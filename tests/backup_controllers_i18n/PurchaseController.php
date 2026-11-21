<?php
final class PurchaseController {

    public static function orders(): void {
        $status = inp('status');
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'po.status = ?'; $params[] = $status; }
        $sql = "SELECT po.*, s.name AS supplier FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY po.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('purchase/orders', ['p' => $p, 'status' => $status, 'title' => 'Purchase Orders']);
    }

    public static function orderNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $supplierId = intv(inp('supplier_id'));
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $costs = $_POST['items']['cost'] ?? [];
            if (!$supplierId) { flash('error', __('Supplier required.')); redirect('purchase/orders/new'); }
            $items = [];
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($productIds[$i] ?? 0); $q = (float)$q; $c = (float)($costs[$i] ?? 0);
                if ($pid && $q > 0) $items[] = ['product_id' => $pid, 'qty' => $q, 'cost' => $c, 'total' => round($q * $c, 2)];
            }
            if (!$items) { flash('error', __('Add at least one item.')); redirect('purchase/orders/new'); }
            $total = array_sum(array_column($items, 'total'));
            $isAdmin = is_owner_or_admin();
            Database::transaction(function () use ($supplierId, $items, $total, $isAdmin) {
                $poId = db_insert('purchase_orders', ['po_no' => 'PO-' . date('YmdHis') . substr(str_replace('.', '', (string)microtime(true)), -4), 'supplier_id' => $supplierId, 'order_date' => inp('order_date', today()), 'expected_date' => inp('expected_date') ?: null, 'status' => $isAdmin ? 'approved' : 'pending_approval', 'subtotal' => $total, 'total' => $total, 'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                foreach ($items as $it) db_insert('purchase_order_items', ['po_id' => $poId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_cost' => $it['cost'], 'line_total' => $it['total']]);
                if (!$isAdmin) {
                    Approvals::create('purchase_request', 'purchase_orders', $poId, $total, 'Purchase request to ' . db_val('SELECT name FROM suppliers WHERE id = ?', [$supplierId]), (int)($_SESSION['user_id'] ?? 0));
                }
                audit('create', 'purchase_orders', $poId, "PO total Rs " . number_format($total, 2));
            });
            flash('success', $isAdmin ? __('Purchase order created (approved).') : __('Purchase request submitted — Owner/Admin must approve it before it is confirmed.'));
            redirect('purchase/orders');
        }
        view('purchase/order_new', ['suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'), 'products' => json_encode(StockController::productOptions()), 'title' => 'New Purchase Order']);
    }

    public static function orderApprove(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        if (!is_owner_or_admin()) { flash('error', 'Only Owner/Admin can approve purchase orders.'); redirect('purchase/orders'); }
        // Approvals engine is the source of truth; direct approve flips a pending request (idempotent, exactly-once handled in Posting)
        $req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'purchase_orders' AND reference_id = ? AND status = 'pending'", [$id]);
        if ($req) {
            try { Approvals::decide((int)$req['id'], true, 'Approved from purchase orders', (int)($_SESSION['user_id'] ?? 0)); flash('success', __('Purchase approved.')); }
            catch (Throwable $e) { flash('error', $e->getMessage()); }
        } else {
            flash('error', 'No pending approval request for this order.');
        }
        redirect('purchase/orders');
    }

    public static function orderCancel(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        db_exec("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ? AND status != 'received'", [$id]);
        audit('cancel', 'purchase_orders', $id, '');
        redirect('purchase/orders');
    }

    /** Raw Material Purchases — only raw/packaging purchases, with supplier, qty, rate, total, payment status and remaining stock. */
    public static function rawMaterials(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $q = inp('q'); $status = inp('status');
        $where = ["pi.date >= ?", "pi.date <= ?", "p.kind IN ('raw','packaging')"];
        $params = [$from, $to];
        if ($status !== '') { $where[] = 'pi.status = ?'; $params[] = $status; }
        if ($q !== '') { $where[] = '(s.name LIKE ? OR pi.invoice_no LIKE ? OR pi.supplier_invoice_no LIKE ? OR p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $totQtyKg = 0.0;
        $sql = "SELECT pi.id, pi.invoice_no, pi.date, pi.payment_type, pi.payment_status, pi.amount_paid, pi.status, pi.total, pi.discount, pi.tax,
                       s.id AS supplier_id, s.name AS supplier,
                       p.id AS product_id, p.sku, p.name AS product, p.kind,
                       ii.qty, ii.unit_short, ii.qty_kg, ii.unit_cost, ii.line_total, ii.is_bonus,
                       (SELECT COALESCE(SUM(sb2.qty),0) FROM stock_balances sb2 JOIN warehouses w ON w.id = sb2.warehouse_id AND w.type <> 'salesman' WHERE sb2.product_id = p.id) AS on_hand
                FROM purchase_invoices pi
                JOIN suppliers s ON s.id = pi.supplier_id
                JOIN purchase_items ii ON ii.invoice_id = pi.id
                JOIN products p ON p.id = ii.product_id
                WHERE " . implode(' AND ', $where) . " ORDER BY pi.id DESC, p.name";
        $p = paginate($sql, $params, intv(inp('page', 1)), 30);
        $totQty = 0.0; $totValue = 0.0; $totQtyKg = 0.0;
        foreach ($p['rows'] as &$r) { $r['qty'] = (float)$r['qty']; $r['qty_kg'] = (float)($r['qty_kg'] ?? 0); $r['unit_short'] = strtoupper($r['unit_short'] ?? 'KG'); $r['unit_cost'] = (float)$r['unit_cost']; $r['line_total'] = (float)$r['line_total']; $r['on_hand'] = (float)$r['on_hand']; $r['amount_paid'] = (float)($r['amount_paid'] ?? 0); $r['payment_status'] = $r['payment_status'] ?? ($r['payment_type'] === 'cash' ? 'paid' : 'unpaid'); $totQty += $r['qty']; $totQtyKg += ($r['qty_kg'] > 0.0009 ? $r['qty_kg'] : UnitConverter::toKg($r['qty'], $r['unit_short'])); $totValue += $r['line_total']; }
        unset($r);
        $invoiceTotals = db_one("SELECT COUNT(*) AS invoices, COALESCE(SUM(pi.total),0) AS total_value FROM purchase_invoices pi WHERE " . implode(' AND ', ["pi.date >= ?", "pi.date <= ?", "pi.status = 'posted'"]), [$from, $to]);
        view('purchase/raw', ['p' => $p, 'q' => $q, 'from' => $from, 'to' => $to, 'status' => $status, 'totQty' => $totQty, 'totQtyKg' => $totQtyKg, 'totValue' => $totValue, 'invoiceTotals' => $invoiceTotals, 'title' => 'Raw Material Purchases']);
    }

    public static function invoices(): void {
        $q = inp('q'); $from = inp('from', date('Y-m-01')); $to = inp('to', today()); $status = inp('status');
        $where = ['pi.date >= ?', 'pi.date <= ?']; $params = [$from, $to];
        if ($status !== '') { $where[] = 'pi.status = ?'; $params[] = $status; }
        if ($q !== '') { $where[] = '(pi.invoice_no LIKE ? OR s.name LIKE ? OR pi.supplier_invoice_no LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = "SELECT pi.*, s.name AS supplier, w.name AS warehouse FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id JOIN warehouses w ON w.id = pi.warehouse_id WHERE " . implode(' AND ', $where) . " ORDER BY pi.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $totals = db_one("SELECT COALESCE(SUM(pi.total),0) AS t FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id WHERE " . implode(' AND ', $where), $params);
        view('purchase/invoices', ['p' => $p, 'q' => $q, 'from' => $from, 'to' => $to, 'status' => $status, 'totals' => $totals, 'title' => 'Purchase Invoices / GRN']);
    }

    /** Single purchase invoice / GRN — printable detail page. */
    public static function invoiceView(): void {
        $id = intv(inp('id'));
        $inv = db_one("SELECT pi.*, s.name AS supplier, s.code AS supplier_code, s.address AS supplier_address, s.phone AS supplier_phone, s.ntn AS supplier_ntn, w.name AS warehouse FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id JOIN warehouses w ON w.id = pi.warehouse_id WHERE pi.id = ?", [$id]);
        if (!$inv) { http_response_code(404); exit('Purchase invoice not found'); }
        $items = db_all("SELECT pi.*, p.sku, p.name, u.short AS unit FROM purchase_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.invoice_id = ? ORDER BY pi.id", [$id]);
        view('purchase/invoice_view', ['inv' => $inv, 'items' => $items, 'title' => 'Purchase Invoice ' . ($inv['invoice_no'] ?? '')]);
    }

    /** GRN — receive goods, stock in, journal posted */
    public static function invoiceNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $idem = inp('idem_key');
            $supplierId = intv(inp('supplier_id'));
            $warehouseId = intv(inp('warehouse_id'));
            $date = inp('date', today());
            $paymentType = in_array(inp('payment_type'), ['cash', 'credit'], true) ? inp('payment_type') : 'credit';
            $invoiceDiscount = round(num(inp('discount')), 2);
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $costs = $_POST['items']['cost'] ?? [];
            $taxRates = $_POST['items']['tax_rate'] ?? [];
            $bonuses = $_POST['items']['is_bonus'] ?? [];
            $units = $_POST['items']['unit'] ?? [];
            $items = [];
            $subtotal = 0.0; $taxTotal = 0.0;
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($productIds[$i] ?? 0); $q = (float)$q; $cost = round((float)($costs[$i] ?? 0), 2);
                $taxRate = round((float)($taxRates[$i] ?? 0), 2);
                $isBonus = !empty($bonuses[$i]) ? 1 : 0;
                if ($isBonus) { $cost = 0.0; $taxRate = 0.0; } // supplier bonus goods never raise the payable
                if (!$pid || $q <= 0) continue;
                $prodKind = (string)db_val('SELECT kind FROM products WHERE id = ?', [$pid]);
                $unitShort = strtoupper((string)($units[$i] ?? 'KG'));
                if ($prodKind !== 'raw' && $prodKind !== 'packaging') {
                    $unitShort = (string)db_val('SELECT u.short FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.id = ?', [$pid]);
                    $unitShort = $unitShort ?: 'KG';
                } elseif (!in_array($unitShort, ['KG', 'TON', 'MAN'], true)) {
                    $unitShort = 'KG';
                }
                $qtyKg = UnitConverter::toKg($q, $unitShort);
                $net = round($q * $cost, 2);
                $tax = round($net * $taxRate / 100, 2);
                $lineTotal = $net + $tax;
                $subtotal += $net; $taxTotal += $tax;
                $items[] = ['product_id' => $pid, 'qty' => $q, 'unit_short' => $unitShort, 'qty_kg' => $qtyKg, 'cost' => $cost, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'line_total' => $lineTotal, 'is_bonus' => $isBonus];
            }
            if (!$supplierId || !$warehouseId || !$items) { flash('error', __('Supplier, warehouse and at least one item are required.')); redirect('purchase/invoices/new'); }
            $netSubtotal = round($subtotal, 2);
            $total = round($netSubtotal - $invoiceDiscount + $taxTotal, 2);
            if (idempotency_seen('purchase/invoices/new', $idem, (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected — the invoice was already saved.'));
                redirect('purchase/invoices');
            }
            $invoiceId = null;
            $isAdmin = is_owner_or_admin();
            $posted = false;
            try {
                Database::transaction(function () use ($items, $supplierId, $warehouseId, $date, $paymentType, $netSubtotal, $invoiceDiscount, $taxTotal, $total, $isAdmin, &$invoiceId, &$posted) {
                    $tmpNo = 'TMP-' . bin2hex(random_bytes(6));
                    $docStatus = $isAdmin ? 'posted' : 'pending_approval';
                    $invoiceId = db_insert('purchase_invoices', [
                        'invoice_no' => $tmpNo, 'supplier_id' => $supplierId, 'po_id' => intv(inp('po_id')) ?: null,
                        'supplier_invoice_no' => inp('supplier_invoice_no'), 'date' => $date,
                        'payment_type' => $paymentType, 'warehouse_id' => $warehouseId,
                        'subtotal' => $netSubtotal, 'discount' => $invoiceDiscount, 'tax' => $taxTotal, 'total' => $total,
                        'status' => $docStatus, 'payment_status' => $paymentType === 'cash' ? 'paid' : 'unpaid', 'amount_paid' => $paymentType === 'cash' ? $total : 0,
                        'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        db_insert('purchase_items', ['invoice_id' => $invoiceId, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'unit_short' => $it['unit_short'], 'qty_kg' => $it['qty_kg'], 'unit_cost' => $it['cost'], 'discount' => 0, 'tax_rate' => $it['tax_rate'], 'tax_amount' => $it['tax_amount'], 'line_total' => $it['line_total'], 'is_bonus' => $it['is_bonus']]);
                    }
                    if (!$isAdmin) {
                        Approvals::create('purchase_invoice', 'purchase_invoices', (int)$invoiceId, $total, 'Purchase / GRN from ' . db_val('SELECT name FROM suppliers WHERE id = ?', [$supplierId]), (int)($_SESSION['user_id'] ?? 0));
                    } else {
                        if (intv(inp('po_id'))) db_exec("UPDATE purchase_orders SET status = 'received' WHERE id = ? AND status IN ('approved','draft')", [intv(inp('po_id'))]);
                        Posting::postPurchaseInvoice((int)$invoiceId);
                        $posted = true;
                    }
                });
                if ($posted) {
                    flash('success', __('Goods received — stock updated and journal posted.'));
                } else {
                    flash('success', __('Purchase submitted — Owner/Admin must approve it before stock moves or any payable/cash entry posts.'));
                }
                redirect('purchase/invoices');
            } catch (Throwable $e) {
                error_log('Purchase invoice failed: ' . $e->getMessage());
                flash('error', 'Purchase failed: ' . $e->getMessage());
                redirect('purchase/invoices/new');
            }
        }
        $poId = intv(inp('po_id'));
        $po = $poId ? db_one("SELECT * FROM purchase_orders WHERE id = ?", [$poId]) : null;
        $poItems = $po ? db_all('SELECT pi.*, p.sku, p.name, u.short AS unit FROM purchase_order_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.po_id = ?', [$poId]) : [];
        view('purchase/invoice_new', [
            'suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'warehouses' => StockController::warehouseOptions(),
            'products' => json_encode(StockController::productOptions(!empty($_GET['raw']) ? ['raw', 'packaging'] : null)),
            'rawProducts' => db_all("SELECT p.id, p.sku, p.name, p.kind, u.short AS unit_short FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.is_active = 1 AND p.kind IN ('raw','packaging') ORDER BY p.name"),
            'pendingPos' => db_all("SELECT po.id, po.po_no, s.name AS supplier FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.status = 'approved' ORDER BY po.id DESC LIMIT 50"),
            'po' => $po, 'poItems' => $poItems,
            'title' => 'Receive Goods (GRN) / Purchase Invoice']);
    }

    public static function invoiceReverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $reason = inp('reason', 'Purchase reversal');
        try {
            Posting::reversePurchaseInvoice($id, $reason);
            flash('success', __('Purchase reversed — stock removed (if still on hand) and a reversal journal posted.'));
        } catch (Throwable $e) { flash('error', 'Reversal failed: ' . $e->getMessage()); }
        redirect('purchase/invoices');
    }
}
