<?php
final class StockController {

    /** CSV export helper — sends rows as CSV download. */
    public static function sendCsv(string $filename, array $header, array $rows): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 BOM
        fputcsv($out, $header);
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }

    /* ============ STOCK LEDGER (movements) ============ */
    public static function movements(): void {
        $productId = intv(inp('product_id')); $wh = intv(inp('warehouse_id')); $type = inp('type');
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $where = ['m.created_at >= ?', 'm.created_at <= ?']; $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        if ($productId) { $where[] = 'm.product_id = ?'; $params[] = $productId; }
        if ($wh) { $where[] = 'm.warehouse_id = ?'; $params[] = $wh; }
        if ($type !== '') { $where[] = 'm.type = ?'; $params[] = $type; }
        $sql = 'SELECT m.*, p.sku, p.name AS product, p.kind, u.short AS unit_short, w.name AS warehouse FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN units u ON u.id = p.unit_id JOIN warehouses w ON w.id = m.warehouse_id WHERE ' . implode(' AND ', $where) . ' ORDER BY m.id DESC';
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = db_all($sql, $params);
            self::sendCsv('stock_ledger.csv', ['Date','SKU','Product','Warehouse','Type','Qty','Unit','Unit Cost','Total Cost','Balance After','Reference','Batch'],
                array_map(fn($r) => [$r['created_at'], $r['sku'], $r['product'], $r['warehouse'], $r['type'], $r['qty'], ($r['kind'] === 'finished' ? 'Packets' : ($r['unit_short'] ?? '')), $r['unit_cost'], $r['total_cost'], $r['balance_qty'], ($r['ref_type'] ?? '') . ' #' . ($r['ref_id'] ?? ''), $r['batch_no']], $rows));
        }
        $p = paginate($sql, $params, intv(inp('page', 1)), 25);
        view('stock/movements', ['p' => $p, 'filters' => compact('productId', 'wh', 'type', 'from', 'to'),
            'products' => self::productOptions(), 'warehouses' => self::warehouseOptions(),
            'types' => ['opening','purchase','sale','sale_return','sale_reversal','purchase_reversal','adjustment_in','adjustment_out','transfer_in','transfer_out','production_in','production_consumption','damaged','expired','salesman_issue_out','salesman_issue_in'],
            'title' => 'Stock Ledger']);
    }

    /* ============ ADJUSTMENTS ============ */
    public static function adjustments(): void {
        $rows = db_all('SELECT a.*, w.name AS warehouse, u.username AS creator FROM stock_adjustments a JOIN warehouses w ON w.id = a.warehouse_id LEFT JOIN users u ON u.id = a.created_by ORDER BY a.id DESC');
        view('stock/adjustments', ['rows' => $rows, 'title' => 'Stock Adjustments']);
    }

    public static function adjustmentNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $warehouseId = intv(inp('warehouse_id'));
            $reason = inp('reason');
            $productIds = $_POST['items']['product_id'] ?? [];
            $directions = $_POST['items']['direction'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            if (!$warehouseId || $reason === '') { flash('error', __('Warehouse and reason are required.')); redirect('stock/adjustments/new'); }
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $dir = in_array($directions[$i] ?? '', ['in', 'out'], true) ? $directions[$i] : null;
                $q = (float)($qtys[$i] ?? 0);
                if ($pid && $dir && $q > 0) $items[] = ['product_id' => $pid, 'direction' => $dir, 'qty' => $q];
            }
            if (!$items) { flash('error', __('Add at least one item line.')); redirect('stock/adjustments/new'); }
            Database::transaction(function () use ($warehouseId, $reason, $items) {
                $id = db_insert('stock_adjustments', [
                    'adjustment_no' => 'ADJ-' . date('YmdHis'),
                    'warehouse_id' => $warehouseId, 'date' => inp('date', today()),
                    'reason' => $reason, 'status' => 'pending',
                    'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                ]);
                foreach ($items as $it) {
                    $cost = Inventory::avgCost($it['product_id'], $warehouseId);
                    db_insert('stock_adjustment_items', ['adjustment_id' => $id, 'product_id' => $it['product_id'], 'direction' => $it['direction'], 'qty' => $it['qty'], 'unit_cost' => $cost]);
                }
                audit('create', 'stock_adjustments', $id, "Adjustment pending approval — $reason");
            });
            flash('success', __('Adjustment created — pending approval. Stock moves only after approval.'));
            redirect('stock/adjustments');
        }
        view('stock/adjustment_new', ['warehouses' => self::warehouseOptions(), 'products' => json_encode(self::productOptions()), 'title' => 'New Stock Adjustment']);
    }

    public static function adjustmentApprove(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $action = inp('action');
        $adj = db_one('SELECT * FROM stock_adjustments WHERE id = ?', [$id]);
        if (!$adj) { flash('error', __('Adjustment not found.')); redirect('stock/adjustments'); }
        if ($adj['status'] !== 'pending') { flash('error', __('Adjustment already processed.')); redirect('stock/adjustments'); }
        Database::transaction(function () use ($adj, $action, $id) {
            if ($action === 'reject') {
                db_exec("UPDATE stock_adjustments SET status = 'rejected', approved_by = ? WHERE id = ?", [$_SESSION['user_id'] ?? null, $id]);
                audit('update', 'stock_adjustments', $id, 'Rejected');
                return;
            }
            $items = db_all('SELECT * FROM stock_adjustment_items WHERE adjustment_id = ?', [$id]);
            $netDebit = 0.0; $netCredit = 0.0;
            foreach ($items as $it) {
                $prod = db_one('SELECT id, name FROM products WHERE id = ?', [(int)$it['product_id']]);
                $cost = (float)$it['unit_cost'];
                if ($it['direction'] === 'in') {
                    Inventory::receive((int)$it['product_id'], (int)$adj['warehouse_id'], (float)$it['qty'], $cost, 'adjustment_in', 'stock_adjustment', $id, null, null, 'Adjustment ' . $adj['reason']);
                    $netDebit += (float)$it['qty'] * $cost;
                } else {
                    $used = Inventory::issue((int)$it['product_id'], (int)$adj['warehouse_id'], (float)$it['qty'], 'adjustment_out', 'stock_adjustment', $id, null, null, 'Adjustment ' . $adj['reason']);
                    $netCredit += (float)$it['qty'] * $used;
                }
            }
            $lines = [];
            if ($netDebit > 0) $lines[] = ['account' => 'INVENTORY', 'debit' => round($netDebit, 2), 'memo' => 'Stock adjustment in'];
            if ($netDebit > 0) $lines[] = ['account' => 'OTHER_INCOME', 'credit' => round($netDebit, 2), 'memo' => 'Inventory surplus / gain'];
            if ($netCredit > 0) $lines[] = ['account' => 'OTHER_EXP', 'debit' => round($netCredit, 2), 'memo' => 'Stock adjustment out'];
            if ($netCredit > 0) $lines[] = ['account' => 'INVENTORY', 'credit' => round($netCredit, 2), 'memo' => 'Stock adjustment out'];
            if ($lines) {
                Accounting::postEntry($lines, ['date' => today(), 'voucher_type' => 'ADJ', 'reference_type' => 'stock_adjustment', 'reference_id' => $id, 'memo' => 'Stock adjustment ' . $adj['adjustment_no'], 'idem_key' => 'stock_adjustment:' . $id]);
            }
            db_exec("UPDATE stock_adjustments SET status = 'approved', approved_by = ? WHERE id = ?", [$_SESSION['user_id'] ?? null, $id]);
            audit('approve', 'stock_adjustments', $id, 'Approved & applied');
        });
        flash('success', __('Adjustment processed.'));
        redirect('stock/adjustments');
    }

    /* ============ TRANSFERS ============ */
    public static function transferNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $from = intv(inp('from_warehouse_id')); $to = intv(inp('to_warehouse_id'));
            if (!$from || !$to || $from === $to) { flash('error', __('Choose two different warehouses.')); redirect('stock/transfers'); }
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $done = 0;
            Database::transaction(function () use ($from, $to, $productIds, $qtys, &$done) {
                foreach ($productIds as $i => $pid) {
                    $pid = (int)$pid; $q = (float)($qtys[$i] ?? 0);
                    if ($pid && $q > 0) {
                        Inventory::transfer($pid, $from, $to, $q, 'transfer_out', 'transfer_in', 'stock_transfer', null, null, null, 'Warehouse transfer');
                        $done++;
                    }
                }
            });
            audit('transfer', 'stock_movements', null, "Transferred $done items from warehouse $from to $to");
            flash('success', $done . __(' item(s) transferred.'));
            redirect('stock/movements');
        }
        view('stock/transfer', ['warehouses' => self::warehouseOptions(), 'products' => json_encode(self::productOptions()), 'title' => 'Stock Transfer']);
    }

    /* ============ OPENING STOCK ============ */
    public static function openingNew(): void {
        if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can record opening stock.')); redirect('inventory/raw'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $warehouseId = intv(inp('warehouse_id'));
            if (!$warehouseId) { flash('error', __('Warehouse required.')); redirect('stock/opening'); }
            $productIds = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty'] ?? [];
            $costs = $_POST['items']['cost'] ?? [];
            $total = 0.0; $count = 0;
            Database::transaction(function () use ($warehouseId, $productIds, $qtys, $costs, &$total, &$count) {
                foreach ($productIds as $i => $pid) {
                    $pid = (int)$pid; $q = (float)($qtys[$i] ?? 0); $c = (float)($costs[$i] ?? 0);
                    if ($pid && $q > 0) {
                        Inventory::receive($pid, $warehouseId, $q, $c, 'opening', 'opening_stock', null, null, null, 'Opening stock');
                        $total += $q * $c; $count++;
                    }
                }
                if ($total > 0) {
                    Accounting::postEntry([
                        ['account' => 'INVENTORY', 'debit' => round($total, 2), 'memo' => 'Opening stock value'],
                        ['account' => 'CAPITAL', 'credit' => round($total, 2), 'memo' => 'Opening balance — owner capital'],
                    ], ['date' => today(), 'voucher_type' => 'OB', 'reference_type' => 'opening_stock', 'reference_id' => null, 'memo' => 'Opening stock — ' . $count . ' products', 'idem_key' => 'opening_stock:' . bin2hex(random_bytes(8))]);
                }
            });
            audit('create', 'opening_stock', null, "Opening stock: $count products, value " . number_format($total, 2));
            flash('success', __('Opening stock posted - ') . $count . __(' products, value Rs ') . number_format($total, 2) . __(' (Dr Inventory / Cr Capital).'));
            redirect('stock/movements');
        }
        view('stock/opening', ['warehouses' => self::warehouseOptions(), 'products' => json_encode(self::productOptions()), 'title' => 'Opening Stock']);
    }

    /* ============ PHYSICAL STOCK COUNT ============ */
    public static function counts(): void {
        $wh = intv(inp('warehouse_id'));
        $products = [];
        if ($wh) {
            $products = db_all("SELECT p.id, p.sku, p.name, p.reorder_level, COALESCE(b.qty,0) AS system_qty, COALESCE(b.avg_cost,0) AS avg_cost
                FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ?
                WHERE p.is_active = 1 ORDER BY p.name", [$wh]);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['counted'])) {
            require_post(); csrf_verify();
            $warehouseId = intv(inp('warehouse_id'));
            $counted = $_POST['counted'] ?? [];
            $reason = 'Physical stock count ' . date('Y-m-d');
            $items = [];
            foreach (db_all("SELECT p.id, COALESCE(b.qty,0) AS system_qty, COALESCE(b.avg_cost,0) AS avg_cost FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ? WHERE p.is_active = 1", [$warehouseId]) as $row) {
                $c = $counted[$row['id']] ?? null;
                if ($c === null || $c === '') continue;
                $diff = round((float)$c - (float)$row['system_qty'], 3);
                if (abs($diff) < 0.0009) continue;
                $items[] = ['product_id' => (int)$row['id'], 'direction' => $diff > 0 ? 'in' : 'out', 'qty' => abs($diff)];
            }
            if (!$items) { flash('info', __('No variances found — stock matches system.')); redirect('stock/counts?warehouse_id=' . $warehouseId); }
            Database::transaction(function () use ($warehouseId, $reason, $items) {
                $id = db_insert('stock_adjustments', ['adjustment_no' => 'CNT-' . date('YmdHis'), 'warehouse_id' => $warehouseId, 'date' => today(), 'reason' => $reason, 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                foreach ($items as $it) {
                    $cost = Inventory::avgCost($it['product_id'], $warehouseId);
                    db_insert('stock_adjustment_items', ['adjustment_id' => $id, 'product_id' => $it['product_id'], 'direction' => $it['direction'], 'qty' => $it['qty'], 'unit_cost' => $cost]);
                }
            });
            flash('success', __('Stock count recorded — an adjustment (') . count($items) . __(' variances) is pending approval.'));
            redirect('stock/adjustments');
        }
        view('stock/counts', ['wh' => $wh, 'products' => $products, 'warehouses' => self::warehouseOptions(), 'title' => 'Physical Stock Count']);
    }

    /* ============ helpers ============ */
    public static function productOptions(?array $kinds = null): array {
        $sql = "SELECT p.id, p.sku, p.name, p.sale_price, p.purchase_price, p.issue_rate, p.min_price, p.tax_rate, p.kind, u.short AS unit_short
            FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.is_active = 1";
        $params = [];
        if ($kinds) {
            $marks = implode(',', array_fill(0, count($kinds), '?'));
            $sql .= " AND p.kind IN ($marks)";
            $params = array_values($kinds);
        }
        $sql .= " ORDER BY p.name";
        $rows = db_all($sql, $params);
        return array_map(fn($r) => ['id' => (int)$r['id'], 'sku' => $r['sku'], 'name' => $r['name'], 'sale_price' => (float)$r['sale_price'], 'purchase_price' => (float)$r['purchase_price'], 'issue_rate' => (float)$r['issue_rate'], 'min_price' => (float)$r['min_price'], 'tax_rate' => (float)$r['tax_rate'], 'kind' => $r['kind'], 'unit_short' => $r['unit_short']], $rows);
    }

    /** Company (non-salesman-van) warehouses for standard dropdowns — vans are never valid company locations. */
    public static function warehouseOptions(): array {
        return db_all("SELECT id, code, name, type FROM warehouses WHERE is_active = 1 AND type != 'salesman' ORDER BY CASE type WHEN 'central' THEN 0 WHEN 'factory' THEN 1 ELSE 2 END, name");
    }

    /* ============ Raw Material Stock (kind = raw|packaging) ============ */
    /** On-hand (company warehouses only — salesman vans excluded), purchased (all time), consumed in production (incl. today), value, reorder. */
    public static function rawStock(): void {
        $rows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.unit_id, u.short AS unit_short, p.reorder_level,
                       COALESCE(x.qty,0) AS qty,
                       COALESCE(x.value,0) AS value,
                       (SELECT COALESCE(SUM(COALESCE(ii.qty_kg, ii.qty)),0) FROM purchase_items ii JOIN purchase_invoices i ON i.id = ii.invoice_id WHERE ii.product_id = p.id AND i.status = 'posted') AS purchased,
                       ((SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fii.product_id = p.id AND fi.status = 'posted') + (SELECT COALESCE(SUM(pi2.qty),0) FROM production_inputs pi2 JOIN production_orders po2 ON po2.id = pi2.production_order_id WHERE pi2.product_id = p.id AND po2.status = 'completed' AND (SELECT COUNT(*) FROM factory_issue_items fii2 JOIN factory_issues fi2 ON fi2.id = fii2.issue_id WHERE fi2.status = 'posted' AND fi2.batch_no = po2.batch_no) = 0) + (SELECT COALESCE(SUM(rdu.qty_kg),0) FROM rm_daily_usage rdu WHERE rdu.product_id = p.id AND rdu.status = 'posted')) AS used
                FROM products p LEFT JOIN units u ON u.id = p.unit_id
                LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                           FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'
                           GROUP BY sb.product_id) x ON x.product_id = p.id
                WHERE p.kind IN ('raw','packaging') AND p.is_active = 1
                GROUP BY p.id ORDER BY p.name");
        $today = today();
        $totQty = 0.0; $totValue = 0.0; $low = 0; $totUsed = 0.0;
        foreach ($rows as &$r) {
            $r['qty'] = (float)$r['qty']; $r['value'] = (float)$r['value']; $r['purchased'] = (float)$r['purchased']; $r['used'] = (float)$r['used'];
            $r['used_today'] = (float)db_val("SELECT COALESCE(SUM(fii2.qty_kg),0) FROM factory_issue_items fii2 JOIN factory_issues fi ON fi.id = fii2.issue_id WHERE fii2.product_id = ? AND date(fi.date) = ? AND fi.status = 'posted'", [(int)$r['id'], $today])
                + (float)db_val("SELECT COALESCE(SUM(pi3.qty),0) FROM production_inputs pi3 JOIN production_orders po ON po.id = pi3.production_order_id WHERE pi3.product_id = ? AND po.status = 'completed' AND date(po.completed_at) = ? AND (SELECT COUNT(*) FROM factory_issue_items fii3 JOIN factory_issues fi3 ON fi3.id = fii3.issue_id WHERE fi3.status = 'posted' AND fi3.batch_no = po.batch_no) = 0", [(int)$r['id'], $today])
                + (float)db_val("SELECT COALESCE(SUM(rdu.qty_kg),0) FROM rm_daily_usage rdu WHERE rdu.product_id = ? AND rdu.status = 'posted' AND usage_date = ?", [(int)$r['id'], $today]);
            $totQty += $r['qty']; $totValue += $r['value']; $totUsed += $r['used'];
            if ((float)$r['reorder_level'] > 0 && $r['qty'] <= (float)$r['reorder_level']) $low++;
        }
        unset($r);
        view('inventory/raw_stock', ['rows' => $rows, 'totQty' => $totQty, 'totValue' => $totValue, 'totUsed' => $totUsed, 'lowCount' => $low, 'title' => 'Raw Material Stock']);
    }

    /* ============ Finished Product Stock (kind = finished) ============ */
    /** On-hand, produced, issued today to salesmen, sold today, returned, value, reorder. */
    public static function finishedStock(): void {
        $today = today();
        $rows = db_all("SELECT p.id, p.sku, p.name, p.unit_id, u.short AS unit_short, p.reorder_level,
                       COALESCE(x.qty,0) AS qty,
                       COALESCE(x.value,0) AS value,
                       (SELECT COALESCE(SUM(po.qty_produced),0) FROM production_orders po WHERE po.product_id = p.id AND po.status = 'completed')
                         + (SELECT COALESCE(SUM(dpi.qty),0) FROM daily_production_items dpi JOIN daily_production dr ON dr.id = dpi.run_id WHERE dpi.product_id = p.id AND dr.status = 'posted') AS produced,
                       (SELECT COALESCE(SUM(sii.qty),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE sii.product_id = p.id AND date(i2.date) = ?) AS issued_today,
                       (SELECT COALESCE(SUM(i3.qty),0) FROM sales_invoice_items i3 JOIN sales_invoices inv3 ON inv3.id = i3.invoice_id WHERE i3.product_id = p.id AND inv3.status = 'posted' AND date(inv3.date) = ? AND i3.is_bonus = 0) AS sold_today,
                       (SELECT COALESCE(SUM(sri.qty),0) FROM sales_return_items sri JOIN sales_returns sr ON sr.id = sri.return_id WHERE sri.product_id = p.id AND date(sr.date) = ?) AS returned_today
                FROM products p LEFT JOIN units u ON u.id = p.unit_id
                LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                           FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'
                           GROUP BY sb.product_id) x ON x.product_id = p.id
                WHERE p.kind = 'finished' AND p.is_active = 1
                GROUP BY p.id ORDER BY p.name", [$today, $today, $today]);
        $totQty = 0.0; $totValue = 0.0; $totProduced = 0.0; $low = 0;
        foreach ($rows as &$r) {
            $r['qty'] = (float)$r['qty']; $r['value'] = (float)$r['value']; $r['produced'] = (float)$r['produced'];
            $r['issued_today'] = (float)$r['issued_today']; $r['sold_today'] = (float)$r['sold_today']; $r['returned_today'] = (float)$r['returned_today'];
            $totQty += $r['qty']; $totValue += $r['value']; $totProduced += $r['produced'];
            if ((float)$r['reorder_level'] > 0 && $r['qty'] <= (float)$r['reorder_level']) $low++;
        }
        unset($r);
        view('inventory/finished_stock', ['rows' => $rows, 'totQty' => $totQty, 'totValue' => $totValue, 'totProduced' => $totProduced, 'lowCount' => $low, 'title' => 'Finished Product Stock']);
    }

    /* ============ Dedicated Warehouse Stock View (Raw Materials + Factory Finished Goods) ============ */
    public static function warehouseStock(): void {
        $q = trim(inp('q', ''));
        $tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));

        // 1. Raw Materials Stock (kind IN ('raw', 'packaging'))
        $rawWhere = ["p.kind IN ('raw','packaging')", "p.is_active = 1"];
        $rawParams = [];
        if ($q !== '') {
            $rawWhere[] = "(p.name LIKE ? OR p.sku LIKE ?)";
            $rawParams[] = "%$q%";
            $rawParams[] = "%$q%";
        }
        $rawRows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level, u.short AS unit_short,
                                  COALESCE(x.qty, 0) AS qty,
                                  COALESCE(x.value, 0) AS value
                           FROM products p
                           LEFT JOIN units u ON u.id = p.unit_id
                           LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                                      FROM stock_balances sb
                                      JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'
                                      GROUP BY sb.product_id) x ON x.product_id = p.id
                           WHERE " . implode(' AND ', $rawWhere) . "
                           ORDER BY p.name", $rawParams);

        $totRawKg = 0.0;
        $totRawValue = 0.0;
        foreach ($rawRows as &$rr) {
            $rr['qty'] = (float)$rr['qty'];
            $rr['value'] = (float)$rr['value'];
            $totRawKg += $rr['qty'];
            $totRawValue += $rr['value'];
        }
        unset($rr);

        // 2. Factory Finished Goods Stock (kind = 'finished')
        $fgWhere = ["p.kind = 'finished'", "p.is_active = 1"];
        $fgParams = [];
        if ($q !== '') {
            $fgWhere[] = "(p.name LIKE ? OR p.sku LIKE ?)";
            $fgParams[] = "%$q%";
            $fgParams[] = "%$q%";
        }
        $fgRows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.pack_size, p.reorder_level, p.sale_price, u.short AS unit_short,
                                 COALESCE(x.qty, 0) AS qty,
                                 COALESCE(x.value, 0) AS value,
                                 (SELECT COALESCE(SUM(po.qty_produced), 0) FROM production_orders po WHERE po.product_id = p.id AND po.status = 'completed')
                                   + (SELECT COALESCE(SUM(dpi.qty), 0) FROM daily_production_items dpi JOIN daily_production dr ON dr.id = dpi.run_id WHERE dpi.product_id = p.id AND dr.status = 'posted') AS produced_total
                          FROM products p
                          LEFT JOIN units u ON u.id = p.unit_id
                          LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                                     FROM stock_balances sb
                                     JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'
                                     GROUP BY sb.product_id) x ON x.product_id = p.id
                          WHERE " . implode(' AND ', $fgWhere) . "
                          ORDER BY p.name", $fgParams);

        $totFgQty = 0.0;
        $totFgValue = 0.0;
        foreach ($fgRows as &$fr) {
            $fr['qty'] = (float)$fr['qty'];
            $fr['value'] = (float)$fr['value'];
            $fr['produced_total'] = (float)$fr['produced_total'];
            $totFgQty += $fr['qty'];
            $totFgValue += $fr['value'];
        }
        unset($fr);

        $grandTotalValue = $totRawValue + $totFgValue;

        view('warehouse/index', [
            'rawRows'         => $rawRows,
            'fgRows'          => $fgRows,
            'totRawKg'        => $totRawKg,
            'totRawValue'     => $totRawValue,
            'totFgQty'        => $totFgQty,
            'totFgValue'      => $totFgValue,
            'grandTotalValue' => $grandTotalValue,
            'tonUnit'         => $tonUnit,
            'q'               => $q,
            'title'           => 'Warehouse — گودام اسٹاک',
        ]);
    }

    /* ============ Daily movement reports ============ */
    /** Company-custody filter: stock movements outside salesman van warehouses. */
    private static function companyMovements(): string {
        return "sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman')";
    }

    /** Raw material daily movement. Opening = balance at the start of the date (now/back-calculated); company-custody warehouses only. */
    /* ============ Production Report (daily / batch-wise, costed) ============ */
    public static function productionReport(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', date('Y-m-t')); $q = inp('q');
        $where = ["status = 'completed'", "date(completed_at) BETWEEN ? AND ?"]; $params = [$from, $to];
        if ($q !== '') { $where[] = '(order_no LIKE ? OR batch_no LIKE ? OR notes LIKE ? OR product_id IN (SELECT id FROM products WHERE name LIKE ?))'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
        $sql = "SELECT po.*, p.sku, p.name AS product FROM production_orders po JOIN products p ON p.id = po.product_id
                WHERE " . implode(' AND ', $where) . " ORDER BY po.completed_at DESC, po.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 30);
        $sumQty = 0.0; $sumCost = 0.0; $sumMat = 0.0; $sumOut = 0.0; $totOrders = 0;
        foreach ($p['rows'] as &$r) {
            $r['consumed_kg'] = (float)db_val("SELECT COALESCE(SUM(qty),0) FROM production_inputs WHERE production_order_id = ?", [(int)$r['id']]);
            $r['consumed_value'] = (float)db_val("SELECT COALESCE(SUM(total_cost),0) FROM production_inputs WHERE production_order_id = ?", [(int)$r['id']]);
            $sumQty += (float)$r['qty_produced']; $sumCost += (float)$r['total_cost']; $sumMat += (float)$r['materials_cost']; $sumOut += (float)$r['overhead_cost'] + (float)$r['labor_cost']; $totOrders++;
        }
        unset($r);
        if (($_GET['export'] ?? '') === 'csv') {
            self::sendCsv('production_' . $from . '_' . $to . '.csv',
                ['Order', 'Date', 'Batch', 'Product', 'Produced (Packets)', 'RM Consumed (KG)', 'RM Cost', 'Labor', 'Overhead', 'Total Cost', 'Cost / Packet'],
                array_map(fn($r) => [$r['order_no'], $r['completed_at'], $r['batch_no'], $r['product'], $r['qty_produced'], $r['consumed_kg'], $r['materials_cost'], $r['labor_cost'], $r['overhead_cost'], $r['total_cost'], ((float)$r['qty_produced'] > 0 ? round((float)$r['total_cost'] / (float)$r['qty_produced'], 2) : 0)], $p['rows']));
        }
        view('reports/production', ['p' => $p, 'from' => $from, 'to' => $to, 'q' => $q, 'sumQty' => $sumQty, 'sumCost' => $sumCost, 'sumMat' => $sumMat, 'sumOut' => $sumOut, 'totOrders' => $totOrders, 'title' => 'Production Report']);
    }

    public static function rawDaily(): void {
        $date = inp('date', today());
        $cm = self::companyMovements();
        $rows = db_all("SELECT p.id, p.sku, p.name, u.short AS unit_short,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) < ? AND " . $cm . "), 0) AS opening,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type IN ('purchase','claim_goods','opening')), 0) AS purchased,
                       COALESCE((SELECT -SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type IN ('production_consumption','factory_consumption','rm_usage')), 0) AS consumed,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type IN ('adjustment_in','adjustment_out','transfer_in','transfer_out')), 0) AS adjustments,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm), 0) AS net_today
                FROM products p LEFT JOIN units u ON u.id = p.unit_id
                WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 ORDER BY p.name", [$date, $date, $date, $date, $date]);
        $pur = 0.0; $con = 0.0; $adj = 0.0;
        foreach ($rows as &$r) {
            $r['opening'] = (float)$r['opening']; $r['purchased'] = (float)$r['purchased']; $r['consumed'] = (float)$r['consumed']; $r['adjustments'] = (float)$r['adjustments']; $r['closing'] = $r['opening'] + (float)$r['net_today'];
            $pur += $r['purchased']; $con += $r['consumed']; $adj += $r['adjustments'];
        }
        unset($r);
        // Purchases on the selected day, grouped by the unit they were bought in
        $purchaseByUnit = db_all("SELECT COALESCE(ii.unit_short, 'KG') AS unit_short, COALESCE(SUM(ii.qty),0) AS qty, COALESCE(SUM(ii.line_total),0) AS value, COALESCE(SUM(COALESCE(ii.qty_kg, ii.qty)),0) AS qty_kg
            FROM purchase_items ii JOIN purchase_invoices pi ON pi.id = ii.invoice_id
            WHERE date(pi.date) = ? AND pi.status <> 'reversed' AND ii.product_id IN (SELECT id FROM products WHERE kind IN ('raw','packaging'))
            GROUP BY ii.unit_short ORDER BY unit_short", [$date]);
        if (($_GET['export'] ?? '') === 'csv') {
            self::sendCsv('raw_material_daily_' . $date . '.csv', ['SKU','Product','Opening','Purchases','Consumed','Adjustments','Closing'], array_map(fn($r) => [$r['sku'], $r['name'], $r['opening'], $r['purchased'], $r['consumed'], $r['adjustments'], $r['closing']], $rows));
        }
        view('reports/raw_daily', ['rows' => $rows, 'date' => $date, 'totPurchased' => $pur, 'totConsumed' => $con, 'totAdjustments' => $adj, 'purchaseByUnit' => $purchaseByUnit, 'title' => 'Raw Material Daily Report']);
    }

    public static function fgDaily(): void {
        $date = inp('date', today());
        $cm = self::companyMovements();
        $rows = db_all("SELECT p.id, p.sku, p.name, u.short AS unit_short,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) < ? AND $cm), 0) AS opening,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type = 'production_in'), 0) AS produced,
                       COALESCE((SELECT -SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type = 'salesman_issue_out'), 0) AS issued_to_salesmen,
                       COALESCE((SELECT SUM(sii.paid_qty) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE sii.product_id = p.id AND date(i2.date) = ? AND i2.status <> 'reversed'), 0) AS issued_paid,
                       COALESCE((SELECT SUM(sii.bonus_qty) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE sii.product_id = p.id AND date(i2.date) = ? AND i2.status <> 'reversed'), 0) AS issued_bonus,
                       COALESCE((SELECT -SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type = 'sale'), 0) AS sold,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type = 'sale_return'), 0) AS sales_returns,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm AND sm.type IN ('adjustment_in','adjustment_out')), 0) AS adjustments,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND $cm), 0) AS net_today
                FROM products p LEFT JOIN units u ON u.id = p.unit_id
                WHERE p.kind = 'finished' AND p.is_active = 1 ORDER BY p.name", [$date, $date, $date, $date, $date, $date, $date, $date, $date]);
        $totProd = 0.0; $totIss = 0.0; $totIssPaid = 0.0; $totIssBonus = 0.0; $totSold = 0.0; $totRet = 0.0; $totAdj = 0.0;
        foreach ($rows as &$r) {
            $r['opening'] = (float)$r['opening']; $r['produced'] = (float)$r['produced']; $r['issued_to_salesmen'] = (float)$r['issued_to_salesmen']; $r['issued_paid'] = (float)$r['issued_paid']; $r['issued_bonus'] = (float)$r['issued_bonus']; $r['sold'] = (float)$r['sold']; $r['sales_returns'] = (float)$r['sales_returns']; $r['adjustments'] = (float)$r['adjustments']; $r['closing'] = $r['opening'] + (float)$r['net_today'];
            $totProd += $r['produced']; $totIss += $r['issued_to_salesmen']; $totIssPaid += $r['issued_paid']; $totIssBonus += $r['issued_bonus']; $totSold += $r['sold']; $totRet += $r['sales_returns']; $totAdj += $r['adjustments'];
        }
        unset($r);
        if (($_GET['export'] ?? '') === 'csv') {
            self::sendCsv('finished_goods_daily_' . $date . '.csv', ['SKU','Product','Opening (Packets)','Produced (Packets)','Issued to Salesmen (Packets)','Issued Paid (Packets)','Issued Bonus (Packets)','Sold (Packets)','Returns (Packets)','Adjustments (Packets)','Closing (Packets)','Bundle Equivalent'], array_map(fn($r) => [$r['sku'], $r['name'], $r['opening'], $r['produced'], $r['issued_to_salesmen'], $r['issued_paid'], $r['issued_bonus'], $r['sold'], $r['sales_returns'], $r['adjustments'], $r['closing'], round(UnitConverter::packetsToBundles((float)$r['closing']), 2)], $rows));
        }
        view('reports/fg_daily', ['rows' => $rows, 'date' => $date, 'totProduced' => $totProd, 'totIssued' => $totIss, 'totIssuedPaid' => $totIssPaid, 'totIssuedBonus' => $totIssBonus, 'totSold' => $totSold, 'totReturns' => $totRet, 'totAdjustments' => $totAdj, 'title' => 'Finished Product Daily Report']);
    }
}
