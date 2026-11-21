<?php
final class SalesmenController {
    public static function index(): void {
        $rows = db_all("SELECT s.*, w.name AS warehouse,
            (SELECT COUNT(*) FROM customers c WHERE c.salesman_id = s.id) AS assigned_customers,
            (SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = s.id AND status = 'posted' AND date >= ?) AS month_sales
            FROM salesmen s LEFT JOIN warehouses w ON w.id = s.warehouse_id ORDER BY s.name", [date('Y-m-01')]);
        // portable month-start for sqlite vs mysql handled inline above for sqlite; use PHP instead:
        view('salesmen/index', ['rows' => $rows, 'title' => 'Salesmen']);
    }

    public static function newSalesman(): void { self::save(null); }
    public static function editSalesman(): void { self::save(intv(inp('id'))); }

    private static function save(?int $id): void {
        $salesman = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if ($id && !$salesman) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $data = [
                'code' => strtoupper(inp('code')), 'name' => inp('name'), 'phone' => inp('phone'),
                'cnic' => inp('cnic'), 'address' => inp('address'), 'area' => inp('area'),
                'commission_percent' => round(num(inp('commission_percent')), 2),
                'salary' => round(num(inp('salary')), 2),
                'join_date' => inp('join_date') ?: null,
                'vehicle_type' => inp('vehicle_type'),
                'vehicle_reg' => inp('vehicle_reg'),
                'vehicle_note' => inp('vehicle_note'),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $errors = [];
            if ($data['code'] === '' || $data['name'] === '') $errors[] = 'Code and name are required.';
            if (db_val('SELECT 1 FROM salesmen WHERE code = ? AND id != ?', [$data['code'], $id ?? 0])) $errors[] = 'Salesman code already exists.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'salesmen/' . $id . '/edit' : 'salesmen/new'); }
            Database::transaction(function () use ($data, $id, $salesman) {
                if ($id) {
                    db_exec('UPDATE salesmen SET code=?, name=?, phone=?, cnic=?, address=?, area=?, commission_percent=?, salary=?, join_date=?, vehicle_type=?, vehicle_reg=?, vehicle_note=?, is_active=? WHERE id=?',
                        array_merge(array_values($data), [$id]));
                    audit('update', 'salesmen', $id, 'Updated ' . $data['code']);
                } else {
                    $newId = db_insert('salesmen', $data + ['created_at' => now_local()]);
                    // dedicated van/stock location for this salesman
                    $branchId = (int)db_val("SELECT id FROM branches WHERE is_main = 1 LIMIT 1");
                    $whCode = 'WH-' . $data['code'];
                    $existing = db_val('SELECT id FROM warehouses WHERE code = ?', [$whCode]);
                    if (!$existing) {
                        $existing = db_insert('warehouses', ['branch_id' => $branchId ?: null, 'code' => $whCode, 'name' => $data['name'] . ' — Sales Stock', 'type' => 'salesman', 'is_default' => 0, 'is_active' => 1, 'created_at' => now_local()]);
                    }
                    db_exec('UPDATE salesmen SET warehouse_id = ? WHERE id = ?', [$existing, $newId]);
                    audit('create', 'salesmen', $newId, 'Created ' . $data['code'] . ' with sales stock warehouse');
                }
            });
            flash('success', $id ? 'Salesman updated.' : 'Salesman created with a dedicated sales-stock warehouse.');
            redirect('salesmen');
        }
        $suggestedCode = 'SM-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM salesmen') + 1), 3, '0', STR_PAD_LEFT);
        view('salesmen/form', ['salesman' => $salesman, 'suggestedCode' => $suggestedCode, 'title' => $salesman ? 'Edit Salesman' : 'New Salesman']);
    }

    /** Issue goods to a salesman — transfer (stock only) or credit issue at issue rate (payable + optional bonus). */
    public static function issuePicker(): void {
        $salesmen = db_all("SELECT id, code, name, area FROM salesmen WHERE is_active = 1 ORDER BY name");
        view('salesmen/issue_picker', ['salesmen' => $salesmen, 'title' => __('Issue to Salesman')]);
    }

    public static function issueForm(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $fromWh = intv(inp('from_warehouse_id'));
            $issueType = in_array(inp('issue_type'), ['transfer', 'credit_issue'], true) ? inp('issue_type') : 'transfer';
            $schemeId = intv(inp('bonus_scheme')) ?: null;
            $giveBonus = inp('give_bonus', 'no') === 'yes';
            $bonusReason = $giveBonus ? mb_substr(trim(inp('bonus_reason')), 0, 255) : null;
            if (!$fromWh || !$sm['warehouse_id']) { flash('error', __('Source warehouse (and salesman sales warehouse) required.')); redirect('salesmen/' . $id . '/issue'); }
            $productIds = $_POST['items']['product_id'] ?? [];
            $paidQty = $_POST['items']['paid_qty'] ?? [];
            $bonusQty = $_POST['items']['bonus_qty'] ?? [];
            $issueRates = $_POST['items']['issue_rate'] ?? [];
            $items = [];
            $payable = 0.0; $bonusCost = 0.0;
            $badProduct = 0;
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $paid = (float)($paidQty[$i] ?? 0); $bonus = $giveBonus ? (float)($bonusQty[$i] ?? 0) : 0.0; $rate = round((float)($issueRates[$i] ?? 0), 2);
                if ($pid && ($paid + $bonus) > 0) {
                    $k = db_val('SELECT kind FROM products WHERE id = ?', [$pid]);
                    if ($k !== 'finished') { $badProduct++; continue; }
                    $avg = Inventory::avgCost($pid, $fromWh);
                    $items[] = ['product_id' => $pid, 'paid_qty' => $paid, 'bonus_qty' => $bonus, 'issue_rate' => $rate,
                                'qty' => $paid + $bonus, 'line_payable' => round($paid * $rate, 2), 'bonus_cost' => round($bonus * $avg, 2), 'unit_cost' => $avg];
                    $payable += round($paid * $rate, 2);
                    $bonusCost += round($bonus * $avg, 2);
                }
            }
            if ($badProduct > 0) { flash('error', 'Only finished products can be issued to salesmen — raw materials are never issued as saleable stock.'); redirect('salesmen/' . $id . '/issue'); }
            if (!$items) { flash('error', __('Add at least one product with quantity.')); redirect('salesmen/' . $id . '/issue'); }
            $scheme = $schemeId ? db_one('SELECT * FROM bonus_schemes WHERE id = ?', [$schemeId]) : null;
            $threshold = (float)(setting('bonus_approval_threshold', 10000));
            $restricted = ($scheme && (int)$scheme['requires_approval'] === 1) || $bonusCost > $threshold;
            $needsApproval = $restricted; // restricted bonus/stock schemes need Owner/Admin — any issue type

            $issueId = null;
            try {
                Database::transaction(function () use ($sm, $fromWh, $items, $issueType, $payable, $bonusCost, $scheme, $needsApproval, $id, $bonusReason, &$issueId) {
                    $issueNo = 'TMP-' . bin2hex(random_bytes(6));
                    $status = $needsApproval ? 'pending_approval' : 'posted';
                    $issueId = db_insert('salesman_issues', [
                        'issue_no' => $issueNo, 'salesman_id' => $id, 'from_warehouse_id' => $fromWh,
                        'date' => inp('date', today()), 'status' => $status, 'issue_type' => $issueType,
                        'total_payable' => $issueType === 'credit_issue' ? $payable : 0.0, 'total_bonus_cost' => $bonusCost,
                        'bonus_scheme' => $scheme ? $scheme['name'] : null,
                        'bonus_reason' => $bonusReason,
                        'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        db_insert('salesman_issue_items', ['issue_id' => $issueId, 'product_id' => $it['product_id'], 'paid_qty' => $it['paid_qty'], 'bonus_qty' => $it['bonus_qty'], 'qty' => $it['qty'], 'unit_cost' => $it['unit_cost'], 'issue_rate' => $it['issue_rate'], 'line_payable' => $it['line_payable'], 'bonus_cost' => $it['bonus_cost']]);
                    }
                    if ($needsApproval) {
                        $reason = $scheme ? ('Bonus scheme: ' . $scheme['name']) : 'Bonus exceeds approval threshold';
                        Approvals::create('bonus', 'salesman_issues', $issueId, max($payable, $bonusCost), $reason, (int)($_SESSION['user_id'] ?? 0));
                    } else {
                        Posting::postSalesmanIssue($issueId);
                    }
                });
                flash('success', $needsApproval ? 'Credit issue saved - bonus exceeds the approval threshold, awaiting Owner/Admin approval. No stock moved yet.' : ($issueType === 'transfer' ? 'Stock transferred to van.' : 'Credit issue posted - stock moved, receivable booked (bonus at zero charge).'));
                redirect('salesmen/' . $id . '/issues/' . $issueId . '/view');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('salesmen/' . $id . '/issue'); }
        }
        view('salesmen/issue', [
            'sm' => $sm,
            'salesmen' => db_all("SELECT id, code, name, area FROM salesmen WHERE is_active = 1 ORDER BY name"),
            'productsArr' => StockController::productOptions(['finished']),
            'warehouses' => db_all("SELECT id, code, name FROM warehouses WHERE is_active = 1 AND type != 'salesman' ORDER BY name"),
            'products' => json_encode(StockController::productOptions(['finished'])),
            'schemes' => db_all("SELECT * FROM bonus_schemes WHERE is_active = 1 AND kind IN ('salesman','special','promotion') ORDER BY name"),
            'threshold' => (float)(setting('bonus_approval_threshold', 10000)),
            'salesStock' => self::salesStock((int)$sm['warehouse_id']),
            'title' => 'Issue Stock — ' . $sm['name'],
        ]);
    }

    /** Printable stock issue receipt with paid/bonus breakdown — authorized users only. */
    public static function issueView(): void {
        $id = intv(inp('id'));
        $issueId = intv(inp('issue_id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $issue = db_one("SELECT i.*, w.name AS warehouse, u.full_name AS issuer, u2.full_name AS approver FROM salesman_issues i JOIN warehouses w ON w.id = i.from_warehouse_id LEFT JOIN users u ON u.id = i.created_by LEFT JOIN users u2 ON u2.id = i.approved_by WHERE i.id = ? AND i.salesman_id = ?", [$issueId, $id]);
        if (!$issue) { flash('error', __('Issue not found.')); redirect('salesmen/' . $id . '/issue'); }
        $items = db_all("SELECT ii.*, p.sku, p.name, u.short AS unit_short FROM salesman_issue_items ii JOIN products p ON p.id = ii.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE ii.issue_id = ? ORDER BY ii.id", [$issueId]);
        $totPaid = 0.0; $totBonus = 0.0; $totUnits = 0.0; $totPayable = 0.0; $totBonusCost = 0.0;
        foreach ($items as $it) { $totPaid += (float)$it['paid_qty']; $totBonus += (float)$it['bonus_qty']; $totUnits += (float)$it['qty']; $totPayable += (float)$it['line_payable']; $totBonusCost += (float)$it['bonus_cost']; }
        view('salesmen/issue_view', ['sm' => $sm, 'issue' => $issue, 'items' => $items,
            'totPaid' => $totPaid, 'totBonus' => $totBonus, 'totUnits' => $totUnits, 'totPayable' => $totPayable, 'totBonusCost' => $totBonusCost,
            'thermal' => inp('thermal') === '1',
            'title' => 'Issue Receipt ' . $issue['issue_no']]);
    }

    /** Salesman credit ledger - running balance of what the salesman owes the company. */
    public static function ledger(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $entries = db_all('SELECT * FROM salesman_ledger WHERE salesman_id = ? ORDER BY id', [$id]);
        $balance = (float)db_val('SELECT COALESCE(balance,0) FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        $issues = db_all("SELECT i.*, w.name AS warehouse FROM salesman_issues i JOIN warehouses w ON w.id = i.from_warehouse_id WHERE i.salesman_id = ? ORDER BY i.id DESC LIMIT 20", [$id]);
        view('salesmen/ledger', ['sm' => $sm, 'id' => $id, 'entries' => $entries, 'balance' => $balance, 'issues' => $issues, 'title' => 'Ledger — ' . $sm['name']]);
    }

    /** Salesman pays money to the company against his ledger balance (no approval needed — money in). */
    public static function payToCompany(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        $amount = round(num(inp('amount')), 2);
        $accountId = intv(inp('account_id'));
        if (!$sm || $amount <= 0 || !$accountId) { flash('error', __('Amount and account required.')); redirect('salesmen/' . $id . '/ledger'); }
        $balance = (float)db_val('SELECT COALESCE(balance,0) FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        if ($amount > $balance + 0.009) { flash('error', 'Amount exceeds outstanding balance (' . money($balance) . ').'); redirect('salesmen/' . $id . '/ledger'); }
        $gl = Posting::accountGl($accountId);
        Database::transaction(function () use ($sm, $amount, $accountId, $id, $gl, $balance) {
            $journalId = Accounting::postEntry([
                ['account' => $gl, 'debit' => $amount, 'memo' => 'Salesman repayment — ' . $sm['name']],
                ['account' => 'SM_RECEIVABLE', 'credit' => $amount, 'memo' => 'Salesman ledger payment'],
            ], [
                'date' => today(), 'voucher_type' => 'SR', 'reference_type' => 'salesman_repayment',
                'reference_id' => $id, 'memo' => 'Salesman repayment — ' . $sm['name'],
                'idem_key' => 'salesman_repay:' . $id . ':' . bin2hex(random_bytes(5)),
            ]);
            db_insert('salesman_ledger', ['salesman_id' => $id, 'date' => today(), 'doc_type' => 'payment', 'doc_id' => null, 'doc_no' => db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]), 'debit' => 0, 'credit' => $amount, 'balance' => round($balance - $amount, 2), 'notes' => inp('notes') ?: 'Cash/bank deposit', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            audit('payment', 'salesman_ledger', $id, "Salesman paid Rs $amount to company");
            try { Notifications::notifySalesmanPayment($id, $amount, db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]) ?: 'SR-' . $journalId); } catch (Throwable $e) { error_log('notify salesman payment: ' . $e->getMessage()); }
        });
        flash('success', __('Payment received and posted — Dr Cash/Bank, Cr Salesman Receivable; ledger updated.'));
        redirect('salesmen/' . $id . '/ledger');
    }

    public static function settle(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $date = inp('date', today());
        $monthStart = date('Y-m-01', strtotime($date));
        $myCustomers = db_all('SELECT id FROM customers WHERE salesman_id = ?', [$id]);
        $custIds = array_column($myCustomers, 'id');
        $salesToday = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = ? AND status='posted' AND date = ?", [$id, $date]);
        $salesMonth = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = ? AND status='posted' AND date >= ?", [$id, $monthStart]);
        $cashCollected = 0.0;
        if ($custIds) {
            $in = implode(',', array_fill(0, count($custIds), '?'));
            $cashCollected = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE customer_id IN ($in) AND date = ? AND EXISTS (SELECT 1 FROM cash_bank_accounts a WHERE a.id = receipts.account_id AND a.type = 'cash')", array_merge($custIds, [$date]));
        }
        $stock = $sm['warehouse_id'] ? self::salesStock((int)$sm['warehouse_id']) : [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $counted = round(num(inp('cash_counted')), 2);
            Database::transaction(function () use ($id, $date, $salesToday, $cashCollected, $counted) {
                $existing = db_one('SELECT id FROM salesman_settlements WHERE salesman_id = ? AND date = ?', [$id, $date]);
                $data = ['sales_total' => $salesToday, 'collected_total' => $cashCollected, 'cash_counted' => $counted,
                         'expected_cash' => $cashCollected, 'variance' => round($counted - $cashCollected, 2),
                         'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null];
                if ($existing) db_exec('UPDATE salesman_settlements SET sales_total=?, collected_total=?, cash_counted=?, expected_cash=?, variance=?, notes=? WHERE id = ?', array_merge(array_values($data), [$existing['id']]));
                else db_insert('salesman_settlements', $data + ['salesman_id' => $id, 'date' => $date, 'created_at' => now_local()]);
            });
            audit('settle', 'salesman_settlements', $id, "Settled $date");
            flash('success', __('Settlement saved. Variance = counted − expected cash.'));
            redirect('salesmen/' . $id . '/settle', ['date' => $date]);
        }
        $settlements = db_all('SELECT * FROM salesman_settlements WHERE salesman_id = ? ORDER BY date DESC LIMIT 15', [$id]);
        $issues = db_all("SELECT i.*, (SELECT COALESCE(SUM(paid_qty),0) FROM salesman_issue_items WHERE issue_id = i.id) AS paid_qty, (SELECT COALESCE(SUM(bonus_qty),0) FROM salesman_issue_items WHERE issue_id = i.id) AS bonus_qty FROM salesman_issues i WHERE i.salesman_id = ? ORDER BY i.date DESC, i.id DESC LIMIT 12", [$id]);
        view('salesmen/settle', ['sm' => $sm, 'date' => $date, 'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'cashCollected' => $cashCollected, 'stock' => $stock, 'settlements' => $settlements, 'issues' => $issues, 'title' => 'Settle - ' . $sm['name']]);
    }

    public static function bonuses(): void {
        $rows = db_all("SELECT b.*, s.name AS salesman FROM bonuses b JOIN salesmen s ON s.id = b.salesman_id ORDER BY b.id DESC LIMIT 100");
        view('salesmen/bonuses', ['rows' => $rows, 'title' => 'Commission & Bonuses']);
    }

    public static function bonusNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $smId = intv(inp('salesman_id')); $amount = round(num(inp('amount')), 2);
            if (!$smId || $amount <= 0) { flash('error', __('Salesman and amount are required.')); redirect('salesmen/bonuses/new'); }
            db_insert('bonuses', ['bonus_no' => 'BON-' . date('YmdHis'), 'salesman_id' => $smId, 'period_month' => inp('period_month', date('Y-m')), 'amount' => $amount, 'reason' => inp('reason'), 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            audit('create', 'bonuses', null, "Bonus Rs $amount for salesman $smId");
            flash('success', __('Bonus recorded (pending). Pay it via Expenses → category “Sales Commission” to post the journal.'));
            redirect('salesmen/bonuses');
        }
        $isSqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', i.date) = ?" : "DATE_FORMAT(i.date, '%Y-%m') = ?";
        $commissions = db_all("SELECT s.id, s.name, s.commission_percent, COALESCE(SUM(i.total),0) AS month_sales, ROUND(COALESCE(SUM(i.total),0) * s.commission_percent / 100, 2) AS commission
            FROM salesmen s LEFT JOIN sales_invoices i ON i.salesman_id = s.id AND i.status='posted' AND $monthExpr
            WHERE s.is_active = 1 GROUP BY s.id ORDER BY s.name", [date('Y-m')]);
        view('salesmen/bonus_new', ['salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1 ORDER BY name'), 'commissions' => $commissions, 'title' => 'New Bonus / Commission']);
    }

    /** Salesman returns unsold goods to the warehouse (from van stock). */
    public static function salesmanReturn(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $productIds = $_POST['items']['product_id'] ?? [];
            $paidQty = $_POST['items']['paid_qty'] ?? [];
            $bonusQty = $_POST['items']['bonus_qty'] ?? [];
            $issueRates = $_POST['items']['issue_rate'] ?? [];
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $paid = (float)($paidQty[$i] ?? 0); $bonus = (float)($bonusQty[$i] ?? 0);
                if ($pid && ($paid + $bonus) > 0) {
                    $items[] = ['product_id' => $pid, 'paid_qty' => $paid, 'bonus_qty' => $bonus, 'qty' => $paid + $bonus, 'issue_rate' => round((float)($issueRates[$i] ?? 0), 2), 'unit_cost' => 0];
                }
            }
            if (!$items) { flash('error', __('Quantities to return required.')); redirect('salesmen/' . $id . '/return'); }
            try {
                Database::transaction(function () use ($sm, $items, $id) {
                    $srcWh = (int)$sm['warehouse_id'];
                    $returnId = db_insert('salesman_issues', [
                        'issue_no' => 'SRT-' . date('YmdHis'), 'salesman_id' => $id, 'from_warehouse_id' => $srcWh,
                        'date' => inp('date', today()), 'status' => 'posted', 'issue_type' => 'salesman_return',
                        'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        $cost = Inventory::avgCost($it['product_id'], $srcWh);
                        db_insert('salesman_issue_items', ['issue_id' => $returnId, 'product_id' => $it['product_id'], 'paid_qty' => $it['paid_qty'], 'bonus_qty' => $it['bonus_qty'], 'qty' => $it['qty'], 'unit_cost' => $cost, 'issue_rate' => $it['issue_rate'], 'line_payable' => round($it['paid_qty'] * $it['issue_rate'], 2), 'bonus_cost' => 0]);
                    }
                    Posting::postSalesmanReturn($returnId);
                });
                flash('success', __('Salesman return posted — stock restocked, his ledger credited.'));
                redirect('salesmen/' . $id . '/ledger');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('salesmen/' . $id . '/return'); }
        }
        // available stock with the salesman (per product) + last issue rates
        $stock = db_all("SELECT p.id, p.sku, p.name, p.issue_rate, b.qty, b.avg_cost FROM stock_balances b JOIN products p ON p.id = b.product_id WHERE b.warehouse_id = ? AND b.qty > 0 ORDER BY p.name", [(int)$sm['warehouse_id']]);
        view('salesmen/return', ['sm' => $sm, 'stock' => $stock, 'title' => 'Return Stock — ' . $sm['name']]);
    }

    public static function salesStock(int $warehouseId): array {
        return db_all("SELECT p.sku, p.name, COALESCE(b.qty,0) AS qty, COALESCE(b.avg_cost,0) AS avg_cost, ROUND(COALESCE(b.qty,0) * COALESCE(b.avg_cost,0), 2) AS value
            FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ? WHERE b.qty > 0 ORDER BY p.name", [$warehouseId]);
    }

    /** The one-screen salesman account — goods, salary, expenses, advances, history and the payoff. */
    public static function account(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }

        $goods = db_one("SELECT COALESCE(SUM(sii.paid_qty),0) AS issued_paid, COALESCE(SUM(sii.bonus_qty),0) AS issued_bonus, COALESCE(SUM(sii.qty),0) AS issued_units, COALESCE(SUM(sii.line_payable),0) AS issued_value, COUNT(DISTINCT i.id) AS issues FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.status <> 'reversed' AND i.status <> 'pending_approval'", [$id]);
        $sold = db_one("SELECT COALESCE(SUM(si.qty),0) AS units, COALESCE(SUM(si.line_total),0) AS value FROM sales_invoice_items si JOIN sales_invoices iv ON iv.id = si.invoice_id WHERE iv.salesman_id = ? AND iv.status = 'posted' AND si.is_bonus = 0", [$id]);
        $stockRow = db_one("SELECT COALESCE(SUM(qty),0) AS qty, COALESCE(SUM(qty * avg_cost),0) AS value FROM stock_balances WHERE warehouse_id = ?", [(int)$sm['warehouse_id']]);
        $goodsPayable = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        $bonusValue = (float)$goods['issued_bonus'];
        $expensesByType = [];
        foreach (db_all("SELECT expense_type, COALESCE(SUM(amount),0) AS total FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' GROUP BY expense_type", [$id]) as $r) {
            $expensesByType[(string)$r['expense_type']] = (float)$r['total'];
        }
        $diesel = $expensesByType['diesel'] ?? 0; $food = $expensesByType['food'] ?? 0;
        $hotel = $expensesByType['hotel'] ?? 0; $repair = $expensesByType['repair'] ?? 0; $vehicle = $expensesByType['vehicle'] ?? 0;
        $other = $expensesByType['other'] ?? 0;

        $expensesTotal = $diesel + $food + $hotel + $repair + $vehicle + $other;
        $monthlySalary = (float)$sm['salary'];
        $salaryAccrued = 0.0;
        if (!empty($sm['join_date']) && (float)$sm['salary'] > 0) {
            $join = new DateTimeImmutable($sm['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $salaryAccrued = $months * $monthlySalary;
        }
        $salaryPaid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $salaryRemaining = max(0, $salaryAccrued - $salaryPaid);
        $advancesTotal = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $advancesOutstanding = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [$id]);

        $history = [];
        foreach (db_all("SELECT date, doc_type, doc_no, notes, debit, credit FROM salesman_ledger WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 30", [$id]) as $r) {
            $history[] = ['date' => $r['date'], 'kind' => $r['doc_type'] === 'payment' ? 'PAYMENT' : 'GOODS', 'ref' => $r['doc_no'], 'desc' => $r['notes'] ?: $r['doc_type'], 'amount' => $r['credit'] > 0 ? -(float)$r['credit'] : (float)$r['debit']];
        }
        foreach (db_all("SELECT date, period, amount, status FROM salesman_salary_payments WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]) as $r) {
            $history[] = ['kind' => 'SALARY', 'date' => $r['date'], 'ref' => $r['period'] ?: 'Salary', 'desc' => 'Salary payment (' . $r['status'] . ')', 'amount' => -(float)$r['amount']];
        }
        foreach (db_all("SELECT date, amount, status FROM salesman_salary_advances WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]) as $r) {
            $history[] = ['kind' => 'ADVANCE', 'date' => $r['date'], 'ref' => 'Advance', 'desc' => 'Salary advance (' . $r['status'] . ')', 'amount' => -(float)$r['amount']];
        }
        foreach (db_all("SELECT date, expense_type, amount, description FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' ORDER BY date DESC, id DESC LIMIT 20", [$id]) as $r) {
            $history[] = ['kind' => 'EXPENSE', 'date' => $r['date'], 'ref' => strtoupper($r['expense_type']), 'desc' => $r['description'], 'amount' => -(float)$r['amount']];
        }
        usort($history, fn($a, $b) => strcmp($b['date'] ?? '0', $a['date'] ?? '0'));
        $history = array_slice($history, 0, 40);

        $finalBalance = $goodsPayable + $salaryRemaining + $advancesOutstanding;

        view('salesmen/account', [
            'sm' => $sm,
            'goods' => $goods, 'sold' => $sold, 'stockRow' => $stockRow,
            'goodsPayable' => $goodsPayable, 'bonusValue' => $bonusValue,
            'diesel' => $diesel, 'food' => $food, 'hotel' => $hotel, 'repair' => $repair, 'vehicle' => $vehicle, 'other' => $other,
            'expensesTotal' => $expensesTotal,
            'monthlySalary' => $monthlySalary, 'salaryAccrued' => $salaryAccrued, 'salaryPaid' => $salaryPaid, 'salaryRemaining' => $salaryRemaining,
            'advancesTotal' => $advancesTotal, 'advancesOutstanding' => $advancesOutstanding,
            'finalBalance' => $finalBalance,
            'history' => $history,
            'title' => 'Salesman Account - ' . $sm['name'],
        ]);
    }

    /** Salary pay + advances for one salesman. Owner/Admin post directly; others via approval. */
    public static function salary(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $isAdmin = is_owner_or_admin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action', 'pay');
            $accountId = intv(inp('account_id'));
            $amount = round(num(inp('amount')), 2);
            $notes = inp('notes');
            $method = in_array(inp('method'), ['cash', 'bank'], true) ? inp('method') : 'cash';
            $period = inp('period') ?: date('Y-m');
            if (!$accountId || $amount <= 0) { flash('error', __('Amount and payment account are required.')); redirect('salesmen/' . $id . '/salary'); }
            try {
                Database::transaction(function () use ($id, $amount, $accountId, $method, $notes, $action, $isAdmin, $period) {
                    if ($action === 'advance') {
                        $newId = db_insert('salesman_salary_advances', [
                            'salesman_id' => $id, 'date' => today(), 'amount' => $amount,
                            'reason' => $notes ?: 'Advance', 'account_id' => $accountId, 'method' => $method,
                            'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local(),
                        ]);
                        if ($isAdmin) {
                            // Post the advance inline as the owner
                            db_exec("UPDATE salesman_salary_advances SET status = 'approved' WHERE id = ?", [$newId]);
                            Posting::postSalesmanAdvance((int)$newId);
                        } else {
                            Approvals::create('advance', 'salesman_salary_advances', (int)$newId, $amount, 'Advance for ' . db_val('SELECT name FROM salesmen WHERE id = ?', [$id]), (int)($_SESSION['user_id'] ?? 0));
                        }
                        return;
                    }
                    $newId = db_insert('salesman_salary_payments', [
                        'salesman_id' => $id, 'date' => today(), 'amount' => $amount,
                        'account_id' => $accountId, 'method' => $method, 'period' => $period,
                        'notes' => $notes ?: null,
                        'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local(),
                    ]);
                    if ($isAdmin) {
                        Posting::postSalesmanSalary((int)$newId);
                    } else {
                        Approvals::create('payroll', 'salesman_salary_payments', (int)$newId, $amount, 'Salesman salary — ' . $period, (int)($_SESSION['user_id'] ?? 0));
                    }
                });
                flash('success', __($action === 'advance' ? 'Advance processed.' : 'Salary payment processed.'));
            } catch (Throwable $e) { flash('error', 'Payment failed: ' . $e->getMessage()); }
            redirect('salesmen/' . $id . '/salary');
        }

        $monthly = (float)$sm['salary'];
        $accrued = 0.0;
        if (!empty($sm['join_date']) && $monthly > 0) {
            $join = new DateTimeImmutable($sm['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $accrued = $months * $monthly;
        }
        $paid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $remaining = max(0, $accrued - $paid);
        $advancesOut = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [$id]);
        $salaryPayments = db_all("SELECT * FROM salesman_salary_payments WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]);
        $advances = db_all("SELECT * FROM salesman_salary_advances WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]);
        view('salesmen/salary', ['sm' => $sm, 'monthly' => $monthly, 'accrued' => $accrued, 'paid' => $paid, 'remaining' => $remaining, 'advancesOut' => $advancesOut, 'util' => max(0, $remaining - $advancesOut), 'salaryPayments' => $salaryPayments, 'advances' => $advances, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'Salary — ' . $sm['name']]);
    }

    /** Add an expense to a salesman account (diesel|vehicle|food|hotel|repair|other). */
    public static function expense(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $isAdmin = is_owner_or_admin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $accountId = intv(inp('account_id'));
            $amount = round(num(inp('amount')), 2);
            $type = in_array(inp('expense_type'), ['diesel','vehicle','food','hotel','repair','other'], true) ? inp('expense_type') : 'other';
            $desc = inp('description');
            $notes = inp('notes');
            $paymentType = in_array(inp('payment_type'), ['cash','bank'], true) ? inp('payment_type') : 'cash';
            if (!$accountId || $amount <= 0 || $desc === '') { flash('error', __('Description, amount and account are required.')); redirect('salesmen/' . $id . '/expense'); }
            try {
                // attach receipt upload if provided
                $attachment = null;
                if (!empty($_FILES['receipt']['tmp_name']) && is_uploaded_file($_FILES['receipt']['tmp_name'])) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['receipt']['tmp_name']);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
                    if (isset($allowed[$mime]) && $_FILES['receipt']['size'] <= 5 * 1024 * 1024) {
                        $dir = dirname(__DIR__, 2) . '/storage/uploads/salesmen/' . $id;
                        if (!is_dir($dir)) mkdir($dir, 0750, true);
                        $attachment = 'uploads/salesmen/' . $id . '/receipt-' . time() . '-' . random_int(1000, 9999) . '.' . $allowed[$mime];
                        move_uploaded_file($_FILES['receipt']['tmp_name'], dirname(__DIR__, 2) . '/storage/' . $attachment);
                    }
                }
                Database::transaction(function () use ($id, $amount, $type, $desc, $paymentType, $accountId, $notes, $attachment, $isAdmin) {
                    $newId = db_insert('salesman_expenses', [
                        'salesman_id' => $id, 'date' => today(), 'expense_type' => $type,
                        'amount' => $amount, 'description' => $desc, 'payment_type' => $paymentType,
                        'account_id' => $accountId, 'attachment' => $attachment, 'notes' => $notes ?: null,
                        'status' => $isAdmin ? 'approved' : 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0),
                        'created_at' => now_local(),
                    ]);
                    if ($isAdmin) {
                        Posting::postSalesmanExpense((int)$newId);
                    } else {
                        Approvals::create('expense', 'salesman_expenses', (int)$newId, $amount, strtoupper($type) . ' expense — ' . ($desc ?: ''), (int)($_SESSION['user_id'] ?? 0));
                    }
                });
                flash('success', __($isAdmin ? 'Expense recorded.' : 'Expense submitted for approval.'));
            } catch (Throwable $e) { flash('error', 'Failed: ' . $e->getMessage()); }
            redirect('salesmen/' . $id . '/expense');
        }
        $expenses = db_all("SELECT * FROM salesman_expenses WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 40", [$id]);
        view('salesmen/expense', ['sm' => $sm, 'expenses' => $expenses, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'Expenses — ' . $sm['name']]);
    }

    /** Comprehensive settlement: payable, collections, salary, expenses, advances, remaining. */
    public static function settlement(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $goodsPayable = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        $collections = (float)db_val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment'", [$id]);
        $monthlySalary = (float)$sm['salary'];
        $salaryAccrued = 0.0;
        if (!empty($sm['join_date']) && $monthlySalary > 0) {
            $join = new DateTimeImmutable($sm['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $salaryAccrued = $months * $monthlySalary;
        }
        $salaryPaid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $salaryRemaining = max(0, $salaryAccrued - $salaryPaid);
        $expensesPosted = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted'", [$id]);
        $advancesOut = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [$id]);
        $net = $goodsPayable + $salaryRemaining - $collections + $expensesPosted + $advancesOut;
        view('salesmen/settlement', ['sm' => $sm, 'goodsPayable' => $goodsPayable, 'collections' => $collections,
            'salaryRemaining' => $salaryRemaining, 'expensesPosted' => $expensesPosted, 'advancesOut' => $advancesOut, 'net' => $net,
            'title' => 'Settlement — ' . $sm['name']]);
    }
}

