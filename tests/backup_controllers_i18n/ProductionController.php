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
            flash('success', "Production order $orderNo created" . ($autoRelease && $bomId ? ' and released for production.' : ' as DRAFT.'));
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
                    audit('complete', 'production_orders', $id, "Produced $qtyProduced units; cost Rs " . number_format($totalCost, 2));
                });
                flash('success', __('Production completed — finished goods received into stock at actual cost.'));
                redirect('production/orders');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('production/' . $id . '/complete');
            }
        }
        $inputs = db_all('SELECT pi.*, p.sku, p.name, u.short AS unit_short FROM production_inputs pi JOIN products p ON p.id = pi.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE pi.production_order_id = ?', [$id]);
        $product = db_one('SELECT p.* FROM products p WHERE p.id = ?', [(int)$o['product_id']]);
        view('production/complete', ['o' => $o, 'inputs' => $inputs, 'product' => $product, 'title' => 'Complete Production — ' . $o['order_no']]);
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
}
