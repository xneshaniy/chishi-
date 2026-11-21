<?php
/**
 * Raw material ledger — purchases/consumption in KG base.
 *  - Purchases arrive via the existing raw-material GRN flow (purchase_items stores unit_short + qty_kg).
 *  - Daily usage entries are written to rm_daily_usage and deduct stock exactly once
 *    (Inventory::issue under ref type 'rm_daily_usage'). Cancelling returns stock exactly once.
 *  - An entry is unique per (product, usage_date, warehouse) — re-saving the same day UPDATES the row
 *    (delta stock adjustment + audit), never inserts a duplicate.
 *  - Manager controls only the daily usage; Owner/Admin controls purchases and cancellations.
 */
final class RawMaterial {
    public static function validDate(string $d): bool {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
    }

    /** Priority warehouse for RM consumption (setting or factory warehouse). */
    public static function defaultWarehouse(): int {
        $pref = (int)setting('rm_warehouse_id', '0');
        if ($pref && db_val('SELECT 1 FROM warehouses WHERE id = ? AND is_active = 1', [$pref])) return $pref;
        $id = (int)db_val("SELECT id FROM warehouses WHERE is_active = 1 AND type IN ('factory','central','warehouse') ORDER BY CASE type WHEN 'factory' THEN 0 WHEN 'central' THEN 1 ELSE 2 END LIMIT 1");
        return $id ?: 1;
    }

    /** Total KG purchased (posted GRNs only), across all warehouses. */
    public static function purchasedKg(int $productId): float {
        return (float)db_val("SELECT COALESCE(SUM(COALESCE(ii.qty_kg, ii.qty)),0) FROM purchase_items ii JOIN purchase_invoices i ON i.id = ii.invoice_id WHERE ii.product_id = ? AND i.status = 'posted'", [$productId]);
    }

    /** Total KG consumed: production factory_issues + unlinked production_inputs + daily usage (all posted). */
    public static function usedKg(int $productId): float {
        $factory = (float)db_val("SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fii.product_id = ? AND fi.status = 'posted'", [$productId]);
        $prodIn = (float)db_val("SELECT COALESCE(SUM(pi.qty),0) FROM production_inputs pi JOIN production_orders po ON po.id = pi.production_order_id WHERE pi.product_id = ? AND po.status = 'completed' AND (SELECT COUNT(*) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted' AND fi.batch_no = po.batch_no) = 0", [$productId]);
        $usage = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE product_id = ? AND status = 'posted'", [$productId]);
        return round($factory + $prodIn + $usage, 3);
    }

    /** Today's consumption (all sources) for a product. */
    public static function usedTodayKg(int $productId): float {
        $today = today();
        $factory = (float)db_val("SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fii.product_id = ? AND fi.status = 'posted' AND DATE(fi.date) = ?", [$productId, $today]);
        $prodIn = (float)db_val("SELECT COALESCE(SUM(pi.qty),0) FROM production_inputs pi JOIN production_orders po ON po.id = pi.production_order_id WHERE pi.product_id = ? AND po.status = 'completed' AND DATE(po.completed_at) = ? AND (SELECT COUNT(*) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted' AND fi.batch_no = po.batch_no) = 0", [$productId, $today]);
        $usage = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE product_id = ? AND status = 'posted' AND usage_date = ?", [$productId, $today]);
        return round($factory + $prodIn + $usage, 3);
    }

    /** Stock on hand across ALL non-salesman warehouses (KG). */
    public static function onHandKg(int $productId): float {
        return (float)db_val("SELECT COALESCE(SUM(qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' WHERE sb.product_id = ?", [$productId]);
    }

    /** Per-warehouse on-hand KG for a product [warehouse_id => qty]. */
    public static function onHandByWarehouse(int $productId): array {
        $out = [];
        foreach (db_all("SELECT sb.warehouse_id AS wh, sb.qty FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' WHERE sb.product_id = ?", [$productId]) as $r) {
            $out[(int)$r['wh']] = (float)$r['qty'];
        }
        return $out;
    }

