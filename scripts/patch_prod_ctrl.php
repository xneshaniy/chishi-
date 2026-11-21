<?php
$file = __DIR__ . '/../app/controllers/ProductionController.php';
$content = file_get_contents($file);

// Replace runForm, recordRun, and reverseRun methods
$oldBlock = <<<'PHP'
    /** Daily production entry: one date, multiple products (GET form + POST save with FG stock receipt). */
    public static function runForm(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('production.create')) { http_response_code(403); exit('Forbidden'); }
            self::recordRun();
            return;
        }
        view('production/run', ['products' => db_all("SELECT p.id, p.sku, p.name, p.pack_size, u.short AS unit_short FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.is_active = 1 ORDER BY p.name"),
            'today' => today(), 'title' => __('Daily Production Entry')]);
    }

    private static function recordRun(): void {
        $back = 'production/run';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $notes = trim(inp('notes'));

        $productIds = $_POST['items']['product_id'] ?? [];
        $qtys = $_POST['items']['qty'] ?? [];
        $batches = $_POST['items']['batch_no'] ?? [];
        $lineNotes = $_POST['items']['line_notes'] ?? [];
        $items = [];
        foreach ($productIds as $i => $pid) {
            $pid = (int)$pid;
            // Packets are the base stock unit; store the entered quantity exactly (no conversion).
            $qty = round(num((string)($qtys[$i] ?? '0')), 3);
            $batch = trim((string)($batches[$i] ?? ''));
            if ($pid > 0 && $qty > 0) {
                if (!db_val('SELECT 1 FROM products WHERE id = ? AND is_active = 1', [$pid])) continue;
                $items[] = ['product_id' => $pid, 'qty' => $qty, 'batch_no' => $batch !== '' ? $batch : null, 'line_notes' => trim((string)($lineNotes[$i] ?? '')) ?: null];
            }
        }
        if (!$items) { flash('error', __('Enter at least one product with a produced quantity.')); redirect($back); }
        $whId = self::fgWarehouseId();
        if (!$whId) { flash('error', __('No finished-goods warehouse found â€” create WH-MAIN or a default warehouse first.')); redirect($back); }
        $idem = trim(inp('idem_key')) ?: bin2hex(random_bytes(12));
        try {
            Database::transaction(function () use ($date, $notes, $items, $whId, $userId, $idem, &$runId) {
                if ($idem !== '' && db_val('SELECT 1 FROM idempotency_keys WHERE idem_key = ? AND route = ? AND user_id = ?', [$idem, 'production/run', $userId])) {
                    throw new RuntimeException(__('Duplicate submission detected â€” this entry was already recorded.'));
                }
                if ($idem !== '') db_insert('idempotency_keys', ['idem_key' => $idem, 'route' => 'production/run', 'user_id' => $userId, 'created_at' => now_local()]);
                $runId = db_insert('daily_production', ['run_no' => 'PRD-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)), 'date' => $date, 'notes' => $notes ?: null, 'status' => 'posted', 'created_by' => $userId ?: null, 'created_at' => now_local()]);
                foreach ($items as $it) {
                    db_insert('daily_production_items', ['run_id' => (int)$runId, 'product_id' => (int)$it['product_id'], 'qty' => $it['qty'], 'batch_no' => $it['batch_no'], 'line_notes' => $it['line_notes']]);
                    // Receive finished goods into stock at current weighted-average cost (actual material costing remains in Production Orders)
                    $cost = Inventory::avgCost((int)$it['product_id'], $whId);
                    if ($cost <= 0) $cost = (float)db_val('SELECT purchase_price FROM products WHERE id = ?', [(int)$it['product_id']]);
                    Inventory::receive((int)$it['product_id'], $whId, $it['qty'], $cost, 'production_in', 'daily_production', (int)$runId, $it['batch_no'], null, 'Daily production entry');
                }
                audit('create', 'daily_production', (int)$runId, 'Daily production run: ' . count($items) . ' product(s), ' . number_format(array_sum(array_column($items, 'qty')), 3) . ' Packets');
            });
            flash('success', __('Production entry saved â€” finished-goods stock updated.'));
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
                $items = db_all('SELECT * FROM daily_production_items WHERE run_id = ?', [(int)$id]);
                foreach ($items as $it) {
                    Inventory::issue((int)$it['product_id'], $whId, (float)$it['qty'], 'production_out', 'daily_production_reversal', (int)$id, $it['batch_no'], null, 'Reversed daily production run ' . $run['run_no']);
                }
                audit('reverse', 'daily_production', $id, 'Reversed run ' . $run['run_no'] . ($reason ? ' â€” ' . $reason : ''));
            });
            flash('success', __('Production entry reversed â€” stock adjusted back, audit recorded.'));
        } catch (Throwable $e) {
            error_log('reverse run: ' . $e->getMessage());
            flash('error', $e->getMessage());
        }
        redirect('production');
    }
PHP;

$newBlock = <<<'PHP'
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
PHP;

// Find and replace
$startTag = '    /** Daily production entry: one date, multiple products (GET form + POST save with FG stock receipt). */';
$endTag = '    /** Production reports: daily, monthly, date-range (printable). */';

$startPos = strpos($content, $startTag);
$endPos = strpos($content, $endTag);

if ($startPos !== false && $endPos !== false) {
    $before = substr($content, 0, $startPos);
    $after = substr($content, $endPos);
    $content = $before . $newBlock . "\n\n" . $after;
    file_put_contents($file, $content);
    echo "ProductionController patched successfully\n";
} else {
    echo "Could not find tags in ProductionController. start=" . ($startPos !== false ? 'found' : 'missing') . ", end=" . ($endPos !== false ? 'found' : 'missing') . "\n";
}
