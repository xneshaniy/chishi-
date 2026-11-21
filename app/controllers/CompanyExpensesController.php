<?php
/**
 * Company Expenses — operating/factory expenses recorded here, but every payment\n
 * goes through the same Approvals pipeline used for all existing expenses.\n * A companion `expenses` row is created alongside so Posting/Posting::postExpense()\n * drives the cash/bank movement exactly once on Owner/Admin approval.\n */
final class CompanyExpensesController {
    private static function nextNo(): string {
        $max = (int)db_val("SELECT MAX(CAST(SUBSTR(expense_no, 4) AS INTEGER)) FROM company_expenses WHERE expense_no LIKE 'CE-%'");
        return 'CE-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function index(): void {
        if (!Auth::can('company_expenses.view')) { http_response_code(403); exit('Forbidden'); }
        $q = trim(inp('q')); $cat = intv(inp('category_id')); $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $status = in_array(inp('status'), ['all','pending','posted','rejected','reversed'], true) ? inp('status') : 'all';
        $where = ['e.expense_date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($q !== '') { $where[] = '(e.item_name LIKE ? OR e.supplier LIKE ? OR e.expense_no LIKE ? OR e.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'e.category_id = ?'; $params[] = $cat; }
        if ($status !== 'all') { $where[] = 'e.status = ?'; $params[] = $status; }
        $sql = "SELECT e.*, c.name AS category, a.name AS account FROM company_expenses e JOIN expense_categories c ON c.id = e.category_id LEFT JOIN cash_bank_accounts a ON a.id = e.account_id WHERE " . implode(' AND ', $where) . " ORDER BY e.expense_date DESC, e.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $sum = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM company_expenses e WHERE " . implode(' AND ', $where), $params);
        $pending = (int)db_val("SELECT COUNT(*) FROM company_expenses WHERE status = 'pending'");
        view('company_expenses/index', ['p' => $p, 'q' => $q, 'cat' => $cat, 'from' => $from, 'to' => $to, 'status' => $status, 'sum' => $sum, 'pending' => $pending,
            'categories' => db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name'), 'title' => __('Company Expenses')]);
    }

    public static function newExpense(): void {
        if (!Auth::can('company_expenses.create')) { http_response_code(403); exit('Forbidden'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (idempotency_seen('company-expenses/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('company-expenses');
            }
            try {
                $row = self::recordNew($_POST, $_FILES, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Company expense recorded — pending Owner/Admin approval.'));
                redirect('company-expenses/' . (int)$row['id']);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('company-expenses/new'); }
        }
        $categories = db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name');
        $accounts    = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('company_expenses/new', ['categories' => $categories, 'accounts' => $accounts, 'title' => __('New Company Expense')]);
    }

    /** Pick the right paying account from the form (cash vs bank), with sensible fallbacks. */
    private static function resolveAccountId(array $in, string $paymentType): ?int {
        $cash = intv($in['account_id_cash'] ?? 0);
        $bank = intv($in['account_id_bank'] ?? 0);
        $legacy = intv($in['account_id'] ?? 0);
        if ($paymentType === 'bank') {
            if ($bank > 0) return $bank;
            if ($legacy > 0) return $legacy;
            return null; // bank must be explicitly chosen
        }
        if ($cash > 0) return $cash;
        if ($legacy > 0) return $legacy;
        $first = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'cash' AND is_active = 1 ORDER BY id LIMIT 1");
        return $first > 0 ? $first : null;
    }

    /** Shared logic for creating the expense (used by new + edit POST paths) */
    public static function recordNew(array $in, array $files, int $userId): array {
        $date      = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['expense_date'] ?? '')) ? $in['expense_date'] : today();
        $catId     = intv($in['category_id'] ?? 0);
        $itemName  = trim((string)($in['item_name'] ?? ''));
        $qty       = num($in['quantity'] ?? 0);
        $unit      = trim((string)($in['unit'] ?? ''));
        $rate      = num($in['rate'] ?? 0);
        $amount    = round((float)($in['amount'] ?? 0), 2);
        if ($catId <= 0) throw new RuntimeException(__('Pick an expense category.'));
        if ($itemName === '') throw new RuntimeException(__('Expense item / title is required.'));
        if ($amount <= 0) {
            // auto-compute from qty × rate if both > 0
            if ($qty > 0 && $rate > 0) $amount = round($qty * $rate, 2);
        }
        if ($amount <= 0) throw new RuntimeException(__('Enter a positive amount (or quantity and rate).'));
        $paymentType = in_array($in['payment_method'] ?? 'cash', ['cash', 'bank'], true) ? $in['payment_method'] : 'cash';
        $accountId = self::resolveAccountId($in, $paymentType);
        if ($paymentType === 'bank' && !$accountId) throw new RuntimeException(__('Select the bank account to pay from.'));
        $supplier  = trim((string)($in['supplier'] ?? ''));
        $description = trim((string)($in['description'] ?? ''));
        $notes     = trim((string)($in['notes'] ?? ''));
        $attachment = self::storeUpload($files['attachment'] ?? []);

        return Database::transaction(function () use ($date, $catId, $itemName, $qty, $unit, $rate, $amount, $paymentType, $accountId, $supplier, $description, $notes, $attachment, $userId) {
            // companion expense row for the existing Posting/Approvals pipeline
            $expenseId = db_insert('expenses', [
                'expense_no' => 'TMP-CE-' . bin2hex(random_bytes(5)),
                'category_id' => $catId,
                'date' => $date,
                'amount' => $amount,
                'payee' => $supplier !== '' ? $supplier : null,
                'description' => ($itemName ? $itemName . ' — ' : '') . $description,
                'payment_type' => $paymentType,
                'account_id' => $accountId,
                'status' => 'pending',
                'created_by' => $userId,
                'created_at' => now_local(),
                'person_name' => trim((string)($supplier ?? '')) ?: null,
                'qty' => $qty > 0 ? round($qty, 3) : null,
                'notes' => $notes ?: null,
                'attachment' => $attachment,
            ]);
            $no = self::nextNo();
            $row = [
                'expense_no' => $no,
                'linked_expense_id' => (int)$expenseId,
                'expense_date' => $date,
                'category_id' => (int)$catId,
                'item_name' => $itemName,
                'description' => $description ?: null,
                'quantity' => $qty > 0 ? round($qty, 3) : null,
                'unit' => $unit ?: null,
                'rate' => ($rate > 0 ? round($rate, 2) : null),
                'amount' => $amount,
                'supplier' => $supplier ?: null,
                'payment_type' => $paymentType,
                'account_id' => $accountId,
                'attachment' => $attachment,
                'notes' => $notes ?: null,
                'status' => 'pending',
                'created_by' => $userId,
                'created_at' => now_local(),
            ];
            db_insert('company_expenses', $row);
            $row['id'] = (int)db_val('SELECT id FROM company_expenses WHERE expense_no = ?', [$no]);
            $catName = db_val('SELECT name FROM expense_categories WHERE id = ?', [$catId]);
            Approvals::create('expense', 'expenses', (int)$expenseId, $amount, 'Company expense — ' . ($catName ?: 'expense') . ' — ' . $itemName, $userId);
            audit('create', 'company_expenses', $row['id'], "Company expense Rs $amount ($itemName) — pending approval");
            return $row;
        });
    }

    public static function editExpense(): void {
        if (!Auth::can('company_expenses.create')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $row = db_one('SELECT * FROM company_expenses WHERE id = ?', [$id]);
        if (!$row) { http_response_code(404); exit(__('Company expense not found.')); }
        if ($row['status'] !== 'pending') { flash('error', __('Only pending expenses can be edited.')); redirect('company-expenses/' . $id); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            try {
                self::doEdit($row, $_POST, $_FILES, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Company expense updated.'));
                redirect('company-expenses/' . $id);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('company-expenses/' . $id . '/edit'); }
        }
        $categories = db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name');
        $accounts    = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('company_expenses/edit', ['row' => $row, 'categories' => $categories, 'accounts' => $accounts, 'title' => __('Edit Company Expense') . ' — ' . $row['expense_no']]);
    }

    private static function doEdit(array $row, array $in, array $files, int $userId): void {
        $date      = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['expense_date'] ?? '')) ? $in['expense_date'] : $row['expense_date'];
        $catId     = intv($in['category_id'] ?? $row['category_id']);
        $itemName  = trim((string)($in['item_name'] ?? $row['item_name']));
        $qty       = num($in['quantity'] ?? $row['quantity']);
        $unit      = trim((string)($in['unit'] ?? $row['unit']));
        $rate      = num($in['rate'] ?? $row['rate']);
        $amount    = round((float)($in['amount'] ?? $row['amount']), 2);
        if ($amount <= 0 && $qty > 0 && $rate > 0) $amount = round($qty * $rate, 2);
        if ($catId <= 0 || $itemName === '' || $amount <= 0) throw new RuntimeException(__('Category, item name and a positive amount are required.'));
        $paymentType = in_array($in['payment_method'] ?? $row['payment_type'], ['cash', 'bank'], true) ? ($in['payment_method'] ?? $row['payment_type']) : 'cash';
        $accountId = self::resolveAccountId($in, $paymentType) ?? (int)($row['account_id'] ?: 0) ?: null;
        if ($paymentType === 'bank' && !$accountId) throw new RuntimeException(__('Select the bank account to pay from.'));
        $supplier  = trim((string)($in['supplier'] ?? $row['supplier']));
        $description = trim((string)($in['description'] ?? $row['description']));
        $notes     = trim((string)($in['notes'] ?? $row['notes']));
        $attachment = $row['attachment'];
        if (!empty($files['attachment']['name'])) { $attachment = self::storeUpload($files['attachment']); }
        Database::transaction(function () use ($row, $date, $catId, $itemName, $qty, $unit, $rate, $amount, $paymentType, $accountId, $supplier, $description, $notes, $attachment, $userId) {
            db_exec('UPDATE company_expenses SET expense_date=?, category_id=?, item_name=?, description=?, quantity=?, unit=?, rate=?, amount=?, supplier=?, payment_type=?, account_id=?, attachment=?, notes=? WHERE id=?', [
                $date, $catId, $itemName, $description, ($qty > 0 ? round($qty,3) : null), ($unit ?: null), ($rate > 0 ? round($rate,2) : null), $amount,
                $supplier ?: null, $paymentType, $accountId, $attachment, $notes ?: null, (int)$row['id'],
            ]);
            if (!empty($row['linked_expense_id'])) {
                db_exec('UPDATE expenses SET date=?, category_id=?, amount=?, payee=?, description=?, payment_type=?, account_id=?, person_name=?, qty=?, notes=?, attachment=? WHERE id=?', [
                    $date, $catId, $amount, $supplier ?: null, ($itemName ? $itemName . ' — ' : '') . $description, $paymentType, $accountId,
                    $supplier ?: null, ($qty > 0 ? round($qty,3) : null), $notes ?: null, $attachment, (int)$row['linked_expense_id'],
                ]);
            }
            audit('update', 'company_expenses', (int)$row['id'], "Company expense updated Rs $amount ($itemName)");
        });
    }

    public static function cancelExpense(): void {
        require_post(); csrf_verify();
        // Owner/Admin only — manager cannot cancel accounting-linked rows
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $reason = trim(inp('reason'));
        $row = db_one('SELECT * FROM company_expenses WHERE id = ?', [$id]);
        if (!$row) { http_response_code(404); exit(__('Company expense not found.')); }
        if ($row['status'] !== 'pending' && $row['status'] !== 'posted') { flash('error', __('Only pending or posted expenses can be cancelled.')); redirect('company-expenses/' . $id); }
        Database::transaction(function () use ($row, $reason, $id) {
            $newStatus = 'rejected';
            if (!empty($row['linked_expense_id'])) {
                $linked = db_one('SELECT * FROM expenses WHERE id = ?', [(int)$row['linked_expense_id']]);
                if ($linked && $linked['status'] === 'pending') {
                    db_exec("UPDATE expenses SET status = 'rejected' WHERE id = ?", [(int)$row['linked_expense_id']]);
                    // Close any pending approval request for the companion expense so it can never post later
                    db_exec("UPDATE approval_requests SET status = 'rejected', approver_id = ?, decided_at = ?, decision_note = ? WHERE reference_type = 'expenses' AND reference_id = ? AND status = 'pending'", [(int)($_SESSION['user_id'] ?? 0), now_local(), $reason ?: 'Company expense cancelled', (int)$row['linked_expense_id']]);
                } elseif ($linked && $linked['status'] === 'posted') {
                    // reverse through Posting to keep the ledger balanced; approval stays approved — reversal is the corrective action
                    Posting::reverseExpense((int)$row['linked_expense_id'], $reason ?: 'Company expense cancelled');
                    $newStatus = 'reversed';
                }
            }
            db_exec("UPDATE company_expenses SET status = ? WHERE id = ?", [$newStatus, $id]);
            audit('cancel', 'company_expenses', $id, $row['expense_no'] . ' cancelled — ' . ($reason ?: 'reason not recorded'));
        });
        flash('success', __('Company expense cancelled — record kept for history.'));
        redirect('company-expenses');
    }

    public static function show(): void {
        if (!Auth::can('company_expenses.view')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $row = db_one("SELECT e.*, c.name AS category, a.name AS account, u.username AS recorded_by_username FROM company_expenses e LEFT JOIN expense_categories c ON c.id = e.category_id LEFT JOIN cash_bank_accounts a ON a.id = e.account_id LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?", [$id]);
        if (!$row) { http_response_code(404); exit(__('Company expense not found.')); }
        $auditRows = db_all("SELECT * FROM audit_logs WHERE entity IN ('company_expenses','expenses') AND (entity_id = ? OR (entity = 'expenses' AND entity_id = ?)) ORDER BY id", [(int)$row['id'], (int)($row['linked_expense_id'] ?? 0)]);
        if (empty($auditRows)) $auditRows = db_all("SELECT * FROM audit_logs WHERE entity = 'expenses' AND entity_id = ?", [(int)$row['linked_expense_id']]);
        view('company_expenses/show', ['row' => $row, 'auditRows' => $auditRows, 'title' => __('Company Expense') . ' ' . $row['expense_no']]);
    }

    private static function storeUpload(array $file): ?string {
        if (empty($file['name'])) return null;
        $maxBytes = 5 * 1024 * 1024;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
        if ($file['size'] > $maxBytes) throw new RuntimeException(__('Attachment too large (max 5 MB).'));
        if (!isset($allowed[$mime])) throw new RuntimeException(__('Attachment must be a JPG/PNG/WebP/GIF image or a PDF.'));
        $dir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $attachment = 'coex_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $attachment)) throw new RuntimeException(__('Could not save the attachment.'));
        return $attachment;
    }
}
