<?php
/**
 * Notification engine — real-time alerts for the ERP.
 *
 * - notifyRoles / notifyUsers build in-app notifications for exactly the users
 *   who hold the relevant role (no cross-permission leaks; e.g. a salesman
 *   never sees payment approvals or payroll alerts).
 * - dedupe_key is UNIQUE; inserting with INSERT/OR IGNORE prevents duplicates.
 * - Delivery: in-app bell + unread badge (poll every 15 s) + browser desktop
 *   popup (Web Notification API) + optional chime (Web Audio).
 * - Every delivery is best-effort: notification failures NEVER roll back the
 *   business transaction that triggered them.
 * - Links are always internal app routes (validated) to the relevant record.
 */
final class Notifications {

    /* ============ Core API ============ */

    /** Sanitize an internal link — only same-host front-controller URLs or relative routes allowed. */
    public static function safeLink(?string $link): ?string {
        if (!$link) return null;
        $link = trim($link);
        $path = parse_url($link, PHP_URL_PATH) ?? '';
        $query = parse_url($link, PHP_URL_QUERY) ?? '';
        // Relative front-controller link: index.php?r=...
        if (preg_match('#^index\.php\?r=[a-zA-Z0-9_\.\-/]+(\&[a-zA-Z0-9_\.\-\=\%/]*)*$#', $link)) return $link;
        if ($path && preg_match('#/index\.php$#', $path) && $query && str_starts_with($query, 'r=')) {
            $host = parse_url($link, PHP_URL_HOST);
            if (!$host || strtolower($host) === strtolower($_SERVER['HTTP_HOST'] ?? 'localhost')) return $link; // same host
        }
        return null;
    }

    /** Resolve active user ids holding any of the given role codes. */
    public static function usersWithRoles(array $roles): array {
        if (!$roles) return [];
        $marks = implode(',', array_fill(0, count($roles), '?'));
        return array_map('intval', array_column(db_all(
            "SELECT DISTINCT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.code IN ($marks) AND u.is_active = 1", $roles), 'id'));
    }

    /** Send to explicit user ids (skip self for events the recipient is actor of if not wanted). Returns count created. */
    public static function send(array $userIds, string $type, string $title, ?string $body, ?string $link, ?string $entityType = null, ?int $entityId = null, ?string $dedupeKey = null): int {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return 0;
        $link = self::safeLink($link);
        $created = 0;
        $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($userIds as $uid) {
            try {
                if ($driver === 'sqlite') {
                    db_exec("INSERT OR IGNORE INTO notifications (user_id, type, title, body, link, entity_type, entity_id, dedupe_key, created_at) VALUES (?,?,?,?,?,?,?,?,?)",
                        [$uid, $type, $title, $body, $link, $entityType, $entityId, $dedupeKey, now_local()]);
                } else {
                    db_exec("INSERT IGNORE INTO notifications (user_id, type, title, body, link, entity_type, entity_id, dedupe_key, created_at) VALUES (?,?,?,?,?,?,?,?,?)",
                        [$uid, $type, $title, $body, $link, $entityType, $entityId, $dedupeKey, now_local()]);
                }
                if (db()->lastInsertId()) $created++;
            } catch (Throwable $e) {
                error_log('notification send failed (ignored): ' . $e->getMessage());
            }
        }
        return $created;
    }

    /** Send to users holding any of the given roles. */
    public static function notifyRoles(array $roles, string $type, string $title, ?string $body, ?string $link, ?string $entityType = null, ?int $entityId = null, ?string $dedupeKey = null): int {
        return self::send(self::usersWithRoles($roles), $type, $title, $body, $link, $entityType, $entityId, $dedupeKey);
    }

    public static function unreadCount(int $userId): int {
        return (int)db_val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    }

    /** Latest N notifications for the bell dropdown (unread first, newest first). */
    public static function fetchDropdown(int $userId, int $limit = 8): array {
        return db_all("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, id DESC LIMIT " . max(1, min(20, $limit)), [$userId]);
    }

