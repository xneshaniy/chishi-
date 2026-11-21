<?php
final class SalesController {

    private static function salesmanScope(string $alias = 'sales_invoices'): string {
        if (is_salesman_only()) {
            $my = my_salesman_id() ?: 0;
            return " AND $alias.salesman_id = " . (int)$my;
        }
        return '';
    }

    /* ============ ORDERS ============ */
    public static function orders(): void {
        $status = inp('status');
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'so.status = ?'; $params[] = $status; }
        if (is_salesman_only()) { $where[] = 'so.salesman_id = ?'; $params[] = my_salesman_id() ?: 0; }
        $sql = "SELECT so.*, c.name AS customer, s.name AS salesman FROM sales_orders so JOIN customers c ON c.id = so.customer_id LEFT JOIN salesmen s ON s.id = so.salesman_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY so.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('sales/orders', ['p' => $p, 'status' => $status, 'title' => 'Sales Orders']);
    }

    public static function orderNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $customerId = intv(inp('customer_id'));
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $prices = $_POST['items']['price'] ?? [];
            if (!$customerId) { flash('error', __('Customer required.')); redirect('sales/orders/new'); }
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $q = (float)($qtys[$i] ?? 0); $pr = (float)($prices[$i] ?? 0);
                if ($pid && $q > 0) $items[] = ['product_id' => $pid, 'qty' => $q, 'price' => $pr, 'total' => round($q * $pr, 2)];
            }
            if (!$items) { flash('error', __('Add at least one item.')); redirect('sales/orders/new'); }
            $total = array_sum(array_column($items, 'total'));
            try {
                Database::transaction(function () use ($customerId, $items, $total) {
                    $soId = db_insert('sales_orders', ['so_no' => 'SO-' . date('YmdHis') . substr(str_replace('.', '', (string)microtime(true)), -4), 'customer_id' => $customerId, 'salesman_id' => intv(inp('salesman_id')) ?: null, 'order_date' => inp('order_date', today()), 'delivery_date' => inp('delivery_date') ?: null, 'status' => 'confirmed', 'subtotal' => $total, 'total' => $total, 'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    foreach ($items as $it) db_insert('sales_order_items', ['sales_order_id' => $soId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_price' => $it['price'], 'line_total' => $it['total']]);
                    audit('create', 'sales_orders', $soId, 'Order total Rs ' . number_format($total, 2));
                });
                flash('success', __('Sales order created. Create the invoice when delivering.'));
                redirect('sales/orders');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('sales/orders/new'); }
        }
        view('sales/order_new', ['customers' => self::customerOptions(), 'products' => json_encode(StockController::productOptions(['finished'])), 'salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1'), 'title' => 'New Sales Order']);
    }

    public static function orderConfirm(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        db_exec("UPDATE sales_orders SET status = 'confirmed' WHERE id = ? AND status = 'draft'", [$id]);
        audit('confirm', 'sales_orders', $id, '');
        redirect('sales/orders');
    }

    /* ============ INVOICES ============ */
    public static function invoices(): void {
        $q = inp('q'); $status = inp('status'); $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $where = ['i.date >= ?', 'i.date <= ?']; $params = [$from, $to];
        if ($status !== '') { $where[] = 'i.status = ?'; $params[] = $status; }
        if ($q !== '') { $where[] = '(i.invoice_no LIKE ? OR c.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $whereStr = implode(' AND ', $where);
        if (is_salesman_only()) { $whereStr .= ' AND i.salesman_id = ' . (int)(my_salesman_id() ?: 0); }
        $sql = "SELECT i.*, c.name AS customer, s.name AS salesman FROM sales_invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE $whereStr ORDER BY i.id DESC";
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = db_all($sql, $params);
            StockController::sendCsv('sales_invoices.csv', ['Invoice','Date','Customer','Salesman','Type','Subtotal','Discount','Tax','Total','Status'],
                array_map(fn($r) => [$r['invoice_no'], $r['date'], $r['customer'], $r['salesman'], $r['payment_type'], $r['subtotal'], $r['discount'], $r['tax'], $r['total'], $r['status']], $rows));
        }
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $totals = db_one("SELECT COALESCE(SUM(i.total),0) AS t, COALESCE(SUM(i.tax),0) AS x FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE $whereStr", $params);
        view('sales/invoices', ['p' => $p, 'q' => $q, 'status' => $status, 'from' => $from, 'to' => $to, 'totals' => $totals, 'title' => 'Sales Invoices']);
    }

    public static function invoiceNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $idem = inp('idem_key');
            $customerId = intv(inp('customer_id'));
            $paymentType = in_array(inp('payment_type'), ['cash', 'credit'], true) ? inp('payment_type') : 'cash';
            $warehouseId = intv(inp('warehouse_id'));
            $date = inp('date', today());

            // Salesmen may only sell their assigned customers, from their van stock
            if (is_salesman_only()) {
                $my = my_salesman_id() ?: 0;
                $assigned = db_val('SELECT 1 FROM salesman_customers WHERE salesman_id = ? AND customer_id = ?', [$my, $customerId]) || db_val('SELECT 1 FROM customers WHERE id = ? AND salesman_id = ?', [$customerId, $my]);
                if (!$assigned) { flash('error', __('You can only create invoices for your assigned customers.')); redirect('sales/invoices/new'); }
                $smWh = db_val('SELECT warehouse_id FROM salesmen WHERE id = ?', [$my]);
                if ($smWh) $warehouseId = (int)$smWh;
            }

            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $prices = $_POST['items']['price'] ?? [];
            $discounts = $_POST['items']['discount'] ?? [];
            $taxRates = $_POST['items']['tax_rate'] ?? [];
            $bonuses = $_POST['items']['is_bonus'] ?? [];
            $invoiceDiscount = round(num(inp('discount')), 2);

            $items = [];
            $subtotal = 0.0; $taxTotal = 0.0;
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($productIds[$i] ?? 0); $q = (float)$q; $price = round((float)($prices[$i] ?? 0), 2);
                $disc = round((float)($discounts[$i] ?? 0), 2); $taxRate = round((float)($taxRates[$i] ?? 0), 2);
                $isBonus = !empty($bonuses[$i]);
                if (!$pid || $q <= 0) continue;
                $net = max(0, round($q * $price - $disc, 2));
                $tax = $isBonus ? 0.0 : round($net * $taxRate / 100, 2);
                $lineTotal = $net + $tax;
                if (!$isBonus) { $subtotal += $net; $taxTotal += $tax; }
                $items[] = ['product_id' => $pid, 'qty' => $q, 'price' => $price, 'discount' => $disc, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'line_total' => $lineTotal, 'net_before_tax' => $net, 'is_bonus' => $isBonus ? 1 : 0];
            }
            if (!$warehouseId) { flash('error', __('Warehouse required.')); redirect('sales/invoices/new'); }
            if (!$items) { flash('error', __('Add at least one item.')); redirect('sales/invoices/new'); }
            // Raw materials and packaging can never be sold as finished product (keep separate inventories)
            $badId = (int)db_val("SELECT COUNT(*) FROM products WHERE id IN (" . implode(',', array_fill(0, count($items), '?')) . ") AND kind != 'finished'", array_map(fn($i) => (int)$i['product_id'], $items));
            if ($badId > 0) { flash('error', 'Raw materials and packaging materials cannot be sold. Only finished goods can be invoiced.'); redirect('sales/invoices/new'); }

            $netSubtotal = round($subtotal, 2);
            $total = round($netSubtotal - $invoiceDiscount + $taxTotal, 2);

        // Resolve minimum-price enforcement and restricted-bonus rules
        $belowMin = false;
        $bonusValue = 0.0;
        $schemeId = intv(inp('bonus_scheme')) ?: null;
        foreach ($items as $it) {
            if ((int)$it['is_bonus'] !== 1) {
                $pr = db_one('SELECT min_price FROM products WHERE id = ?', [(int)$it['product_id']]);
                $mp = (float)($pr['min_price'] ?? 0);
                if ($mp > 0 && (float)$it['price'] < $mp) $belowMin = true;
            } else {
                $avg = Inventory::avgCost((int)$it['product_id'], $warehouseId);
                $bonusValue += (float)$it['qty'] * $avg;
            }
        }
        $scheme = $schemeId ? db_one('SELECT * FROM bonus_schemes WHERE id = ?', [$schemeId]) : null;
        $bonusThreshold = (float)(setting('bonus_approval_threshold', 10000));
        $schemeRestricted = $scheme && (int)$scheme['requires_approval'] === 1;
        $bonusRestricted = $schemeRestricted || ((float)$bonusValue > $bonusThreshold && $bonusValue > 0);
        $needsApproval = $belowMin || $bonusRestricted;

        if ($paymentType === 'credit') {
                $cust = db_one('SELECT credit_limit FROM customers WHERE id = ?', [$customerId]);
                $open = Accounting::customerBalance($customerId) + (float)db_val("SELECT COALESCE(SUM(CASE WHEN opening_type='debit' THEN opening_balance ELSE -opening_balance END),0) FROM customers WHERE id = ?", [$customerId]);
                $limit = (float)($cust['credit_limit'] ?? 0);
                if ($limit > 0 && ($open + $total) > $limit) {
                    flash('error', sprintf('Credit limit exceeded: limit Rs %s, outstanding Rs %s, this invoice Rs %s. Ask a manager to raise the limit or collect payment first.', number_format($limit, 2), number_format($open, 2), number_format($total, 2)));
                    redirect('sales/invoices/new');
                }
            }

            if (idempotency_seen('sales/invoices/new', $idem, (int)($_SESSION['user_id'] ?? 0))) {
                $existing = db_val('SELECT id FROM sales_invoices WHERE created_by = ? ORDER BY id DESC LIMIT 1', [$_SESSION['user_id'] ?? 0]);
                flash('info', __('This form was already submitted. Loading the existing invoice.'));
                redirect($existing ? 'sales/invoices/' . $existing : 'sales/invoices');
            }

            $invoiceId = null;
            try {
                Database::transaction(function () use ($items, $customerId, $paymentType, $warehouseId, $date, $netSubtotal, $invoiceDiscount, $taxTotal, $total, $needsApproval, $schemeId, $schemeRestricted, $belowMin, $bonusRestricted, $scheme, &$invoiceId) {
                    $tmpNo = 'TMP-' . bin2hex(random_bytes(6));
                    $salesmanId = is_salesman_only() ? my_salesman_id() : (intv(inp('salesman_id')) ?: null);
                    $dueDate = $paymentType === 'credit' ? (inp('due_date') ?: null) : null;
                    $docStatus = $needsApproval ? 'pending' : 'posted';
                    $invoiceId = db_insert('sales_invoices', [
                        'invoice_no' => $tmpNo, 'customer_id' => $customerId, 'salesman_id' => $salesmanId,
                        'warehouse_id' => $warehouseId, 'date' => $date, 'due_date' => $dueDate,
                        'payment_type' => $paymentType, 'subtotal' => $netSubtotal, 'discount' => $invoiceDiscount,
                        'tax' => $taxTotal, 'total' => $total, 'cost_total' => 0, 'status' => $docStatus,
                        'bonus_scheme' => $scheme ? $scheme['name'] : null, 'authorized_by' => null,
                        'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        db_insert('sales_invoice_items', ['invoice_id' => $invoiceId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_price' => $it['price'], 'discount' => $it['discount'], 'tax_rate' => $it['tax_rate'], 'tax_amount' => $it['tax_amount'], 'line_total' => $it['line_total'], 'net_before_tax' => $it['net_before_tax'], 'cost_price' => 0, 'cost_total' => 0, 'is_bonus' => $it['is_bonus']]);
                    }
                    if ($needsApproval) {
                        $customReason = trim((string)inp('discount_reason'));
                        $reason = $customReason !== '' ? $customReason
                            : (($belowMin && $bonusRestricted) ? 'Below-minimum selling price AND restricted bonus'
                                : ($belowMin ? 'Selling below minimum price'
                                    : 'Bonus goods beyond approval threshold — bonus authorization required'));
                        Approvals::create($belowMin ? 'discount' : 'bonus', 'sales_invoices', (int)$invoiceId, $total, $reason, (int)($_SESSION['user_id'] ?? 0));
                    } else {
                        Posting::postSalesInvoice((int)$invoiceId);
                    }
                });
                if ($needsApproval) {
                    flash('success', __('Invoice saved as PENDING — Owner/Admin must approve before stock moves and the journal posts.'));
                } else {
                    flash('success', __('Invoice posted — stock deducted, journal created.'));
                }
                redirect('sales/invoices/' . $invoiceId);
            } catch (Throwable $e) {
                error_log('Sales invoice failed: ' . $e->getMessage());
                flash('error', 'Invoice failed: ' . $e->getMessage());
                redirect('sales/invoices/new');
            }
        }
        $scopeSm = is_salesman_only();
        $customers = self::customerOptions();
        view('sales/invoice_new', [
            'customers' => $customers,
            'warehouses' => db_all("SELECT id, code, name, type FROM warehouses WHERE is_active = 1" . ($scopeSm ? " AND id = (SELECT warehouse_id FROM salesmen WHERE id = " . (int)(my_salesman_id() ?: 0) . ")" : '') . " ORDER BY type, name"),
            'products' => json_encode(StockController::productOptions(['finished'])),
            'salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1'),
            'scopeSm' => $scopeSm,
            'title' => 'New Sales Invoice']);
    }

    public static function invoiceView(): void {
        $id = intv(inp('id'));
        $inv = db_one("SELECT i.*, c.name AS customer, c.address AS customer_address, c.phone AS customer_phone, c.area, c.ntn AS customer_ntn, s.name AS salesman FROM sales_invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE i.id = ?", [$id]);
        if (!$inv) { flash('error', __('Invoice not found.')); redirect('sales/invoices'); }
        if (is_salesman_only() && (int)$inv['salesman_id'] !== (int)(my_salesman_id() ?: 0)) { http_response_code(403); exit('Not your invoice.'); }
        $items = db_all("SELECT ii.*, p.sku, p.name, u.short AS unit FROM sales_invoice_items ii JOIN products p ON p.id = ii.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE ii.invoice_id = ?", [$id]);
        view('sales/invoice_view', ['inv' => $inv, 'items' => $items, 'title' => 'Invoice ' . $inv['invoice_no']]);
    }

    public static function invoiceReverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $reason = inp('reason', 'Manual reversal');
        try {
            Posting::reverseSalesInvoice($id, $reason);
            flash('success', __('Invoice reversed — stock restored and a reversal journal was posted.'));
        } catch (Throwable $e) { flash('error', 'Reversal failed: ' . $e->getMessage()); }
        redirect('sales/invoices/' . $id);
    }

    /* ============ RETURNS ============ */
    public static function returns(): void {
        $p = paginate("SELECT r.*, c.name AS customer, i.invoice_no FROM sales_returns r JOIN customers c ON c.id = r.customer_id JOIN sales_invoices i ON i.id = r.sales_invoice_id ORDER BY r.id DESC", [], intv(inp('page', 1)), 20);
        view('sales/returns', ['p' => $p, 'title' => 'Sales Returns']);
    }

    public static function returnNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $invoiceId = intv(inp('invoice_id'));
            $invoice = db_one("SELECT * FROM sales_invoices WHERE id = ? AND status = 'posted'", [$invoiceId]);
            if (!$invoice) { flash('error', __('Select a posted invoice.')); redirect('sales/returns/new'); }
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $q = (float)($qtys[$i] ?? 0);
                if (!$pid || $q <= 0) continue;
                $line = db_one('SELECT * FROM sales_invoice_items WHERE invoice_id = ? AND product_id = ?', [$invoiceId, $pid]);
                if (!$line) continue;
                // quantity already returned for this invoice/product
                $already = (float)db_val("SELECT COALESCE(SUM(ri.qty),0) FROM sales_return_items ri JOIN sales_returns r ON r.id = ri.return_id WHERE r.sales_invoice_id = ? AND ri.product_id = ? AND r.status = 'posted'", [$invoiceId, $pid]);
                $maxReturn = (float)$line['qty'] - $already;
                $q = min($q, $maxReturn);
                if ($q <= 0) continue;
                $items[] = ['product_id' => $pid, 'qty' => $q,
                    'unit_price' => (float)$line['unit_price'],
                    'line_total' => round($q * (float)$line['unit_price'] - 0, 2),
                    'cost_price' => (float)$line['cost_price']];
            }
            if (!$items) { flash('error', __('Nothing to return (quantities exceed invoiced/returned amounts).')); redirect('sales/returns/new'); }
            try {
                Database::transaction(function () use ($invoice, $items, $invoiceId) {
                    $returnId = db_insert('sales_returns', ['return_no' => 'TMP-' . bin2hex(random_bytes(6)), 'sales_invoice_id' => $invoiceId, 'customer_id' => (int)$invoice['customer_id'], 'date' => inp('date', today()), 'reason' => inp('reason'), 'total' => 0, 'status' => 'posted', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    foreach ($items as $it) db_insert('sales_return_items', ['return_id' => $returnId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_price' => $it['unit_price'], 'line_total' => $it['line_total'], 'cost_price' => $it['cost_price']]);
                    Posting::postSalesReturn($returnId);
                });
                flash('success', __('Sales return posted — stock restocked and journal created.'));
                redirect('sales/returns');
            } catch (Throwable $e) { flash('error', 'Return failed: ' . $e->getMessage()); redirect('sales/returns/new'); }
        }
        $invoiceId = intv(inp('invoice_id'));
        $invoice = $invoiceId ? db_one("SELECT i.*, c.name AS customer FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ? AND i.status = 'posted'", [$invoiceId]) : null;
        $items = [];
        if ($invoice) {
            $items = db_all("SELECT ii.*, p.sku, p.name, u.short AS unit,
                (SELECT COALESCE(SUM(ri.qty),0) FROM sales_return_items ri JOIN sales_returns r ON r.id = ri.return_id WHERE r.sales_invoice_id = ? AND ri.product_id = ii.product_id AND r.status='posted') AS returned_qty
                FROM sales_invoice_items ii JOIN products p ON p.id = ii.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE ii.invoice_id = ?", [$invoiceId, $invoiceId]);
        }
        view('sales/return_new', ['invoice' => $invoice, 'items' => $items, 'invoices' => db_all("SELECT i.id, i.invoice_no, c.name AS customer, i.total FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE i.status = 'posted' ORDER BY i.id DESC LIMIT 200"), 'title' => 'New Sales Return']);
    }

    public static function returnReverse(): void {
        require_post(); csrf_verify();
        try {
            Posting::reverseSalesReturn(intv(inp('id')), inp('reason', 'Manual reversal'));
            flash('success', __('Return reversed.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('sales/returns');
    }

    /* ============ helpers ============ */
    private static function customerOptions(): array {
        if (is_salesman_only()) {
            $my = (int)(my_salesman_id() ?: 0);
            return db_all("SELECT c.id, c.code, c.name, c.credit_limit FROM customers c WHERE c.is_active = 1 AND (c.salesman_id = ? OR c.id IN (SELECT customer_id FROM salesman_customers WHERE salesman_id = ?)) ORDER BY c.name", [$my, $my]);
        }
        return db_all('SELECT id, code, name, credit_limit FROM customers WHERE is_active = 1 ORDER BY name');
    }
}


