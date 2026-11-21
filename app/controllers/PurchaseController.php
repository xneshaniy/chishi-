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
            // Manager cannot raise raw-material purchase requests at all
            if (!is_owner_or_admin()) {
                $kinds = db_all('SELECT id, kind FROM products WHERE id IN (' . implode(',', array_fill(0, count($items), '?')) . ')', array_column($items, 'product_id'));
                foreach ($kinds as $k) {
                    if (in_array($k['kind'], ['raw', 'packaging'], true)) {
                        flash('error', __('Raw material purchases are Owner/Admin-only.'));
                        redirect('purchase/orders/new');
                    }
                }
            }
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
        if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can approve purchase orders.')); redirect('purchase/orders'); }
        // Approvals engine is the source of truth; direct approve flips a pending request (idempotent, exactly-once handled in Posting)
        $req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'purchase_orders' AND reference_id = ? AND status = 'pending'", [$id]);
        if ($req) {
            try { Approvals::decide((int)$req['id'], true, 'Approved from purchase orders', (int)($_SESSION['user_id'] ?? 0)); flash('success', __('Purchase approved.')); }
            catch (Throwable $e) { flash('error', $e->getMessage()); }
        } else {
            flash('error', __('No pending approval request for this order.'));
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

    public static function rawWarehouse(): void {
        $_GET['tab'] = 'warehouse';
        self::rawMaterials();
    }

    public static function rawSuppliers(): void {
        $_GET['tab'] = 'suppliers';
        self::rawMaterials();
    }

    /** Raw Material Purchases — only raw/packaging purchases, with supplier, qty, rate, total, payment status and remaining stock. */
    public static function rawMaterials(): void {
        // Raw material ledger (quantities AND money) is Owner/Admin-only
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden — raw material purchases are Owner/Admin only.'); }

        // Handle POST actions for Suppliers & Products tab
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action');
            if ($action === 'add_supplier') {
                $name = trim(inp('name'));
                $phone = trim(inp('phone'));
                $city = trim(inp('city'));
                if ($name !== '') {
                    $code = 'SUP-' . str_pad((string)((int)db_val("SELECT MAX(id) FROM suppliers") + 1), 4, '0', STR_PAD_LEFT);
                    $newSupId = db_insert('suppliers', [
                        'code' => $code, 'name' => $name, 'phone' => $phone, 'city' => $city, 'is_active' => 1,
                        'opening_balance' => 0, 'opening_type' => 'credit'
                    ]);
                    $assignedProds = $_POST['product_ids'] ?? [];
                    foreach ($assignedProds as $pid) {
                        if ((int)$pid > 0) {
                            db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$newSupId, (int)$pid]);
                        }
                    }
                    flash('success', __('Supplier added successfully with assigned products.'));
                }
            } elseif ($action === 'map_products') {
                $supId = intv(inp('supplier_id'));
                if ($supId > 0) {
                    db_exec("DELETE FROM supplier_products WHERE supplier_id = ?", [$supId]);
                    $assignedProds = $_POST['product_ids'] ?? [];
                    foreach ($assignedProds as $pid) {
                        if ((int)$pid > 0) {
                            db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$supId, (int)$pid]);
                        }
                    }
                    flash('success', __('Supplier products updated successfully.'));
                }
            } elseif ($action === 'add_product_to_supplier') {
                $supId = intv(inp('supplier_id'));
                $assignedProds = $_POST['product_ids'] ?? [];
                if (!empty($_POST['product_id'])) $assignedProds[] = $_POST['product_id'];
                $count = 0;
                foreach ($assignedProds as $pid) {
                    if ((int)$pid > 0 && $supId > 0) {
                        db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$supId, (int)$pid]);
                        $count++;
                    }
                }
                flash('success', __('Products added to supplier successfully.'));
            } elseif ($action === 'remove_supplier_product') {
                $supId = intv(inp('supplier_id'));
                $pid = intv(inp('product_id'));
                if ($supId > 0 && $pid > 0) {
                    db_exec("DELETE FROM supplier_products WHERE supplier_id = ? AND product_id = ?", [$supId, $pid]);
                    flash('success', __('Product removed from supplier successfully.'));
                }
            } elseif ($action === 'delete_supplier') {
                $supId = intv(inp('supplier_id'));
                if ($supId > 0) {
                    $hasInvoices = (int)db_val("SELECT COUNT(*) FROM purchase_invoices WHERE supplier_id = ?", [$supId]);
                    db_exec("DELETE FROM supplier_products WHERE supplier_id = ?", [$supId]);
                    if ($hasInvoices > 0) {
                        // Soft delete to protect existing financial ledgers and purchase history
                        db_exec("UPDATE suppliers SET is_active = 0 WHERE id = ?", [$supId]);
                        flash('success', __('Supplier deactivated and removed from active list (historical purchases preserved).'));
                    } else {
                        // Hard delete if no invoices exist
                        db_exec("DELETE FROM suppliers WHERE id = ?", [$supId]);
                        flash('success', __('Supplier deleted successfully.'));
                    }
                }
            } elseif ($action === 'add_raw_product') {
                $name = trim(inp('product_name'));
                if ($name !== '') {
                    $sku = 'RM-' . str_pad((string)((int)db_val("SELECT MAX(id) FROM products") + 1), 4, '0', STR_PAD_LEFT);
                    $unitId = (int)db_val("SELECT id FROM units WHERE short = 'KG' LIMIT 1") ?: 1;
                    $newPid = db_insert('products', [
                        'sku' => $sku, 'name' => $name, 'kind' => 'raw', 'unit_id' => $unitId, 'is_active' => 1,
                        'purchase_price' => 0, 'sale_price' => 0, 'issue_rate' => 0, 'reorder_level' => 0
                    ]);
                    $supIds = $_POST['supplier_ids'] ?? [];
                    if (!empty($_POST['supplier_id'])) $supIds[] = $_POST['supplier_id'];
                    foreach ($supIds as $sid) {
                        if ((int)$sid > 0) {
                            db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [(int)$sid, $newPid]);
                        }
                    }
                    flash('success', __('Raw material product created successfully.'));
                }
            }
            redirect('purchase/raw', ['tab' => 'suppliers']);

        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array(inp('action'), ['add_daily_usage', 'cancel_daily_usage'], true)) {
            // ── Daily Usage POST (from raw.php daily-usage tab) ──────────────
            require_post(); csrf_verify();
            $duAction = inp('action');
            if ($duAction === 'add_daily_usage') {
                if (!Auth::can('rm.usage')) { flash('error', __('No permission to record daily usage.')); redirect('purchase/raw', ['tab' => 'daily-usage']); }
                $productId   = intv(inp('product_id'));
                $qty         = num(inp('qty_kg'));
                $usageDate   = RawMaterial::validDate(inp('usage_date', '')) ? inp('usage_date') : today();
                $note        = trim(inp('note'));
                $warehouseId = intv(inp('warehouse_id')) ?: RawMaterial::defaultWarehouse();
                if ($productId <= 0) { flash('error', __('Select the raw material.')); redirect('purchase/raw', ['tab' => 'daily-usage', 'usage_date' => $usageDate]); }
                try {
                    [$ok, $msg] = RawMaterial::recordUsage($usageDate, $productId, $warehouseId, $qty, $note, (int)($_SESSION['user_id'] ?? 0));
                    if ($ok) flash('success', __('Daily usage saved — stock updated.'));
                    else flash('error', $msg);
                } catch (Throwable $e) { flash('error', $e->getMessage()); }
                redirect('purchase/raw', ['tab' => 'daily-usage', 'usage_date' => $usageDate]);

            } elseif ($duAction === 'cancel_daily_usage') {
                if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can cancel usage entries.')); redirect('purchase/raw', ['tab' => 'daily-usage']); }
                $id     = intv(inp('usage_id'));
                $reason = trim(inp('reason'));
                if ($reason === '') { flash('error', __('A reason is required to cancel.')); redirect('purchase/raw', ['tab' => 'daily-usage']); }
                try {
                    RawMaterial::cancelUsage($id, (int)($_SESSION['user_id'] ?? 0), $reason);
                    flash('success', __('Usage cancelled — stock returned.'));
                } catch (Throwable $e) { flash('error', $e->getMessage()); }
                redirect('purchase/raw', ['tab' => 'daily-usage']);
            }
        }

        $from   = inp('from', date('Y-m-01'));
        $to     = inp('to', today());
        $q      = inp('q');
        $tab    = in_array(inp('tab'), ['purchases', 'warehouse', 'summary', 'suppliers', 'daily-usage'], true) ? inp('tab') : 'purchases';
        $tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));

        // Build WHERE (no status filter — keep it simple)
        $where  = ["pi.date >= ?", "pi.date <= ?", "p.kind IN ('raw','packaging')", "pi.status NOT IN ('rejected','reversed')"];
        $params = [$from, $to];
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR s.name LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $sql = "SELECT pi.id, pi.date, pi.payment_type, pi.payment_status, pi.amount_paid, pi.status,
                       s.name AS supplier,
                       p.name AS product, p.kind,
                       ii.qty, ii.unit_short, ii.qty_kg, ii.unit_cost, ii.line_total
                FROM purchase_invoices pi
                JOIN suppliers s ON s.id = pi.supplier_id
                JOIN purchase_items ii ON ii.invoice_id = pi.id
                JOIN products p ON p.id = ii.product_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY pi.date DESC, p.name";

        // Paginated rows for Purchases tab
        $p = paginate($sql, $params, intv(inp('page', 1)), 50);

        // All rows for Summary tab (no pagination)
        $allRows = db_all($sql, $params);

        // Compute totals from all rows
        $totQtyKg = 0.0; $totValue = 0.0; $totPaid = 0.0;
        foreach ($allRows as &$r) {
            $r['qty']        = (float)$r['qty'];
            $r['qty_kg']     = (float)($r['qty_kg'] ?? 0);
            $r['unit_short'] = strtoupper($r['unit_short'] ?? 'KG');
            $r['line_total'] = (float)$r['line_total'];
            $r['amount_paid']= (float)($r['amount_paid'] ?? 0);
            $kg = $r['qty_kg'] > 0.0009 ? $r['qty_kg'] : UnitConverter::toKg($r['qty'], $r['unit_short']);
            $totQtyKg += $kg;
            $totValue += $r['line_total'];
            $totPaid  += $r['amount_paid'];
        }
        unset($r);

        // Also fix up paginated rows
        foreach ($p['rows'] as &$r) {
            $r['qty']        = (float)$r['qty'];
            $r['qty_kg']     = (float)($r['qty_kg'] ?? 0);
            $r['unit_short'] = strtoupper($r['unit_short'] ?? 'KG');
            $r['line_total'] = (float)$r['line_total'];
            $r['amount_paid']= (float)($r['amount_paid'] ?? 0);
        }
        unset($r);

        // Warehouse stock for raw materials (Tab: warehouse)
        $rawStockRows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level,
                                       COALESCE(x.qty,0) AS qty,
                                       COALESCE(x.value,0) AS value,
                                       (SELECT COALESCE(SUM(COALESCE(ii.qty_kg, ii.qty)),0) FROM purchase_items ii JOIN purchase_invoices i ON i.id = ii.invoice_id WHERE ii.product_id = p.id AND i.status = 'posted') AS purchased,
                                       ((SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fii.product_id = p.id AND fi.status = 'posted') + (SELECT COALESCE(SUM(rdu.qty_kg),0) FROM rm_daily_usage rdu WHERE rdu.product_id = p.id AND rdu.status = 'posted')) AS used
                                FROM products p
                                LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                                           FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type != 'salesman'
                                           GROUP BY sb.product_id) x ON x.product_id = p.id
                                WHERE p.kind IN ('raw','packaging') AND p.is_active = 1
                                ORDER BY p.name");
        $totStockQtyKg = 0.0; $totStockValue = 0.0;
        foreach ($rawStockRows as &$sr) {
            $sr['qty'] = (float)$sr['qty'];
            $sr['value'] = (float)$sr['value'];
            $sr['purchased'] = (float)$sr['purchased'];
            $sr['used'] = (float)$sr['used'];
            $totStockQtyKg += $sr['qty'];
            $totStockValue += $sr['value'];
        }
        unset($sr);

        // Suppliers & associated products (Tab: suppliers)
        $suppliersList = db_all("SELECT s.*,
                                       COUNT(DISTINCT sp.product_id) AS prod_count,
                                       GROUP_CONCAT(p.name, '||') AS product_names,
                                       GROUP_CONCAT(p.id, ',') AS product_ids
                                FROM suppliers s
                                LEFT JOIN supplier_products sp ON sp.supplier_id = s.id
                                LEFT JOIN products p ON p.id = sp.product_id AND p.is_active = 1
                                WHERE s.is_active = 1
                                GROUP BY s.id
                                ORDER BY s.name");
        $rawProductsAll = db_all("SELECT id, sku, name, kind FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 ORDER BY name");

        // ── Daily Usage data (Tab: daily-usage) ─────────────────────────────
        $usageDate       = RawMaterial::validDate(inp('usage_date', '')) ? inp('usage_date') : today();
        $usageFrom       = RawMaterial::validDate(inp('usage_from', '')) ? inp('usage_from') : date('Y-m-01');
        $usageTo         = RawMaterial::validDate(inp('usage_to', ''))   ? inp('usage_to')   : today();
        $usageMat        = intv(inp('usage_mat'));

        $dailyEntries = db_all("SELECT rdu.*, p.name AS product_name, p.sku, w.name AS warehouse_name,
                                       u.username AS recorded_by_username
                                FROM rm_daily_usage rdu
                                JOIN products p ON p.id = rdu.product_id
                                LEFT JOIN warehouses w ON w.id = rdu.warehouse_id
                                LEFT JOIN users u ON u.id = rdu.recorded_by
                                WHERE rdu.usage_date = ?
                                ORDER BY rdu.id DESC", [$usageDate]);

        $rptWhere  = ["rdu.usage_date BETWEEN ? AND ?"]; $rptParams = [$usageFrom, $usageTo];
        if ($usageMat > 0) { $rptWhere[] = "rdu.product_id = ?"; $rptParams[] = $usageMat; }
        $dailySummary = db_all("SELECT rdu.usage_date, p.name AS product_name, p.sku,
                                       COALESCE(SUM(CASE WHEN rdu.status='posted' THEN rdu.qty_kg ELSE 0 END),0) AS total_kg,
                                       COUNT(*) AS entries
                                FROM rm_daily_usage rdu
                                JOIN products p ON p.id = rdu.product_id
                                WHERE " . implode(' AND ', $rptWhere) . "
                                GROUP BY rdu.usage_date, rdu.product_id
                                ORDER BY rdu.usage_date DESC, total_kg DESC", $rptParams);

        $usageProducts   = db_all("SELECT p.id, p.sku, p.name FROM products p WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 AND COALESCE(p.is_archived,0)=0 ORDER BY p.name");
        $usageWarehouses = RawMaterial::consumptionWarehouses();
        $defaultUsageWh  = RawMaterial::defaultWarehouse();

        $todayTotalKg = 0.0; $todayEntryCount = 0;
        foreach ($dailyEntries as $de) {
            if ($de['status'] === 'posted') { $todayTotalKg += (float)$de['qty_kg']; }
            $todayEntryCount++;
        }
        // Current-month total usage
        $monthTotalKg = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE status='posted' AND usage_date >= ? AND usage_date <= ?", [date('Y-m-01'), date('Y-m-t')]);

        view('purchase/raw', [
            'p'             => $p,
            'allRows'       => $allRows,
            'rawStockRows'  => $rawStockRows,
            'totStockQtyKg' => $totStockQtyKg,
            'totStockValue' => $totStockValue,
            'suppliersList' => $suppliersList,
            'rawProductsAll'=> $rawProductsAll,
            'q'             => $q,
            'from'          => $from,
            'to'            => $to,
            'tab'           => $tab,
            'totQtyKg'      => $totQtyKg,
            'totValue'      => $totValue,
            'totPaid'       => $totPaid,
            'tonUnit'       => $tonUnit,
            // daily-usage tab
            'usageDate'      => $usageDate,
            'usageFrom'      => $usageFrom,
            'usageTo'        => $usageTo,
            'usageMat'       => $usageMat,
            'dailyEntries'   => $dailyEntries,
            'dailySummary'   => $dailySummary,
            'usageProducts'  => $usageProducts,
            'usageWarehouses'=> $usageWarehouses,
            'defaultUsageWh' => $defaultUsageWh,
            'todayTotalKg'   => $todayTotalKg,
            'todayEntryCount'=> $todayEntryCount,
            'monthTotalKg'   => $monthTotalKg,
            'title'          => 'Raw Material Purchases',
        ]);
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
        // Manager must not see raw-material financial details on the invoice page
        if (!is_owner_or_admin()) {
            $hasRaw = db_val("SELECT COUNT(*) FROM purchase_items ii JOIN products p ON p.id = ii.product_id WHERE ii.invoice_id = ? AND p.kind IN ('raw','packaging')", [(int)$inv['id']]);
            if ($hasRaw) { http_response_code(403); exit('Forbidden — purchase invoice contains raw materials (Owner/Admin only).'); }
        }
        $items = db_all("SELECT pi.*, p.sku, p.name, u.short AS unit FROM purchase_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.invoice_id = ? ORDER BY pi.id", [$id]);
        view('purchase/invoice_view', ['inv' => $inv, 'items' => $items, 'title' => 'Purchase Invoice ' . ($inv['invoice_no'] ?? '')]);
    }

    /** GRN — receive goods, stock in, journal posted */
    public static function invoiceNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $idem = inp('idem_key');
            $isRaw = !empty($_POST['is_raw']) || !empty($_GET['raw']);
            $supplierId = intv(inp('supplier_id'));
            if (!$supplierId) {
                $supplierId = (int)db_val("SELECT id FROM suppliers WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            }
            $warehouseId = intv(inp('warehouse_id'));
            if (!$warehouseId) {
                $warehouseId = (int)db_val("SELECT id FROM warehouses WHERE type <> 'salesman' ORDER BY id ASC LIMIT 1");
            }
            $date = inp('date', today());
            $invoiceDiscount = round(num(inp('discount')), 2);
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $costs = $_POST['items']['cost'] ?? [];
            $lineTotals = $_POST['items']['line_total'] ?? [];
            $taxRates = $_POST['items']['tax_rate'] ?? [];
            $bonuses = $_POST['items']['is_bonus'] ?? [];
            $units = $_POST['items']['unit'] ?? [];
            $items = [];
            $subtotal = 0.0; $taxTotal = 0.0;
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($productIds[$i] ?? 0); $q = (float)$q; $cost = round((float)($costs[$i] ?? 0), 2);
                if ($cost <= 0 && !empty($lineTotals[$i]) && $q > 0) {
                    $cost = round((float)$lineTotals[$i] / $q, 2);
                }
                $taxRate = round((float)($taxRates[$i] ?? 0), 2);
                $isBonus = !empty($bonuses[$i]) ? 1 : 0;
                if ($isBonus) { $cost = 0.0; $taxRate = 0.0; } // supplier bonus goods never raise the payable
                if (!$pid || $q <= 0) continue;
                $prodKind = (string)db_val('SELECT kind FROM products WHERE id = ?', [$pid]);
                // Manager cannot purchase raw materials at all — not even for approval
                if (!is_owner_or_admin() && in_array($prodKind, ['raw', 'packaging'], true)) {
                    flash('error', __('Raw material purchases are Owner/Admin-only.'));
                    redirect($isRaw ? 'purchase/invoices/new?raw=1' : 'purchase/invoices/new');
                }
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
            if (!$supplierId || !$warehouseId || !$items) {
                flash('error', __('Supplier, warehouse and at least one item are required.'));
                redirect($isRaw ? 'purchase/invoices/new?raw=1' : 'purchase/invoices/new');
            }
            $netSubtotal = round($subtotal, 2);
            $total = round($netSubtotal - $invoiceDiscount + $taxTotal, 2);

            $amountPaid = round(num(inp('amount_paid')), 2);
            $cashAccountId = intv(inp('cash_account_id')) ?: (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'cash' AND is_active = 1 LIMIT 1");
            if ($amountPaid >= $total && $total > 0) {
                $paymentType = 'cash';
                $paymentStatus = 'paid';
                $amountPaid = $total;
            } elseif ($amountPaid > 0) {
                $paymentType = 'credit';
                $paymentStatus = 'partial';
            } else {
                $paymentType = 'credit';
                $paymentStatus = 'unpaid';
                $amountPaid = 0.0;
            }

            if (idempotency_seen('purchase/invoices/new', $idem, (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected — the invoice was already saved.'));
                redirect($isRaw ? 'purchase/raw' : 'purchase/invoices');
            }
            $invoiceId = null;
            $isAdmin = is_owner_or_admin();
            $posted = false;
            try {
                Database::transaction(function () use ($items, $supplierId, $warehouseId, $date, $paymentType, $paymentStatus, $amountPaid, $cashAccountId, $netSubtotal, $invoiceDiscount, $taxTotal, $total, $isAdmin, &$invoiceId, &$posted) {
                    $tmpNo = 'TMP-' . bin2hex(random_bytes(6));
                    $docStatus = $isAdmin ? 'posted' : 'pending_approval';
                    $invoiceId = db_insert('purchase_invoices', [
                        'invoice_no' => $tmpNo, 'supplier_id' => $supplierId, 'po_id' => intv(inp('po_id')) ?: null,
                        'supplier_invoice_no' => inp('supplier_invoice_no'), 'date' => $date,
                        'payment_type' => $paymentType, 'cash_account_id' => $cashAccountId, 'warehouse_id' => $warehouseId,
                        'subtotal' => $netSubtotal, 'discount' => $invoiceDiscount, 'tax' => $taxTotal, 'total' => $total,
                        'status' => $docStatus, 'payment_status' => $paymentStatus, 'amount_paid' => $amountPaid,
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
                        // Record supplier partial payment if paid amount was given on credit invoice
                        if ($paymentType === 'credit' && $amountPaid > 0 && $cashAccountId > 0) {
                            $payId = db_insert('payments', [
                                'payment_no' => 'TMP-' . bin2hex(random_bytes(4)),
                                'party_type' => 'supplier',
                                'party_id' => $supplierId,
                                'account_id' => $cashAccountId,
                                'date' => $date,
                                'amount' => $amountPaid,
                                'reference' => 'Invoice ' . $tmpNo,
                                'notes' => 'Raw material purchase payment — Invoice #' . $invoiceId,
                                'status' => 'pending',
                                'created_by' => (int)($_SESSION['user_id'] ?? 0),
                                'created_at' => now_local(),
                            ]);
                            Posting::postPayment((int)$payId);
                        }
                    }
                });
                if ($posted) {
                    flash('success', __('Goods received — stock updated and journal posted.'));
                } else {
                    flash('success', __('Purchase submitted — Owner/Admin must approve it before stock moves or any payable/cash entry posts.'));
                }
                redirect($isRaw ? 'purchase/raw' : 'purchase/invoices');
            } catch (Throwable $e) {
                error_log('Purchase invoice failed: ' . $e->getMessage());
                flash('error', __('Purchase failed: ') . $e->getMessage());
                redirect($isRaw ? 'purchase/invoices/new?raw=1' : 'purchase/invoices/new');
            }
        }
        $poId = intv(inp('po_id'));
        $po = $poId ? db_one("SELECT * FROM purchase_orders WHERE id = ?", [$poId]) : null;
        $poItems = $po ? db_all('SELECT pi.*, p.sku, p.name, u.short AS unit FROM purchase_order_items pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.po_id = ?', [$poId]) : [];
        $suppMapRaw = db_all("SELECT supplier_id, product_id FROM supplier_products");
        $suppMap = [];
        foreach ($suppMapRaw as $smr) {
            $suppMap[(int)$smr['supplier_id']][] = (int)$smr['product_id'];
        }
        view('purchase/invoice_new', [
            'suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'warehouses' => StockController::warehouseOptions(),
            'products' => json_encode(StockController::productOptions(!empty($_GET['raw']) ? ['raw', 'packaging'] : null)),
            'rawProducts' => db_all("SELECT p.id, p.sku, p.name, p.kind, u.short AS unit_short FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.is_active = 1 AND p.kind IN ('raw','packaging') ORDER BY p.name"),
            'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"),
            'supplierProductsMap' => json_encode($suppMap),
            'selectedSupplierId' => intv(inp('supplier_id')),
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
        } catch (Throwable $e) { flash('error', __('Reversal failed: ') . $e->getMessage()); }
        redirect('purchase/invoices');
    }
}
