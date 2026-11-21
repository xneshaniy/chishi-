<?php
/**
 * Claims — one section, two workflows (tabbed):
 *   1. Customer Claims  — customer credit/shortage claims (existing module, unchanged behavior)
 *   2. Claim Goods      — supplier claim receipts with Good / Damaged / Rejected split:
 *                          accepted → stock + claim payable (cash or pay-later),
 *                          damaged/rejected → recorded only (never saleable stock),
 *                          Admin approval required for posting and for any later payment.
 */
final class ClaimsController {

    /* ============ CUSTOMER CLAIMS (existing) ============ */

    public static function index(): void {
        $status = inp('status');
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }
        if (is_salesman_only()) {
            $my = (int)(my_salesman_id() ?: 0);
            $where[] = 'c.customer_id IN (SELECT id FROM customers WHERE salesman_id = ?)'; $params[] = $my;
        }
        $sql = "SELECT c.*, cu.name AS customer, i.invoice_no FROM claims c JOIN customers cu ON cu.id = c.customer_id LEFT JOIN sales_invoices i ON i.id = c.sales_invoice_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY c.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('claims/index', ['p' => $p, 'status' => $status, 'title' => 'Claims']);
    }

    public static function newClaim(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $customerId = intv(inp('customer_id'));
            $description = inp('description');
            $amount = round(num(inp('amount')), 2);
            if (!$customerId || $description === '') { flash('error', __('Customer and description are required.')); redirect('claims/new'); }
            if (is_salesman_only()) {
                $my = (int)(my_salesman_id() ?: 0);
                $assigned = db_val('SELECT 1 FROM customers WHERE id = ? AND salesman_id = ?', [$customerId, $my]);
                if (!$assigned) { flash('error', __('You can only file claims for your customers.')); redirect('claims/new'); }
            }
            $id = db_insert('claims', ['claim_no' => 'CLM-' . date('YmdHis'), 'customer_id' => $customerId, 'sales_invoice_id' => intv(inp('sales_invoice_id')) ?: null, 'date' => inp('date', today()), 'description' => $description, 'amount' => $amount, 'status' => 'pending', 'created_at' => now_local()]);
            audit('create', 'claims', $id, $description);
            flash('success', __('Claim registered — pending approval.'));
            redirect('claims');
        }
        $customers = is_salesman_only()
            ? db_all('SELECT id, code, name FROM customers WHERE salesman_id = ? OR id IN (SELECT customer_id FROM salesman_customers WHERE salesman_id = ?)', [my_salesman_id() ?: 0, my_salesman_id() ?: 0])
            : db_all('SELECT id, code, name FROM customers WHERE is_active = 1 ORDER BY name');
        view('claims/new', ['customers' => $customers, 'invoices' => db_all("SELECT id, invoice_no FROM sales_invoices WHERE status='posted' ORDER BY id DESC LIMIT 100"), 'title' => 'New Customer Claim']);
    }

    public static function setStatus(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $status = in_array(inp('status'), ['approved', 'rejected', 'pending'], true) ? inp('status') : 'pending';
        db_exec('UPDATE claims SET status = ?, approved_by = ? WHERE id = ?', [$status, $_SESSION['user_id'] ?? null, $id]);
        audit('update', 'claims', $id, "Status → $status");
        flash('success', __('Claim updated.'));
        redirect('claims');
    }

    /* ============ CLAIM GOODS (supplier claim receipts — NEW) ============ */

    /** Claim Goods list: summary cards (claim history KPIs) + supplier-wise history + rows. */
    public static function goodsList(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $supplierId = intv(inp('supplier_id')); $status = inp('status');
        $where = ['cg.date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($supplierId > 0) { $where[] = 'cg.supplier_id = ?'; $params[] = $supplierId; }
        if ($status !== '') { $where[] = 'cg.status = ?'; $params[] = $status; }
        $base = 'FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id WHERE ' . implode(' AND ', $where);

        $totals = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(cg.good_qty),0) AS good_qty, COALESCE(SUM(cg.damaged_qty),0) AS damaged_qty, COALESCE(SUM(cg.rejected_qty),0) AS rejected_qty, COALESCE(SUM(cg.claim_amount),0) AS claim_amount, COALESCE(SUM(cg.cash_paid),0) AS cash_paid, COALESCE(SUM(cg.claim_amount - cg.cash_paid),0) AS outstanding $base AND cg.status IN ('posted','pending_approval')", $params);
        $supplierHistory = db_all("SELECT s.id, s.name AS supplier, COUNT(cg.id) AS claims, COALESCE(SUM(cg.good_qty),0) AS good_qty, COALESCE(SUM(cg.damaged_qty),0) AS damaged_qty, COALESCE(SUM(cg.rejected_qty),0) AS rejected_qty, COALESCE(SUM(cg.claim_amount),0) AS claim_amount, COALESCE(SUM(cg.cash_paid),0) AS cash_paid, COALESCE(SUM(cg.claim_amount - cg.cash_paid),0) AS outstanding $base AND cg.status = 'posted' GROUP BY s.id ORDER BY outstanding DESC", $params);

        $sql = "SELECT cg.*, s.name AS supplier FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id WHERE " . implode(' AND ', $where) . " ORDER BY cg.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('claims/goods', [
            'from' => $from, 'to' => $to, 'supplierId' => $supplierId, 'status' => $status,
            'totals' => $totals, 'supplierHistory' => $supplierHistory, 'p' => $p,
            'suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'title' => 'Claim Goods',
        ]);
    }

    /** New claim receipt form (GET) / create + submit for Admin approval (POST). */
    public static function goodsNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $supplierId = intv(inp('supplier_id'));
            $warehouseId = intv(inp('warehouse_id'));
            $date = inp('date', today());
            $reason = inp('reason');
            $paymentType = in_array(inp('payment_type'), ['cash', 'outstanding'], true) ? inp('payment_type') : 'outstanding';
            $payAccount = intv(inp('pay_account_id'));
            $piId = intv(inp('purchase_invoice_id')) ?: null;
            $errors = [];
            if (!$supplierId) $errors[] = 'Supplier is required.';
            if (!$warehouseId) $errors[] = 'Warehouse is required.';
            if ($paymentType === 'cash' && !$payAccount) $errors[] = 'Select the cash/bank account for immediate payment.';
            $productIds = $_POST['items']['product_id'] ?? [];
            $goodQty = $_POST['items']['good_qty'] ?? [];
            $damagedQty = $_POST['items']['damaged_qty'] ?? [];
            $rejectedQty = $_POST['items']['rejected_qty'] ?? [];
            $rates = $_POST['items']['claim_rate'] ?? [];
            $items = []; $totalClaim = 0.0; $sumGood = 0.0; $sumDmg = 0.0; $sumRej = 0.0;
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $g = (float)($goodQty[$i] ?? 0); $d = (float)($damagedQty[$i] ?? 0); $r = (float)($rejectedQty[$i] ?? 0); $rate = round((float)($rates[$i] ?? 0), 2);
                if ($g + $d + $r <= 0) continue;
                if ($g < 0 || $d < 0 || $r < 0 || $rate < 0) { $errors[] = 'Quantities and claim rate must not be negative.'; break; }
                $line = round($g * $rate, 2);
                $items[] = ['product_id' => $pid, 'good_qty' => $g, 'damaged_qty' => $d, 'rejected_qty' => $r, 'claim_rate' => $rate, 'line_claim' => $line];
                $totalClaim += $line; $sumGood += $g; $sumDmg += $d; $sumRej += $r;
            }
            if (!$items) $errors[] = 'Add at least one product with a quantity.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect('claims/goods/new'); }
            $totalClaim = round($totalClaim, 2);

            try {
                Database::transaction(function () use ($supplierId, $warehouseId, $date, $reason, $paymentType, $payAccount, $piId, $items, $totalClaim, $sumGood, $sumDmg, $sumRej, &$claimId) {
                    $claimNo = 'CG-' . date('YmdHis') . substr(str_replace('.', '', (string)microtime(true)), -4);
                    $claimId = db_insert('claim_goods', [
                        'claim_no' => $claimNo, 'supplier_id' => $supplierId, 'purchase_invoice_id' => $piId,
                        'warehouse_id' => $warehouseId, 'date' => $date, 'reason' => $reason,
                        'status' => 'pending_approval', 'payment_type' => $paymentType, 'pay_account_id' => $payAccount ?: null,
                        'good_qty' => round($sumGood, 3), 'damaged_qty' => round($sumDmg, 3), 'rejected_qty' => round($sumRej, 3),
                        'claim_amount' => $totalClaim, 'cash_paid' => 0,
                        'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        db_insert('claim_goods_items', ['claim_goods_id' => $claimId, 'product_id' => $it['product_id'], 'good_qty' => $it['good_qty'], 'damaged_qty' => $it['damaged_qty'], 'rejected_qty' => $it['rejected_qty'], 'claim_rate' => $it['claim_rate'], 'line_claim' => $it['line_claim']]);
                    }
                    Approvals::create('claim', 'claim_goods', $claimId, $totalClaim, 'Claim goods ' . $claimNo . ' — accepted ' . number_format($sumGood, 3) . ' units', (int)($_SESSION['user_id'] ?? 0));
                    audit('request', 'claim_goods', $claimId, "Claim receipt Rs {$totalClaim} — pending Admin approval");
                });
                flash('success', __('Claim received — Owner/Admin must approve it before stock moves or any payable/cash entry posts.'));
                redirect('claims/goods/' . $claimId);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('claims/goods/new'); }
        }
        view('claims/goods_new', [
            'suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'warehouses' => db_all('SELECT id, code, name FROM warehouses WHERE is_active = 1 AND type != \'salesman\' ORDER BY name'),
            'accounts' => db_all('SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name'),
            'purchaseInvoices' => db_all("SELECT id, invoice_no FROM purchase_invoices WHERE status = 'posted' ORDER BY id DESC LIMIT 100"),
            'products' => StockController::productOptions(),
            'title' => 'New Claim Receipt',
        ]);
    }

    /** Claim receipt detail: split quantities, amounts, approval state, payments ledger. */
    public static function goodsView(): void {
        $id = intv(inp('id'));
        $cg = db_one('SELECT cg.*, s.name AS supplier, s.code AS supplier_code, w.name AS warehouse FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id JOIN warehouses w ON w.id = cg.warehouse_id WHERE cg.id = ?', [$id]);
        if (!$cg) { flash('error', 'Claim not found.'); redirect('claims/goods'); }
        $items = db_all('SELECT ci.*, p.sku, p.name AS product FROM claim_goods_items ci JOIN products p ON p.id = ci.product_id WHERE ci.claim_goods_id = ? ORDER BY p.name', [$id]);
        $payments = db_all('SELECT p.*, a.name AS account FROM claim_goods_payments p JOIN cash_bank_accounts a ON a.id = p.account_id WHERE p.claim_goods_id = ? ORDER BY p.id', [$id]);
        $outstanding = max(0, round((float)$cg['claim_amount'] - (float)$cg['cash_paid'], 2));
        view('claims/goods_view', ['cg' => $cg, 'items' => $items, 'payments' => $payments, 'outstanding' => $outstanding, 'title' => 'Claim ' . $cg['claim_no']]);
    }

    /** Register a (partial or full) payment against a posted outstanding claim — routes to Owner/Admin approval. */
    public static function goodsPay(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $amount = round(num(inp('amount')), 2);
        $accountId = intv(inp('account_id'));
        $date = inp('date', today());
        $cg = db_one('SELECT * FROM claim_goods WHERE id = ?', [$id]);
        if (!$cg) { flash('error', 'Claim not found.'); redirect('claims/goods'); }
        if ($cg['status'] !== 'posted') { flash('error', 'The claim must be approved and posted before payment can be recorded.'); redirect('claims/goods/' . $id); }
        $outstanding = max(0, round((float)$cg['claim_amount'] - (float)$cg['cash_paid'], 2));
        if ($amount <= 0) { flash('error', 'Amount must be positive.'); redirect('claims/goods/' . $id); }
        if ($amount > $outstanding + 0.009) { flash('error', 'Amount exceeds outstanding claim balance (' . money($outstanding) . ').'); redirect('claims/goods/' . $id); }
        if (!$accountId) { flash('error', 'Select the cash/bank account to pay from.'); redirect('claims/goods/' . $id); }
        try {
            Database::transaction(function () use ($id, $amount, $accountId, $date, &$pid) {
                $pid = db_insert('claim_goods_payments', [
                    'claim_goods_id' => $id, 'date' => $date, 'amount' => $amount, 'account_id' => $accountId,
                    'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                ]);
                $cg = db_one('SELECT claim_no FROM claim_goods WHERE id = ?', [$id]);
                Approvals::create('payment', 'claim_goods_payments', $pid, $amount, 'Claim payment — ' . $cg['claim_no'], (int)($_SESSION['user_id'] ?? 0));
                audit('request', 'claim_goods_payments', $pid, "Claim payment Rs $amount — pending Owner/Admin approval");
            });
            flash('success', __('Payment submitted — Owner/Admin must approve it before it posts to the ledger.'));
            redirect('claims/goods/' . $id);
        } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('claims/goods/' . $id); }
    }

    /** Printable claim receipt (cash or outstanding copy). */
    public static function goodsReceipt(): void {
        $id = intv(inp('id'));
        $cg = db_one('SELECT cg.*, s.name AS supplier, s.code AS supplier_code, w.name AS warehouse FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id JOIN warehouses w ON w.id = cg.warehouse_id WHERE cg.id = ?', [$id]);
        if (!$cg) { flash('error', 'Claim not found.'); redirect('claims/goods'); }
        $items = db_all('SELECT ci.*, p.sku, p.name AS product FROM claim_goods_items ci JOIN products p ON p.id = ci.product_id WHERE ci.claim_goods_id = ? ORDER BY p.name', [$id]);
        $payments = db_all('SELECT p.*, a.name AS account FROM claim_goods_payments p JOIN cash_bank_accounts a ON a.id = p.account_id WHERE p.claim_goods_id = ? AND p.status = \'posted\' ORDER BY p.id', [$id]);
        view('claims/receipt', ['cg' => $cg, 'items' => $items, 'payments' => $payments, 'title' => 'Claim Receipt ' . $cg['claim_no']]);
    }
}
