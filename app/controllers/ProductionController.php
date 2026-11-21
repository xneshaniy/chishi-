<?php
final class ProductionController {

    public static function orders(): void {
        $status = inp('status');
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'po.status = ?'; $params[] = $status; }
        $sql = "SELECT po.*, p.name AS product, p.sku, w.name AS warehouse FROM production_orders po JOIN products p ON p.id = po.product_id JOIN warehouses w ON w.id = po.warehouse_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY po.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('production/orders', ['p' => $p, 'status' => $status, 'title' => 'Production Orders']);
    }

    public static function orderNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $productId = intv(inp('product_id'));
            $warehouseId = intv(inp('warehouse_id'));
            $qty = num(inp('qty_planned'));
            $bomId = intv(inp('bom_id')) ?: null;
            if (!$productId || !$warehouseId || $qty <= 0) { flash('error', __('Product, warehouse and planned quantity (>0) are required.')); redirect('production/new'); }
            $autoRelease = inp('auto_release', '0') === '1';
            $orderNo = 'PO-' . date('Ymd') . '-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM production_orders') + 1), 4, '0', STR_PAD_LEFT);
            $id = (int)Database::transaction(function () use ($productId, $warehouseId, $qty, $bomId, $orderNo, $autoRelease) {
                $id = db_insert('production_orders', [
                    'order_no' => $orderNo, 'product_id' => $productId, 'warehouse_id' => $warehouseId, 'bom_id' => $bomId,
                    'qty_planned' => $qty, 'qty_produced' => 0, 'status' => 'draft',
                    'batch_no' => inp('batch_no') ?: null, 'expiry_date' => inp('expiry_date') ?: null,
                    'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                ]);
                // Copy BOM lines into production_inputs (planned consumption)
                if ($bomId) {
                    $bom = db_one('SELECT * FROM bills_of_materials WHERE id = ?', [$bomId]);
                    $scale = $bom && (float)$bom['output_qty'] > 0 ? $qty / (float)$bom['output_qty'] : 1;
                    foreach (db_all('SELECT * FROM bom_lines WHERE bom_id = ?', [$bomId]) as $line) {
                        db_insert('production_inputs', ['production_order_id' => $id, 'product_id' => $line['material_id'], 'qty' => round((float)$line['qty'] * $scale, 3), 'unit_cost' => 0, 'total_cost' => 0]);
                    }
                    // Auto-release so a one-step production entry needs no separate Release click
                    if ($autoRelease) db_exec("UPDATE production_orders SET status = 'released' WHERE id = ? AND (SELECT COUNT(*) FROM production_inputs WHERE production_order_id = ?) > 0", [$id, $id]);
                }
                return $id;
            });
            audit('create', 'production_orders', $id, "Production order $orderNo");
            try {
                Notifications::notifyProductionCreated($id, $orderNo, (string)db_val('SELECT name FROM products WHERE id = ?', [$productId]), $qty);
            } catch (Throwable $e) { error_log('notify production created: ' . $e->getMessage()); }
            flash('success', __('Production order ') . $orderNo . __(' created') . ($autoRelease && $bomId ? __(' and released for production.') : __(' as DRAFT.')));
            redirect($autoRelease && $bomId ? 'production/' . $id . '/complete' : 'production/orders');
        }
        view('production/order_new', [
            'products' => db_all("SELECT id, sku, name FROM products WHERE kind = 'finished' AND is_active = 1 ORDER BY name"),
            'boms' => db_all("SELECT b.*, p.name AS product FROM bills_of_materials b JOIN products p ON p.id = b.product_id WHERE b.is_active = 1"),
            'warehouses' => StockController::warehouseOptions(),
            'title' => 'New Production Order']);
    }

    public static function orderRelease(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $o = db_one('SELECT * FROM production_orders WHERE id = ?', [$id]);
        if (!$o || $o['status'] !== 'draft') { flash('error', __('Only draft orders can be released.')); redirect('production/orders'); }
        if (!db_val('SELECT 1 FROM production_inputs WHERE production_order_id = ?', [$id])) { flash('error', __('Order has no material inputs (create a BOM and attach it).')); redirect('production/orders'); }
        db_exec("UPDATE production_orders SET status = 'released' WHERE id = ?", [$id]);
        audit('release', 'production_orders', $id, $o['order_no']);
        flash('success', __('Order released for production.'));
        redirect('production/orders');
    }

    public static function orderCancel(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $o = db_one('SELECT * FROM production_orders WHERE id = ?', [$id]);
        if (!$o || $o['status'] === 'completed') { flash('error', __('Only draft/released orders can be cancelled.')); redirect('production/orders'); }
        db_exec("UPDATE production_orders SET status = 'cancelled' WHERE id = ?", [$id]);
        audit('cancel', 'production_orders', $id, $o['order_no']);
        redirect('production/orders');
    }

    public static function orderComplete(): void {
        $id = intv(inp('id'));
        $o = db_one('SELECT * FROM production_orders WHERE id = ?', [$id]);
        if (!$o) { flash('error', __('Order not found.')); redirect('production/orders'); }
        if ($o['status'] !== 'released') { flash('error', __('Only released orders can be completed.')); redirect('production/orders'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $qtyProduced = num(inp('qty_produced'));
            $wasteQty = num(inp('waste_qty'));
            $laborCost = round(num(inp('labor_cost')), 2);
            $overheadCost = round(num(inp('overhead_cost')), 2);
            if ($qtyProduced <= 0) { flash('error', __('Produced quantity must be positive.')); redirect('production/' . $id . '/complete'); }
            $notes = inp('notes');
            try {
                Database::transaction(function () use ($o, $qtyProduced, $wasteQty, $laborCost, $overheadCost, $id, $notes) {
                    $consumption = ProductionService::absorbBatchConsumption($id, $o['batch_no'] ?? null);
                    if ($consumption !== null) {
                        $materialsCost = $consumption['materials_cost'];
                    } else {
                        // 1) Consume planned BOM materials (at current WA/FIFO cost)
                        $materialsCost = ProductionService::consumePlannedInputs($id, (int)$o['warehouse_id'], $o['order_no']);
                    }
                    $totalCost = round($materialsCost + $laborCost + $overheadCost, 2);
                    $unitCost = $qtyProduced > 0 ? round($totalCost / $qtyProduced, 4) : 0;
                    // 2) Receive finished goods at total cost
                    Inventory::receive((int)$o['product_id'], (int)$o['warehouse_id'], $qtyProduced, $unitCost,
                        'production_in', 'production_order', $id, $o['batch_no'], $o['expiry_date'], 'Produced per ' . $o['order_no']);
                    // 3) If waste was recorded, remove waste qty from inputs proportionally as loss (documented on order)
                    db_exec("UPDATE production_orders SET status = 'completed', qty_produced = ?, waste_qty = ?, labor_cost = ?, overhead_cost = ?, materials_cost = ?, total_cost = ?, completed_at = ?, notes = ? WHERE id = ?",
                        [$qtyProduced, $wasteQty, $laborCost, $overheadCost, $materialsCost, $totalCost, now_local(), $notes ?: null, $id]);
                    audit('complete', 'production_orders', $id, "Produced $qtyProduced Packets; cost Rs " . number_format($totalCost, 2));
                });
                try {
                    $prodName = (string)db_val('SELECT name FROM products WHERE id = ?', [(int)$o['product_id']]);
                    Notifications::notifyProductionCompleted($id, (string)$o['order_no'], $prodName, $qtyProduced);
                } catch (Throwable $e) { error_log('notify production completed: ' . $e->getMessage()); }
                flash('success', __('Production completed â€” finished goods received into stock at actual cost.'));
                redirect('production/orders');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('production/' . $id . '/complete');
            }
        }
        $inputs = db_all('SELECT pi.*, p.sku, p.name, u.short AS unit_short FROM production_inputs pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.production_order_id = ?', [$id]);
        $product = db_one('SELECT p.* FROM products p WHERE p.id = ?', [(int)$o['product_id']]);
        view('production/complete', ['o' => $o, 'inputs' => $inputs, 'product' => $product, 'title' => 'Complete Production â€” ' . $o['order_no']]);
    }

    public static function boms(): void {
        $rows = db_all("SELECT b.*, p.name AS product, p.sku, (SELECT COUNT(*) FROM bom_lines bl WHERE bl.bom_id = b.id) AS line_count FROM bills_of_materials b JOIN products p ON p.id = b.product_id ORDER BY b.id DESC");
        view('production/boms', ['rows' => $rows, 'title' => 'Bills of Materials']);
    }

    public static function bomNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $productId = intv(inp('product_id')); $name = inp('name'); $outputQty = num(inp('output_qty', 1));
            $materialIds = $_POST['items']['material_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            if (!$productId || $name === '' || $outputQty <= 0) { flash('error', __('Product, name and output qty are required.')); redirect('production/boms/new'); }
            $lines = [];
            foreach ($materialIds as $i => $mid) {
                $mid = (int)$mid; $q = (float)($qtys[$i] ?? 0);
                if ($mid && $q > 0) $lines[] = ['material_id' => $mid, 'qty' => $q];
            }
            if (!$lines) { flash('error', __('Add at least one material line.')); redirect('production/boms/new'); }
            try {
                Database::transaction(function () use ($productId, $name, $outputQty, $lines) {
                    $bomId = db_insert('bills_of_materials', ['product_id' => $productId, 'name' => $name, 'output_qty' => $outputQty, 'is_active' => 1]);
                    foreach ($lines as $l) db_insert('bom_lines', ['bom_id' => $bomId, 'material_id' => $l['material_id'], 'qty' => $l['qty']]);
                    audit('create', 'boms', $bomId, $name);
                });
                flash('success', __('Bill of materials saved.'));
                redirect('production/boms');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('production/boms/new'); }
        }
        view('production/bom_new', [
            'products' => db_all("SELECT id, sku, name FROM products WHERE kind = 'finished' AND is_active = 1 ORDER BY name"),
            'materials' => db_all("SELECT id, sku, name FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 ORDER BY name"),
            'title' => 'New BOM / Recipe']);
    }

    /* ============ PRODUCTION SECTION â€” product cards, daily entries, reports ============ */

    private static function fgWarehouseId(): int {
        return (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1") ?: (int)db_val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1');
    }

    /** Combined production totals per product id (completed production orders + posted daily runs). */
    private static function producedTotalMap(): array {
        $map = [];
        foreach (db_all("SELECT product_id, COALESCE(SUM(qty_produced),0) AS qty FROM production_orders WHERE status = 'completed' GROUP BY product_id") as $r) {
            $map[(int)$r['product_id']] = (float)$r['qty'];
        }
        foreach (db_all("SELECT i.product_id, COALESCE(SUM(i.qty),0) AS qty FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE r.status = 'posted' GROUP BY i.product_id") as $r) {
            $pid = (int)$r['product_id'];
            $map[$pid] = ($map[$pid] ?? 0.0) + (float)$r['qty'];
        }
        return $map;
    }

    /** Production summary for the landing page cards. */
    private static function productionStats(): array {
        $t = today();
        $monthStart = date('Y-m-01');
        $ordersToday = (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE status = 'completed' AND DATE(completed_at) = ?", [$t]);
        $runsToday = (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE r.status = 'posted' AND r.date = ?", [$t]);
        $ordersMonth = (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE status = 'completed' AND completed_at >= ?", [$monthStart]);
        $runsMonth = (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE r.status = 'posted' AND r.date >= ?", [$monthStart]);
        $totalAll = (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE status = 'completed'")
            + (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE r.status = 'posted'");
        return [
            'today' => round($ordersToday + $runsToday, 3),
            'month' => round($ordersMonth + $runsMonth, 3),
            'totalProducts' => (int)db_val("SELECT COUNT(*) FROM products WHERE is_active = 1"),
            'total' => round($totalAll, 3),
        ];
    }

    /** Production landing: summary cards + product card grid (search / kind filter). */
    public static function cards(): void {
        $q = trim(inp('q'));
        $kind = inp('kind', 'finished');
        if (!in_array($kind, ['finished', 'raw', 'packaging', 'all'], true)) $kind = 'finished';
        $where = 'WHERE p.is_active = 1';
        $params = [];
        if ($kind !== 'all') { $where .= ' AND p.kind = ?'; $params[] = $kind; }
        if ($q !== '') { $where .= ' AND (p.sku LIKE ? OR p.name LIKE ?)'; $like = '%' . $q . '%'; $params[] = $like; $params[] = $like; }
        $products = db_all("SELECT p.*, c.name AS category, u.short AS unit_short,
            COALESCE((SELECT SUM(qty) FROM stock_balances sb WHERE sb.product_id = p.id),0) AS on_hand
            FROM products p LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN units u ON u.id = p.unit_id
            $where ORDER BY p.name", $params);
        $totals = self::producedTotalMap();
        foreach ($products as &$p) { $p['produced_total'] = $totals[(int)$p['id']] ?? 0.0; }
        $stats = self::productionStats();
        $counts = [];
        foreach (db_all("SELECT kind, COUNT(*) AS n FROM products WHERE is_active = 1 GROUP BY kind") as $r) $counts[$r['kind']] = (int)$r['n'];
        view('production/cards', ['products' => $products, 'q' => $q, 'kind' => $kind, 'stats' => $stats, 'counts' => $counts,
            'today' => today(), 'title' => __('Production')]);
    }

    /** Product production history: daily + monthly totals, combined orders + daily entries, filterable. */
    public static function productDetail(): void {
        $id = intv(inp('id'));
        $product = $id ? db_one('SELECT p.*, c.name AS category, u.short AS unit_short FROM products p LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN units u ON u.id = p.unit_id WHERE p.id = ?', [$id]) : null;
        if (!$product) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : null;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', '')) ? inp('to') : null;
        $month = preg_match('/^\d{4}-\d{2}$/', inp('month', '')) ? inp('month') : null;
        if ($month) { $from = $month . '-01'; $to = date('Y-m-t', strtotime($from)); }
        if (!$from || !$to) { $to = today(); $from = date('Y-m-d', strtotime('-30 days')); }

        $dailyRows = db_all("SELECT r.date AS row_date, i.qty, i.batch_no, i.line_notes, r.run_no, 'daily' AS source, r.id AS run_id, r.status,
                COALESCE(u.full_name, u.username) AS recorded_by
            FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id LEFT JOIN users u ON u.id = r.created_by
            WHERE i.product_id = ? AND r.status = 'posted' AND r.date BETWEEN ? AND ?
            ORDER BY r.date DESC, i.id DESC", [(int)$id, $from, $to]);
        $orderRows = db_all("SELECT DATE(o.completed_at) AS row_date, o.qty_produced AS qty, o.batch_no, o.notes AS line_notes, o.order_no AS run_no, 'order' AS source, o.id AS run_id, o.status,
                COALESCE(u.full_name, u.username) AS recorded_by
            FROM production_orders o LEFT JOIN users u ON u.id = o.created_by
            WHERE o.product_id = ? AND o.status = 'completed' AND DATE(o.completed_at) BETWEEN ? AND ?
            ORDER BY o.completed_at DESC", [(int)$id, $from, $to]);
        $history = array_merge($dailyRows, $orderRows);
        usort($history, fn($a, $b) => strcmp((string)$b['row_date'], (string)$a['row_date']));
        $todayQty = (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE i.product_id = ? AND r.status = 'posted' AND r.date = ?", [(int)$id, today()])
            + (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE product_id = ? AND status = 'completed' AND DATE(completed_at) = ?", [(int)$id, today()]);
        $monthQty = (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE i.product_id = ? AND r.status = 'posted' AND r.date >= ?", [(int)$id, date('Y-m-01')])
            + (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE product_id = ? AND status = 'completed' AND completed_at >= ?", [(int)$id, date('Y-m-01')]);
        $overallQty = (float)db_val("SELECT COALESCE(SUM(i.qty),0) FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id WHERE i.product_id = ? AND r.status = 'posted'", [(int)$id])
            + (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE product_id = ? AND status = 'completed'", [(int)$id]);
        $rangeQty = array_sum(array_column($history, 'qty'));
        $onHand = (float)db_val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ?', [(int)$id]);
        $reversed = db_all("SELECT r.run_no, r.date, i.qty, r.reversal_reason, COALESCE(u.full_name, u.username) AS reversed_by FROM daily_production r JOIN daily_production_items i ON i.run_id = r.id LEFT JOIN users u ON u.id = r.reversed_by WHERE i.product_id = ? AND r.status = 'reversed' ORDER BY r.reversed_at DESC, r.id DESC LIMIT 10", [(int)$id]);
        view('production/product', ['product' => $product, 'id' => (int)$id, 'history' => $history, 'from' => $from, 'to' => $to,
            'todayQty' => $todayQty, 'monthQty' => $monthQty, 'overallQty' => $overallQty, 'rangeQty' => $rangeQty, 'onHand' => $onHand,
            'reversed' => $reversed, 'title' => 'Production â€” ' . $product['name']]);
    }

    /** Daily production entry: one date, multiple products + automatic RM deduction via BOM. */
    public static function runForm(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('production.create')) { http_response_code(403); exit('Forbidden'); }
            self::recordRun();
            return;
        }

        $products = db_all("SELECT p.id, p.sku, p.name, p.pack_size, u.short AS unit_short
                            FROM products p
                            LEFT JOIN units u ON u.id = p.unit_id
                            WHERE p.is_active = 1 AND p.kind = 'finished'
                            ORDER BY p.name");
        $rawMaterials = db_all("SELECT p.id, p.sku, p.name, p.kind
                                FROM products p
                                WHERE p.is_active = 1 AND p.kind IN ('raw','packaging')
                                ORDER BY p.name");
        $warehouses = RawMaterial::consumptionWarehouses();
        $defaultWh = RawMaterial::defaultWarehouse();
        $boms = db_all("SELECT b.id AS bom_id, b.product_id, b.output_qty, bl.material_id, bl.qty AS line_qty,
                               p.name AS material_name, p.sku AS material_sku
                        FROM bills_of_materials b
                        JOIN bom_lines bl ON bl.bom_id = b.id
                        JOIN products p ON p.id = bl.material_id
                        WHERE b.is_active = 1");

        view('production/run', [
            'products' => $products,
            'rawMaterials' => $rawMaterials,
            'warehouses' => $warehouses,
            'defaultWh' => $defaultWh,
            'boms' => $boms,
            'today' => today(),
            'title' => __('Daily Production Entry')
        ]);
    }

    private static function recordRun(): void {
        $back = 'production/run';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $date = inp('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : today();
        $notes = trim(inp('notes'));

        $productIds = $_POST['items']['product_id'] ?? [];
        $qtys = $_POST['items']['qty'] ?? [];
        $batches = $_POST['items']['batch_no'] ?? [];
        $lineNotes = $_POST['items']['line_notes'] ?? [];
        $items = [];
        foreach ($productIds as $i => $pid) {
            $pid = (int)$pid;
            $qty = round(num((string)($qtys[$i] ?? '0')), 3);
            $batch = trim((string)($batches[$i] ?? ''));
            if ($pid > 0 && $qty > 0) {
                if (!db_val('SELECT 1 FROM products WHERE id = ? AND is_active = 1', [$pid])) continue;
                $items[] = [
                    'product_id' => $pid,
                    'qty' => $qty,
                    'batch_no' => $batch !== '' ? $batch : null,
                    'line_notes' => trim((string)($lineNotes[$i] ?? '')) ?: null
                ];
            }
        }
        if (!$items) { flash('error', __('Enter at least one product with a produced quantity.')); redirect($back); }

        $whId = self::fgWarehouseId();
        if (!$whId) { flash('error', __('No finished-goods warehouse found.')); redirect($back); }

        // Raw Material consumption (from form or auto-calculated from BOM)
        $autoDeductBom = !empty($_POST['auto_deduct_bom']) || isset($_POST['auto_deduct']);
        $rmProductIds = $_POST['rm_items']['product_id'] ?? [];
        $rmQtys = $_POST['rm_items']['qty_kg'] ?? [];
        $rmWhs = $_POST['rm_items']['warehouse_id'] ?? [];
        $rmNotes = $_POST['rm_items']['note'] ?? [];

        $rmConsumptions = [];
        foreach ($rmProductIds as $j => $rmPid) {
            $rmPid = (int)$rmPid;
            $rmQty = round(num((string)($rmQtys[$j] ?? '0')), 3);
            $rmWh = (int)($rmWhs[$j] ?? 0) ?: RawMaterial::defaultWarehouse();
            $rmNote = trim((string)($rmNotes[$j] ?? ''));
            if ($rmPid > 0 && $rmQty > 0) {
                $rmConsumptions[] = [
                    'product_id' => $rmPid,
                    'qty_kg' => $rmQty,
                    'warehouse_id' => $rmWh,
                    'note' => $rmNote
                ];
            }
        }

        // Auto-deduct from BOM if no explicit lines or auto-deduct is set
        if (empty($rmConsumptions) && $autoDeductBom) {
            $defaultRmWh = RawMaterial::defaultWarehouse();
            foreach ($items as $it) {
                $bom = db_one("SELECT * FROM bills_of_materials WHERE product_id = ? AND is_active = 1 LIMIT 1", [(int)$it['product_id']]);
                if ($bom) {
                    $bomLines = db_all("SELECT * FROM bom_lines WHERE bom_id = ?", [(int)$bom['id']]);
                    $ratio = (float)$bom['output_qty'] > 0 ? ((float)$it['qty'] / (float)$bom['output_qty']) : (float)$it['qty'];
                    $pName = (string)db_val("SELECT name FROM products WHERE id = ?", [(int)$it['product_id']]);
                    foreach ($bomLines as $bl) {
                        $rmNeeded = round((float)$bl['qty'] * $ratio, 3);
                        if ($rmNeeded > 0) {
                            $rmConsumptions[] = [
                                'product_id' => (int)$bl['material_id'],
                                'qty_kg' => $rmNeeded,
                                'warehouse_id' => $defaultRmWh,
                                'note' => 'BOM auto-deduct for ' . (float)$it['qty'] . ' ' . $pName
                            ];
                        }
                    }
                }
            }
        }

        $idem = trim(inp('idem_key')) ?: bin2hex(random_bytes(12));
        try {
            $runNo = 'PRD-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            Database::transaction(function () use ($date, $notes, $items, $rmConsumptions, $whId, $userId, $idem, $runNo, &$runId) {
                if ($idem !== '' && db_val('SELECT 1 FROM idempotency_keys WHERE idem_key = ? AND route = ? AND user_id = ?', [$idem, 'production/run', $userId])) {
                    throw new RuntimeException(__('Duplicate submission detected.'));
                }
                if ($idem !== '') db_insert('idempotency_keys', ['idem_key' => $idem, 'route' => 'production/run', 'user_id' => $userId, 'created_at' => now_local()]);

                $runId = db_insert('daily_production', [
                    'run_no' => $runNo,
                    'date' => $date,
                    'notes' => $notes ?: null,
                    'status' => 'posted',
                    'created_by' => $userId ?: null,
                    'created_at' => now_local()
                ]);

                // 1. Receive Finished Goods into stock
                foreach ($items as $it) {
                    db_insert('daily_production_items', [
                        'run_id' => (int)$runId,
                        'product_id' => (int)$it['product_id'],
                        'qty' => $it['qty'],
                        'batch_no' => $it['batch_no'],
                        'line_notes' => $it['line_notes']
                    ]);
                    $cost = Inventory::avgCost((int)$it['product_id'], $whId);
                    if ($cost <= 0) $cost = (float)db_val('SELECT purchase_price FROM products WHERE id = ?', [(int)$it['product_id']]);
                    Inventory::receive((int)$it['product_id'], $whId, $it['qty'], $cost, 'production_in', 'daily_production', (int)$runId, $it['batch_no'], null, 'Daily production entry');
                }

                // 2. Deduct Raw Materials from stock + record in rm_daily_usage
                $totalRmDeductedKg = 0.0;
                foreach ($rmConsumptions as $rm) {
                    db_insert('daily_production_rm_items', [
                        'run_id' => (int)$runId,
                        'product_id' => (int)$rm['product_id'],
                        'warehouse_id' => (int)$rm['warehouse_id'],
                        'qty_kg' => (float)$rm['qty_kg'],
                        'note' => $rm['note'] ?: null
                    ]);

                    // Deduct from inventory stock
                    Inventory::issue((int)$rm['product_id'], (int)$rm['warehouse_id'], (float)$rm['qty_kg'], 'production_raw_deduct', 'daily_production', (int)$runId, null, null, 'Consumed for Daily Production #' . $runNo);

                    // Insert into rm_daily_usage with production_run_id link
                    db_insert('rm_daily_usage', [
                        'usage_date' => $date,
                        'product_id' => (int)$rm['product_id'],
                        'warehouse_id' => (int)$rm['warehouse_id'],
                        'qty_kg' => (float)$rm['qty_kg'],
                        'note' => $rm['note'] ?: ('Auto-deducted for Production #' . $runNo),
                        'status' => 'posted',
                        'recorded_by' => $userId ?: null,
                        'production_run_id' => (int)$runId,
                        'created_at' => now_local()
                    ]);

                    $totalRmDeductedKg += (float)$rm['qty_kg'];
                }

                audit('create', 'daily_production', (int)$runId, 'Daily production run: ' . count($items) . ' product(s), ' . number_format(array_sum(array_column($items, 'qty')), 3) . ' Packets; RM Deducted: ' . number_format($totalRmDeductedKg, 3) . ' KG');
            });

            flash('success', __('پروڈکشن محفوظ ہو گئی — تیار مال اسٹاک میں شامل اور را مٹیریل کا اسٹاک خود بخود کٹ گیا!'));
        } catch (Throwable $e) {
            error_log('daily production: ' . $e->getMessage());
            flash('error', $e->getMessage());
        }
        redirect($back);
    }

    /** Void a posted daily entry (Owner/Admin only). Issues the goods back out; audited, exactly once. */
    public static function reverseRun(): void {
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $reason = trim(inp('reason'));
        $run = $id ? db_one('SELECT * FROM daily_production WHERE id = ?', [$id]) : null;
        if (!$run) { flash('error', __('Production entry not found.')); redirect('production'); }
        if ($run['status'] !== 'posted') { flash('error', __('Only posted entries can be reversed.')); redirect('production'); }
        $whId = self::fgWarehouseId();
        try {
            Database::transaction(function () use ($run, $id, $reason, $whId) {
                db_exec("UPDATE daily_production SET status = 'reversed', reversed_at = ?, reversed_by = ?, reversal_reason = ? WHERE id = ? AND status = 'posted'", [now_local(), (int)($_SESSION['user_id'] ?? 0) ?: null, $reason ?: 'Reversed by admin', $id]);

                // 1. Issue Finished Goods back out
                $items = db_all('SELECT * FROM daily_production_items WHERE run_id = ?', [(int)$id]);
                foreach ($items as $it) {
                    Inventory::issue((int)$it['product_id'], $whId, (float)$it['qty'], 'production_out', 'daily_production_reversal', (int)$id, $it['batch_no'], null, 'Reversed daily production run ' . $run['run_no']);
                }

                // 2. Return Raw Materials back to stock
                $rmItems = db_all('SELECT * FROM daily_production_rm_items WHERE run_id = ?', [(int)$id]);
                foreach ($rmItems as $rm) {
                    $cost = Inventory::avgCost((int)$rm['product_id'], (int)$rm['warehouse_id']);
                    Inventory::receive((int)$rm['product_id'], (int)$rm['warehouse_id'], (float)$rm['qty_kg'], $cost, 'production_raw_reversal', 'daily_production_reversal', (int)$id, null, null, 'Reversal of Production Run #' . $run['run_no']);
                }

                // 3. Mark rm_daily_usage as cancelled
                db_exec("UPDATE rm_daily_usage SET status = 'cancelled', cancel_reason = ? WHERE production_run_id = ? AND status = 'posted'", [
                    'Reversed with Production Run #' . $run['run_no'] . ($reason ? ' — ' . $reason : ''),
                    $id
                ]);

                audit('reverse', 'daily_production', $id, 'Reversed run ' . $run['run_no'] . ($reason ? ' — ' . $reason : ''));
            });
            flash('success', __('پروڈکشن انٹری کینسل — تیار مال اور را مٹیریل دونوں کا اسٹاک بحال کر دیا گیا۔'));
        } catch (Throwable $e) {
            error_log('reverse run: ' . $e->getMessage());
            flash('error', $e->getMessage());
        }
        redirect('production');
    }

    /** Production reports: daily, monthly, date-range (printable). */
    public static function report(): void {
        $mode = inp('mode', 'range');
        if (!in_array($mode, ['day', 'month', 'range'], true)) $mode = 'range';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('date', '')) ? inp('date') : today();
        $month = preg_match('/^\d{4}-\d{2}$/', inp('month', '')) ? inp('month') : date('Y-m');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', '')) ? inp('to') : today();
        $productId = intv(inp('product_id'));
        if ($mode === 'day') { $from = $date; $to = $date; }
        elseif ($mode === 'month') { $from = $month . '-01'; $to = date('Y-m-t', strtotime($from)); }

        $params = [$from, $to];
        $where = "r.date BETWEEN ? AND ? AND r.status = 'posted'";
        if ($productId) { $where .= ' AND i.product_id = ?'; $params[] = $productId; }
        $rows = db_all("SELECT r.date AS row_date, p.id AS product_id, p.sku, p.name, p.kind, c.name AS category, u.short AS unit_short,
                i.qty, i.batch_no, r.run_no, COALESCE(uu.full_name, uu.username) AS recorded_by
            FROM daily_production_items i JOIN daily_production r ON r.id = i.run_id JOIN products p ON p.id = i.product_id
            LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN users uu ON uu.id = r.created_by
            WHERE $where ORDER BY r.date DESC, p.name", $params);
        $orderRows = db_all("SELECT DATE(o.completed_at) AS row_date, p.id AS product_id, p.sku, p.name, p.kind, c.name AS category, u.short AS unit_short,
                o.qty_produced AS qty, o.batch_no, o.order_no AS run_no, COALESCE(uu.full_name, uu.username) AS recorded_by
            FROM production_orders o JOIN products p ON p.id = o.product_id LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN users uu ON uu.id = o.created_by
            WHERE o.status = 'completed' AND DATE(o.completed_at) BETWEEN ? AND ?" . ($productId ? " AND o.product_id = ?" : "") . " ORDER BY o.completed_at DESC", $productId ? [$from, $to, $productId] : [$from, $to]);
        $rows = array_merge($rows, $orderRows);
        usort($rows, fn($a, $b) => strcmp((string)$b['row_date'], (string)$a['row_date']));

        $byProduct = [];
        foreach ($rows as $r) {
            $k = (int)$r['product_id'];
            if (!isset($byProduct[$k])) $byProduct[$k] = ['name' => $r['name'], 'sku' => $r['sku'], 'kind' => $r['kind'], 'unit_short' => $r['unit_short'], 'qty' => 0.0, 'entries' => 0];
            $byProduct[$k]['qty'] += (float)$r['qty'];
            $byProduct[$k]['entries']++;
        }
        usort($byProduct, fn($a, $b) => $b['qty'] <=> $a['qty']);
        $grandTotal = array_sum(array_column($rows, 'qty'));
        view('production/report', ['mode' => $mode, 'date' => $date, 'month' => $month, 'from' => $from, 'to' => $to,
            'productId' => $productId, 'rows' => $rows, 'byProduct' => $byProduct, 'grandTotal' => $grandTotal,
            'products' => db_all("SELECT id, sku, name FROM products WHERE is_active = 1 ORDER BY name"),
            'title' => 'Production Report']);
    }
}