    /** Full stock matrix: per raw/packaging product — purchased KG, used KG, on hand KG, value. */
    public static function summaryRows(): array {
        $rows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level, u.short AS unit_short,
                       COALESCE((SELECT SUM(qty) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' WHERE sb.product_id = p.id),0) AS qty
                       FROM products p LEFT JOIN units u ON u.id = p.unit_id
                       WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 AND COALESCE(p.is_archived, 0) = 0
                       ORDER BY p.name");
        foreach ($rows as &$r) {
            $pid = (int)$r['id'];
            $r['purchased'] = self::purchasedKg($pid);
            $r['used'] = self::usedKg($pid);
            $r['used_today'] = self::usedTodayKg($pid);
            $r['qty'] = (float)$r['qty'];
            $r['value'] = $r['qty'] * (float)db_val('SELECT COALESCE(AVG(avg_cost),0) FROM stock_balances WHERE product_id = ?', [$pid]);
        }
        return $rows;
    }

    /** Totals for cards. */
    public static function summaryTotals(): array {
        $rows = self::summaryRows();
        $t = ['qty' => 0.0, 'purchased' => 0.0, 'used' => 0.0, 'used_today' => 0.0, 'value' => 0.0, 'low' => 0];
        foreach ($rows as $r) {
            $t['qty'] += $r['qty']; $t['purchased'] += $r['purchased']; $t['used'] += $r['used'];
            $t['used_today'] += $r['used_today']; $t['value'] += $r['value'];
            if ((float)$r['reorder_level'] > 0 && $r['qty'] <= (float)$r['reorder_level']) $t['low']++;
        }
        return $t;
    }

    /** Eligible warehouses for RM consumption (active, non-salesman), with balance for a product. */
    public static function consumptionWarehouses(?int $productId = null): array {
        $rows = db_all("SELECT id, code, name, type FROM warehouses WHERE is_active = 1 AND type <> 'salesman' ORDER BY CASE type WHEN 'factory' THEN 0 WHEN 'central' THEN 1 ELSE 2 END, name");
        if ($productId) {
            $balByWh = self::onHandByWarehouse($productId);
            foreach ($rows as &$r) $r['balance'] = (float)($balByWh[(int)$r['id']] ?? 0.0);
        } else {
            foreach ($rows as &$r) $r['balance'] = null;
        }
        return $rows;
    }

    /**
     * Record (or re-record) daily usage for a product. Upsert semantics:
     * existing active row for (product, date, warehouse) is adjusted by the delta, with audit.
     * Returns [true, 'saved'] or [false, 'reason'].
     */
    public static function recordUsage(string $date, int $productId, int $warehouseId, float $qtyKg, string $note, int $userId): array {
        $date = self::validDate($date) ? $date : today();
        $product = db_one('SELECT id, name, kind FROM products WHERE id = ? AND COALESCE(is_archived, 0) = 0', [$productId]);
        if (!$product) return [false, __('Raw material product not found.')];
        if (!in_array($product['kind'], ['raw', 'packaging'], true)) return [false, __('Only raw material / packaging products can be consumed daily.')];
        $wh = db_one('SELECT id FROM warehouses WHERE id = ? AND is_active = 1 AND type <> \'salesman\'', [$warehouseId]);
        if (!$wh) return [false, __('Select a valid warehouse.')];
        if (!is_finite($qtyKg) || $qtyKg < 0.001) return [false, __('Quantity used must be at least 0.001 KG.')];
        $qtyKg = round($qtyKg, 3);
        $note = trim($note);

        return Database::transaction(function () use ($date, $productId, $warehouseId, $qtyKg, $note, $userId, $product) {
            $existing = db_one('SELECT * FROM rm_daily_usage WHERE product_id = ? AND usage_date = ? AND warehouse_id = ?', [$productId, $date, $warehouseId]);
            $onHand = (float)db_val('SELECT COALESCE(qty,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
            $avg = (float)db_val('SELECT COALESCE(avg_cost,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);

            if (!$existing) {
                if ($onHand + 0.0009 < $qtyKg) {
                    throw new RuntimeException(sprintf(__('Insufficient stock: %s — %.3f KG available in the selected warehouse.'), $product['name'], $onHand));
                }
                $id = db_insert('rm_daily_usage', ['usage_date' => $date, 'product_id' => $productId, 'warehouse_id' => $warehouseId,
                    'qty_kg' => $qtyKg, 'unit_cost' => $avg, 'total_cost' => round($qtyKg * $avg, 2), 'note' => $note,
                    'status' => 'posted', 'recorded_by' => $userId, 'created_at' => now_local()]);
                Inventory::issue($productId, $warehouseId, $qtyKg, 'rm_usage', 'rm_daily_usage', (int)$id, null, null, 'Daily usage — ' . $note);
                audit('create', 'rm_daily_usage', $id, sprintf('Used %.3f KG of %s on %s', $qtyKg, $product['name'], $date));
                return [true, 'saved'];
            }
            if ($existing['status'] !== 'posted') {
                throw new RuntimeException(__('This usage entry was cancelled — it cannot be re-used. Delete it first or create a new correction.'));
            }
            // Delta adjustment
            $old = (float)$existing['qty_kg'];
            $delta = round($qtyKg - $old, 3);
            if (abs($delta) < 0.0009) {
                if ($note !== (string)$existing['note']) {
                    db_exec('UPDATE rm_daily_usage SET note = ? WHERE id = ?', [$note, (int)$existing['id']]);
                    audit('update', 'rm_daily_usage', (int)$existing['id'], 'Note updated' . ($note !== '' ? ': ' . $note : ''));
                }
                return [true, 'saved'];
            }
            if ($delta > 0) {
                $extra = $delta;
                if ($onHand + 0.0009 < $extra) {
                    throw new RuntimeException(sprintf(__('Insufficient stock: %.3f KG available to add to today\'s usage.'), $onHand));
                }
                Inventory::issue($productId, $warehouseId, $extra, 'rm_usage', 'rm_daily_usage', (int)$existing['id'], null, null, 'Daily usage correction +' . number_format($extra, 3) . ' KG' . ($note !== '' ? ' — ' . $note : ''));
            } else {
                Inventory::receive($productId, $warehouseId, -$delta, $avg, 'rm_usage_return', 'rm_daily_usage', (int)$existing['id'], null, null, 'Daily usage correction -' . number_format(-$delta, 3) . ' KG');
            }
            $newQty = (float)$qtyKg;
            db_exec('UPDATE rm_daily_usage SET qty_kg = ?, total_cost = ?, note = ?, recorded_by = ? WHERE id = ?',
                [$newQty, round($newQty * $avg, 2), $note, $userId, (int)$existing['id']]);
            audit('update', 'rm_daily_usage', (int)$existing['id'], sprintf('Usage corrected %.3f → %.3f KG (delta %+.3f)', $old, $newQty, $delta));
            return [true, 'saved'];
        });
    }

    /** Cancel usage → return stock exactly once. Owner/Admin only (controller gate). */
    public static function cancelUsage(int $id, int $userId, string $reason): void {
        Database::transaction(function () use ($id, $userId, $reason) {
            $row = db_one('SELECT * FROM rm_daily_usage WHERE id = ?', [$id]);
            if (!$row) throw new RuntimeException(__('Usage entry not found.'));
            if ($row['status'] === 'cancelled') throw new RuntimeException(__('Already cancelled.'));
            $product = db_one('SELECT name FROM products WHERE id = ?', [(int)$row['product_id']]);
            $avg = (float)db_val('SELECT COALESCE(avg_cost,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [(int)$row['product_id'], (int)$row['warehouse_id']]);
            Inventory::receive((int)$row['product_id'], (int)$row['warehouse_id'], (float)$row['qty_kg'], $avg, 'rm_usage_return', 'rm_daily_usage', $id, null, null, 'Usage cancelled: ' . $reason);
            $newNote = trim(((string)($row['note'] ?? '')) . ' — cancelled by user ' . $userId . ': ' . $reason);
            db_exec("UPDATE rm_daily_usage SET status = 'cancelled', note = ? WHERE id = ?", [$newNote, $id]);
            audit('cancel', 'rm_daily_usage', $id, 'Cancelled usage ' . number_format((float)$row['qty_kg'], 3) . ' KG of ' . ($product['name'] ?? '') . ' — ' . $reason);
        });
    }
}
