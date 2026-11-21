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
        $sql = "SELECT so.*, COALESCE(c.name, 'Walk-in') AS customer, s.name AS salesman FROM sales_orders so LEFT JOIN customers c ON c.id = so.customer_id LEFT JOIN salesmen s ON s.id = so.salesman_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY so.id DESC";
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
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $q = (float)($qtys[$i] ?? 0); $pr = (float)($prices[$i] ?? 0);
                if ($pid && $q > 0) $items[] = ['product_id' => $pid, 'qty' => $q, 'price' => $pr, 'total' => round($q * $pr, 2)];
            }
            if (!$items) { flash('error', __('Add at least one item.')); redirect('sales/orders/new'); }
            $total = array_sum(array_column($items, 'total'));
            try {
                Database::transaction(function () use ($customerId, $items, $total) {
                    $soId = db_insert('sales_orders', ['so_no' => 'SO-' . date('YmdHis') . substr(str_replace('.', '', (string)microtime(true)), -4), 'customer_id' => $customerId > 0 ? $customerId : null, 'salesman_id' => intv(inp('salesman_id')) ?: null, 'order_date' => inp('order_date', today()), 'delivery_date' => inp('delivery_date') ?: null, 'status' => 'confirmed', 'subtotal' => $total, 'total' => $total, 'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
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
        if ($q !== '') { $where[] = '(i.invoice_no LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR s.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $whereStr = implode(' AND ', $where);
        if (is_salesman_only()) { $whereStr .= ' AND i.salesman_id = ' . (int)(my_salesman_id() ?: 0); }
        $sql = "SELECT i.*, COALESCE(c.name, 'Walk-in') AS customer, s.name AS salesman FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE $whereStr ORDER BY i.id DESC";
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = db_all($sql, $params);
            StockController::sendCsv('sales_invoices.csv', ['Invoice','Date','Customer','Salesman','Type','Subtotal','Discount','Tax','Total','Status'],
                array_map(fn($r) => [$r['invoice_no'], $r['date'], $r['customer'], $r['salesman'], $r['payment_type'], $r['subtotal'], $r['discount'], $r['tax'], $r['total'], $r['status']], $rows));
        }
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $totals = db_one("SELECT COALESCE(SUM(i.total),0) AS t, COALESCE(SUM(i.tax),0) AS x FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE $whereStr", $params);
        view('sales/invoices', ['p' => $p, 'q' => $q, 'status' => $status, 'from' => $from, 'to' => $to, 'totals' => $totals, 'title' => 'Sales Invoices']);
    }

    public static function invoiceNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $idem = inp('idem_key');
            // Salesman (was: customer) — optional counter sale; required for credit sales
            $salesmanId = is_salesman_only() ? (int)(my_salesman_id() ?: 0) : intv(inp('salesman_id'));
            if (!is_salesman_only() && $salesmanId > 0) {
                $smOk = db_val('SELECT 1 FROM salesmen WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$salesmanId]);
                if (!$smOk) { flash('error', __('Select a valid active salesman (or use Cash Sale / counter sale).')); redirect('sales/invoices/new'); }
            }
            $paymentType = in_array(inp('payment_type'), ['cash', 'credit'], true) ? inp('payment_type') : 'cash';
            if ($paymentType === 'credit' && $salesmanId <= 0) { flash('error', __('Credit sales need a salesman — pick one, or use Cash Sale.')); redirect('sales/invoices/new'); }
            // Cash/bank receiving account is no longer asked on this form — auto-select the company's default cash account
            $cashAccountId = null;
            if ($paymentType === 'cash') {
                $cashAccountId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY id LIMIT 1") ?: null;
            }
            $date = inp('date', today());
            // Sales invoices always deduct from the one main warehouse (salesmen: their van stock)
            $warehouseId = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");

            // Salesmen invoice from their van stock; customer-optional walk-in sales are fine (van stock is already theirs)
            if (is_salesman_only()) {
                $my = my_salesman_id() ?: 0;
                $smWh = db_val('SELECT warehouse_id FROM salesmen WHERE id = ?', [$my]);
                if ($smWh) $warehouseId = (int)$smWh;
            }

            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $prices = $_POST['items']['price'] ?? [];
            $discounts = $_POST['items']['discount'] ?? [];
            $taxRates = $_POST['items']['tax_rate'] ?? [];
            $bonusQtys = $_POST['items']['bonus_qty'] ?? [];
            $invoiceDiscount = round(num(inp('discount')), 2);
            // Selecting a salesman posts a Goods Issue from Main Warehouse to the van:
            // payable must use the product's salesman issue rate, and quantities are Packets.
            $isIssue = $salesmanId > 0 && !is_salesman_only();

            $items = [];
            $subtotal = 0.0; $taxTotal = 0.0;
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($productIds[$i] ?? 0); $q = (float)$q; $bonus = (float)($bonusQtys[$i] ?? 0);
                $price = round((float)($prices[$i] ?? 0), 2);
                if ($isIssue && $pid > 0) $price = round((float)db_val('SELECT issue_rate FROM products WHERE id = ?', [$pid]), 2);
                $disc = round((float)($discounts[$i] ?? 0), 2); $taxRate = round((float)($taxRates[$i] ?? 0), 2);
                if (!$pid || ($q + $bonus) <= 0) continue;
                if ($q > 0) {
                    $net = max(0, round($q * $price - $disc, 2));
                    $tax = round($net * $taxRate / 100, 2);
                    $lineTotal = $net + $tax;
                    $subtotal += $net; $taxTotal += $tax;
                    $items[] = ['product_id' => $pid, 'qty' => $q, 'price' => $price, 'discount' => $disc, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'line_total' => $lineTotal, 'net_before_tax' => $net, 'is_bonus' => 0];
                }
                if ($bonus > 0) {
                    // Bonus/free line — zero payable, no tax; stock is still deducted (postSalesInvoice deducts every item)
                    $items[] = ['product_id' => $pid, 'qty' => $bonus, 'price' => 0, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 0, 'net_before_tax' => 0, 'is_bonus' => 1];
                }
            }
            if (!$items) { flash('error', __('Add at least one item.')); redirect('sales/invoices/new'); }
            // Raw materials and packaging can never be sold as finished product (keep separate inventories)
            $badId = (int)db_val("SELECT COUNT(*) FROM products WHERE id IN (" . implode(',', array_fill(0, count($items), '?')) . ") AND kind != 'finished'", array_map(fn($i) => (int)$i['product_id'], $items));
            if ($badId > 0) { flash('error', __('Raw materials and packaging materials cannot be sold. Only finished goods can be invoiced.')); redirect('sales/invoices/new'); }

            // Stock check — aggregated by product against the selling location (Main Warehouse for company sale; van for a salesman-only session)
            $wantedQty = [];
            foreach ($items as $it) $wantedQty[(int)$it['product_id']] = ($wantedQty[(int)$it['product_id']] ?? 0) + (float)$it['qty'];
            foreach ($wantedQty as $pid => $q) {
                $bal = (float)db_val('SELECT COALESCE(qty, 0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $warehouseId]);
                if ($q > $bal + 1e-9) {
                    $sku = db_val('SELECT sku FROM products WHERE id = ?', [$pid]);
                    flash('error', __('Insufficient stock for ') . $sku . ': ' . __('available') . ' ' . number_format($bal, 3) . ' ' . __('Packets') . ', ' . __('requested') . ' ' . number_format($q, 3) . ' ' . __('Packets') . '. ' . __('Please add stock first.'));
                    redirect('sales/invoices/new');
                }
            }

            $netSubtotal = round($subtotal, 2);
            $total = round($netSubtotal - $invoiceDiscount + $taxTotal, 2);

        // Resolve minimum-price enforcement (sale path only) and bonus-cost threshold for the issue path
        $belowMin = false;
        if (!$isIssue) {
            foreach ($items as $it) {
                if ((int)$it['is_bonus'] === 1) continue; // free lines are never minimum-priced
                $pr = db_one('SELECT min_price FROM products WHERE id = ?', [(int)$it['product_id']]);
                $mp = (float)($pr['min_price'] ?? 0);
                if ($mp > 0 && (float)$it['price'] < $mp) $belowMin = true;
            }
        }
        $threshold = (float)(setting('bonus_approval_threshold', 10000));
        $bonusCost = 0.0;
        foreach ($items as $it) {
            if ((int)$it['is_bonus'] !== 1) continue;
            $avg = Inventory::avgCost((int)$it['product_id'], (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1"));
            $bonusCost += round((float)$it['qty'] * $avg, 2);
        }
        $needsApproval = $belowMin || $bonusCost > $threshold; // applies to both sale and issue paths

        // (No customer on this form anymore — credit sales attach to the salesman; AR lines post untagged as before.)

            if (idempotency_seen('sales/invoices/new', $idem, (int)($_SESSION['user_id'] ?? 0))) {
                $existing = db_val('SELECT id FROM sales_invoices WHERE created_by = ? ORDER BY id DESC LIMIT 1', [$_SESSION['user_id'] ?? 0]);
                flash('info', __('This form was already submitted. Loading the existing invoice.'));
                redirect($existing ? 'sales/invoices/' . $existing : 'sales/invoices');
            }

            $invoiceId = null;
            try {
                // If a salesman is selected the page acts as "Goods Issue" (Main Warehouse → salesman van):
                // close exactly the same posting engine used by the Goods Issue flow, no duplicate records.
                if ($salesmanId > 0 && !is_salesman_only()) {
                    $warehouseId = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
                    $smWarehouse = db_val('SELECT warehouse_id FROM salesmen WHERE id = ?', [$salesmanId]);
                    if (!$smWarehouse) { flash('error', __('Selected salesman has no stock location. Assign a van first.')); redirect('sales/invoices/new'); }
                    Database::transaction(function () use ($items, $salesmanId, $smWarehouse, $warehouseId, $date, $total, $needsApproval, &$invoiceId) {
                        $tmpNo = 'SI-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
                        $issueNo = $tmpNo;
                        $issueType = 'credit_issue';
                        $status = $needsApproval ? 'pending_approval' : 'posted';
                        $issueId = db_insert('salesman_issues', [
                            'issue_no' => $issueNo, 'salesman_id' => $salesmanId, 'from_warehouse_id' => $warehouseId,
                            'date' => $date, 'status' => $status, 'issue_type' => $issueType,
                            'total_payable' => $total, 'total_bonus_cost' => 0,
                            'bonus_scheme' => null, 'bonus_reason' => null,
                            'notes' => null, 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                        ]);
                        foreach ($items as $it) {
                            $cost = Inventory::avgCost((int)$it['product_id'], $warehouseId);
                            $paid = (float)$it['qty'];
                            $bonus = (int)$it['is_bonus'] === 1 ? (float)$it['qty'] : 0.0;
                            db_insert('salesman_issue_items', ['issue_id' => $issueId, 'product_id' => (int)$it['product_id'], 'paid_qty' => $paid - $bonus, 'bonus_qty' => $bonus, 'qty' => $paid, 'unit_cost' => $cost, 'issue_rate' => (float)$it['price'], 'line_payable' => round(($paid - $bonus) * (float)$it['price'], 2), 'bonus_cost' => round($bonus * $cost, 2)]);
                        }
                        if ($needsApproval) {
                            Approvals::create('bonus', 'salesman_issues', (int)$issueId, $total, 'Bonus issue — Owner/Admin approval required', (int)($_SESSION['user_id'] ?? 0));
                        } else {
                            Posting::postSalesmanIssue((int)$issueId);
                        }
                        $invoiceId = $issueId;
                    });
                    flash('success', __('Goods issued to salesman — stock transferred, history updated.'));
                    redirect('salesmen/' . $salesmanId . '/issue');
                }
                Database::transaction(function () use ($items, $salesmanId, $paymentType, $cashAccountId, $warehouseId, $date, $netSubtotal, $invoiceDiscount, $taxTotal, $total, $needsApproval, $belowMin, &$invoiceId) {
                    $tmpNo = 'TMP-' . bin2hex(random_bytes(6));
                    $dueDate = $paymentType === 'credit' ? (inp('due_date') ?: null) : null;
                    $docStatus = $needsApproval ? 'pending' : 'posted';
                    $invoiceId = db_insert('sales_invoices', [
                        'invoice_no' => $tmpNo, 'customer_id' => null, 'salesman_id' => $salesmanId > 0 ? $salesmanId : null,
                        'warehouse_id' => $warehouseId, 'date' => $date, 'due_date' => $dueDate,
                        'payment_type' => $paymentType, 'cash_account_id' => $cashAccountId, 'subtotal' => $netSubtotal, 'discount' => $invoiceDiscount,
                        'tax' => $taxTotal, 'total' => $total, 'cost_total' => 0, 'status' => $docStatus,
                        'bonus_scheme' => null, 'authorized_by' => null,
                        'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        db_insert('sales_invoice_items', ['invoice_id' => $invoiceId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_price' => $it['price'], 'discount' => $it['discount'], 'tax_rate' => $it['tax_rate'], 'tax_amount' => $it['tax_amount'], 'line_total' => $it['line_total'], 'net_before_tax' => $it['net_before_tax'], 'cost_price' => 0, 'cost_total' => 0, 'is_bonus' => $it['is_bonus']]);
                    }
                    if ($needsApproval) {
                        Approvals::create('discount', 'sales_invoices', (int)$invoiceId, $total, 'Selling below minimum price', (int)($_SESSION['user_id'] ?? 0));
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
                flash('error', __('Invoice failed: ') . $e->getMessage());
                redirect('sales/invoices/new');
            }
        }
        $salesmen = db_all('SELECT id, code, name, area FROM salesmen WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0 ORDER BY name');
        $mySalesman = null;
        if (is_salesman_only()) { $mySalesman = db_one('SELECT id, code, name, area FROM salesmen WHERE id = ?', [(int)(my_salesman_id() ?: 0)]); }
        $productsFinished = StockController::productOptions(['finished']);
        $whForView = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
        if (is_salesman_only()) { $v = db_val('SELECT warehouse_id FROM salesmen WHERE id = ?', [(int)(my_salesman_id() ?: 0)]); if ($v) $whForView = (int)$v; }
        $stockMap = [];
        foreach (db_all('SELECT product_id, qty FROM stock_balances WHERE warehouse_id = ?', [$whForView]) as $r) {
            $stockMap[(int)$r['product_id']] = round((float)$r['qty'], 3);
        }
        $productsFinished = array_map(fn($p) => $p + ['stock' => $stockMap[(int)$p['id']] ?? 0], $productsFinished);
        $productsFinishedJson = json_encode($productsFinished);
        view('sales/invoice_new', [
            'salesmen' => $salesmen,
            'mySalesman' => $mySalesman,
            'products' => $productsFinishedJson,
            'stockList' => array_map(fn($p) => ['id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'stock' => $p['stock']], $productsFinished),
            'title' => 'New Sales Invoice']);
    }

    public static function invoiceView(): void {
        $id = intv(inp('id'));
        $inv = db_one("SELECT i.*, COALESCE(c.name, 'Walk-in') AS customer, c.address AS customer_address, c.phone AS customer_phone, c.area, c.ntn AS customer_ntn, s.name AS salesman, s.code AS salesman_code, s.area AS salesman_area FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE i.id = ?", [$id]);
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
        } catch (Throwable $e) { flash('error', __('Reversal failed: ') . $e->getMessage()); }
        redirect('sales/invoices/' . $id);
    }

    /* ============ RETURNS ============ */
    public static function returns(): void {
        $p = paginate("SELECT r.*, COALESCE(c.name, 'Walk-in') AS customer, i.invoice_no FROM sales_returns r LEFT JOIN customers c ON c.id = r.customer_id JOIN sales_invoices i ON i.id = r.sales_invoice_id ORDER BY r.id DESC", [], intv(inp('page', 1)), 20);
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
                    $returnId = db_insert('sales_returns', ['return_no' => 'TMP-' . bin2hex(random_bytes(6)), 'sales_invoice_id' => $invoiceId, 'customer_id' => !empty($invoice['customer_id']) ? (int)$invoice['customer_id'] : null, 'date' => inp('date', today()), 'reason' => inp('reason'), 'total' => 0, 'status' => 'posted', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    foreach ($items as $it) db_insert('sales_return_items', ['return_id' => $returnId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_price' => $it['unit_price'], 'line_total' => $it['line_total'], 'cost_price' => $it['cost_price']]);
                    Posting::postSalesReturn($returnId);
                });
                flash('success', __('Sales return posted — stock restocked and journal created.'));
                redirect('sales/returns');
            } catch (Throwable $e) { flash('error', __('Return failed: ') . $e->getMessage()); redirect('sales/returns/new'); }
        }
        $invoiceId = intv(inp('invoice_id'));
        $invoice = $invoiceId ? db_one("SELECT i.*, COALESCE(c.name, 'Walk-in') AS customer FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ? AND i.status = 'posted'", [$invoiceId]) : null;
        $items = [];
        if ($invoice) {
            $items = db_all("SELECT ii.*, p.sku, p.name, u.short AS unit,
                (SELECT COALESCE(SUM(ri.qty),0) FROM sales_return_items ri JOIN sales_returns r ON r.id = ri.return_id WHERE r.sales_invoice_id = ? AND ri.product_id = ii.product_id AND r.status='posted') AS returned_qty
                FROM sales_invoice_items ii JOIN products p ON p.id = ii.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE ii.invoice_id = ?", [$invoiceId, $invoiceId]);
        }
        view('sales/return_new', ['invoice' => $invoice, 'items' => $items, 'invoices' => db_all("SELECT i.id, i.invoice_no, COALESCE(c.name, 'Walk-in') AS customer, i.total FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.status = 'posted' ORDER BY i.id DESC LIMIT 200"), 'title' => 'New Sales Return']);
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