    public static function history(int $userId, string $filter, int $page, int $perPage = 20): array {
        $where = ' WHERE user_id = ?';
        if ($filter === 'unread') $where .= ' AND is_read = 0';
        if ($filter === 'read') $where .= ' AND is_read = 1';
        return paginate('SELECT * FROM notifications' . $where . ' ORDER BY id DESC', [$userId], $page, $perPage);
    }

    public static function markRead(int $userId, int $id, bool $read): void {
        db_exec('UPDATE notifications SET is_read = ? WHERE id = ? AND user_id = ?', [$read ? 1 : 0, $id, $userId]);
    }

    public static function markAllRead(int $userId): void {
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }

    /** Current user's sound/desktop preferences (stored on the user account). */
    public static function prefs(int $userId): array {
        $row = db_one('SELECT COALESCE(notify_sound,1) AS sound, COALESCE(notify_desktop,1) AS desktop FROM users WHERE id = ?', [$userId]) ?: ['sound' => 1, 'desktop' => 1];
        return ['sound' => (int)$row['sound'], 'desktop' => (int)$row['desktop']];
    }

    public static function setPref(int $userId, string $key, bool $value): void {
        if (!in_array($key, ['sound', 'desktop'], true)) return;
        $col = $key === 'sound' ? 'notify_sound' : 'notify_desktop';
        db_exec("UPDATE users SET $col = ? WHERE id = ?", [$value ? 1 : 0, $userId]);
    }

    /* ============ Event helpers (all wrapped in try/catch internally via send) ============ */

    /** Someone submitted an outgoing payment / expense / advance / loan / bonus / discount request → Owner/Admin (+ note for Manager on purchases?). */
    public static function notifyApprovalRequested(int $approvalId): void {
        $a = db_one('SELECT * FROM approval_requests WHERE id = ?', [$approvalId]);
        if (!$a) return;
        $requester = db_one('SELECT username, full_name FROM users WHERE id = ?', [(int)$a['requested_by']]);
        $who = $requester ? ($requester['full_name'] ?: $requester['username']) : 'Unknown user';
        $map = ['payment' => 'payment', 'transfer' => 'transfer', 'expense' => 'expense', 'payroll' => 'salary', 'advance' => 'salary advance', 'loan' => 'employee loan', 'bonus' => 'bonus', 'discount' => 'discount', 'refund' => 'refund', 'claim' => 'claim', 'purchase_request' => 'purchase request', 'purchase_invoice' => 'purchase / GRN', 'maintenance' => 'maintenance', 'other' => 'request'];
        $what = $map[$a['request_type']] ?? $a['request_type'];
        $roles = ['owner', 'admin'];
        if (in_array($a['request_type'], ['payment', 'transfer', 'expense'], true)) $roles[] = 'manager';
        self::notifyRoles($roles, 'payment_request',
            __('Approval needed: ') . strtoupper($what) . ' Rs ' . number_format((float)$a['amount'], 2),
            ($who ? $who . __(' requested') : __('Request')) . ($a['reason'] ? ' — ' . $a['reason'] : ''),
            'index.php?r=admin/approvals',
            'approval', (int)$a['id'], 'approval_requested:' . (int)$a['id']);
    }

    /** Admin approved/rejected → notify the person who requested it. */
    public static function notifyApprovalDecided(int $approvalId, bool $approved, ?string $note): void {
        $a = db_one('SELECT * FROM approval_requests WHERE id = ?', [$approvalId]);
        if (!$a) return;
        $decider = db_one('SELECT username, full_name FROM users WHERE id = ?', [(int)($a['approver_id'] ?? 0)]);
        $who = $decider ? ($decider['full_name'] ?: $decider['username']) : 'Admin';
        $title = $approved ? __('Request APPROVED') : __('Request REJECTED');
        $body = strtoupper($a['request_type']) . ' Rs ' . number_format((float)$a['amount'], 2) . __(' — decided by ') . $who . ($note ? ' — ' . $note : '');
        self::send([(int)$a['requested_by']], 'approval', $title, $body,
            'index.php?r=admin/approvals',
            'approval', (int)$a['id'], 'approval_decided:' . (int)$a['id']);
    }

    /** Posted sale invoice → Admin + Manager. */
    public static function notifySale(int $invoiceId): void {
        $inv = db_one('SELECT i.*, c.name AS customer FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ?', [$invoiceId]);
        if (!$inv) return;
        self::notifyRoles(['owner', 'admin', 'manager'], 'sale',
            __('New sale — ') . $inv['invoice_no'] . ' Rs ' . number_format((float)$inv['total'], 2),
            __('Customer: ') . $inv['customer'],
            'index.php?r=sales/invoices/' . $invoiceId,
            'sales_invoice', $invoiceId, 'sale_posted:' . $invoiceId);
    }

    /** Posted purchase (GRN) → Admin + Manager. */
    public static function notifyPurchase(int $purchaseId): void {
        $p = db_one('SELECT p.*, s.name AS supplier FROM purchase_invoices p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$purchaseId]);
        if (!$p) return;
        self::notifyRoles(['owner', 'admin', 'manager'], 'purchase',
            __('Stock purchased — ') . $p['invoice_no'] . ' Rs ' . number_format((float)$p['total'], 2),
            __('Supplier: ') . $p['supplier'],
            'index.php?r=purchase/invoices/' . $purchaseId,
            'purchase_invoice', $purchaseId, 'purchase_posted:' . $purchaseId);
    }

    /** Customer payment → Admin + Accountant. */
    public static function notifyReceipt(int $receiptId): void {
        $r = db_one("SELECT r.*, c.name AS party FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id WHERE r.id = ?", [$receiptId]);
        if (!$r) return;
        self::notifyRoles(['owner', 'admin', 'accountant'], 'receipt',
            __('Customer payment — Rs ') . number_format((float)$r['amount'], 2),
            ($r['party'] ?? __('Customer')) . __(' paid'),
            'index.php?r=cashbank/receipts',
            'receipt', $receiptId, 'receipt_posted:' . $receiptId);
    }

    /** Salesman deposited money → Admin + Accountant. */
    public static function notifySalesmanPayment(int $salesmanId, float $amount, string $docNo): void {
        $sm = db_one('SELECT name FROM salesmen WHERE id = ?', [$salesmanId]);
        self::notifyRoles(['owner', 'admin', 'accountant'], 'receipt',
            __('Salesman deposited — Rs ') . number_format($amount, 2),
            ($sm['name'] ?? __('Salesman')) . __(' paid into company account'),
            'index.php?r=salesmen/' . $salesmanId . '/ledger',
            'salesman', $salesmanId, 'salesman_deposit:' . $docNo . ':' . $salesmanId);
    }

    /** Claim goods posted (accepted stock + payable) → Owner/Admin/Manager. */
    public static function notifyClaimGoods(int $claimId): void {
        $cg = db_one('SELECT cg.*, s.name AS supplier FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id WHERE cg.id = ?', [$claimId]);
        if (!$cg) return;
        self::notifyRoles(['owner', 'admin', 'manager'], 'claim',
            __('Claim received — ') . $cg['claim_no'] . __(' — accepted Rs ') . number_format((float)$cg['claim_amount'], 2),
            __('Supplier: ') . $cg['supplier'] . __(' · good ') . qty($cg['good_qty']) . __(' / damaged ') . qty($cg['damaged_qty']) . __(' / rejected ') . qty($cg['rejected_qty']),
            'index.php?r=claims/goods/' . $claimId,
            'claim_goods', $claimId, 'claim_posted:' . $claimId);
    }

    /** Claim payment posted to supplier → Owner/Admin/Accountant. */
    public static function notifyClaimPayment(int $paymentId): void {
        $p = db_one('SELECT p.*, cg.claim_no, s.name AS supplier FROM claim_goods_payments p JOIN claim_goods cg ON cg.id = p.claim_goods_id JOIN suppliers s ON s.id = cg.supplier_id WHERE p.id = ?', [$paymentId]);
        if (!$p) return;
        self::notifyRoles(['owner', 'admin', 'accountant'], 'claim',
            __('Claim payment — Rs ') . number_format((float)$p['amount'], 2) . ' — ' . $p['claim_no'],
            __('Supplier: ') . $p['supplier'],
            'index.php?r=claims/goods/' . (int)$p['claim_goods_id'],
            'claim_payment', $paymentId, 'claim_payment:' . $paymentId);
    }

    /** Low stock alert for products at/below reorder level — deduped per product per day. `kind` restricts to raw/packaging or finished. */
    public static function checkLowStockKind(array $kinds): int {
        $marks = implode(',', array_fill(0, count($kinds), '?'));
        $low = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level, COALESCE(SUM(sb.qty),0) AS on_hand
                       FROM products p JOIN stock_balances sb ON sb.product_id = p.id
                       JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'
                       WHERE p.is_active = 1 AND p.kind IN ($marks)
                       GROUP BY p.id HAVING on_hand <= p.reorder_level", $kinds);
        $created = 0;
        foreach ($low as $p) {
            $suggested = max(0, ((float)$p['reorder_level'] * 2) - (float)$p['on_hand']);
            $created += self::notifyRoles(['owner', 'admin', 'manager'], 'low_stock',
                __('LOW STOCK ') . ($p['kind'] === 'finished' ? __('(Finished)') : __('(Raw Material)')) . ' — ' . $p['name'],
                __('SKU ') . $p['sku'] . __(' on hand ') . qty($p['on_hand']) . __(' ≤ reorder level ') . qty($p['reorder_level']) . __(' — suggested order ≈ ') . qty($suggested) . __(' units'),
                $p['kind'] === 'finished' ? 'index.php?r=inventory/finished' : 'index.php?r=inventory/raw',
                'product', (int)$p['id'], 'lowstock:' . $p['kind'] . ':' . (int)$p['id'] . ':' . date('Y-m-d'));
        }
        return $created;
    }

    /** Low stock alert — raw materials and finished goods alerted separately (dedup per product per day). */
    public static function checkLowStock(): int {
        return self::checkLowStockKind(['raw', 'packaging']) + self::checkLowStockKind(['finished']);
    }

    /** Overdue credit invoices — Admin + Accountant, deduped per invoice per day. */
    public static function checkOverdue(): int {
        $rows = db_all("SELECT i.id, i.invoice_no, i.total, i.due_date, c.name AS customer
                        FROM sales_invoices i JOIN customers c ON c.id = i.customer_id
                        WHERE i.status = 'posted' AND i.payment_type = 'credit' AND i.due_date IS NOT NULL AND i.due_date < ? AND i.customer_id NOT IN (0)", [today()]);
        $created = 0;
        foreach ($rows as $r) {
            $created += self::notifyRoles(['owner', 'admin', 'accountant'], 'overdue',
                __('OVERDUE — ') . $r['invoice_no'] . ' Rs ' . number_format((float)$r['total'], 2),
                ($r['customer'] ?? __('Customer')) . __(' overdue since ') . date_display($r['due_date']),
                'index.php?r=sales/invoices/' . (int)$r['id'],
                'sales_invoice', (int)$r['id'], 'overdue:' . (int)$r['id'] . ':' . date('Y-m-d'));
        }
        return $created;
    }

    /** Important accounting events → Admin + Accountant. */
    public static function notifyAccounting(string $type, string $title, string $body, string $link, int $entityId, string $dedupe): void {
        self::notifyRoles(['owner', 'admin', 'accountant'], $type, $title, $body, $link, 'journal_entry', $entityId, $dedupe);
    }
}
