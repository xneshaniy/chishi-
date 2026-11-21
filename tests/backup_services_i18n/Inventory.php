<?php
/**
 * Inventory engine — weighted-average (default) or FIFO costing.
 * All stock changes write stock_movements + update stock_balances in one DB transaction.
 * Cost is always computed server-side from stock records — never trusted from the client.
 */
final class Inventory {

    public static function costingMethod(): string { return cfg('costing_method', 'weighted_average'); }

    /** Current on-hand for a product (all warehouses or one). */
    public static function balance(int $productId, ?int $warehouseId = null): float {
        $sql = 'SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ?';
        $p = [$productId];
        if ($warehouseId) { $sql .= ' AND warehouse_id = ?'; $p[] = $warehouseId; }
        return (float)db_val($sql, $p);
    }

    public static function avgCost(int $productId, int $warehouseId): float {
        return (float)(db_val('SELECT avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]) ?? 0);
    }

    /** Increase stock (purchase, production output, adjustment in, opening). */
    public static function receive(int $productId, int $warehouseId, float $qty, float $unitCost,
                                   string $type, ?string $refType = null, ?int $refId = null,
                                   ?string $batch = null, ?string $expiry = null, ?string $notes = null): void {
        if ($qty <= 0) throw new InvalidArgumentException('Receive qty must be positive.');
        $unitCost = round($unitCost, 4);
        self::ensureBalanceRow($productId, $warehouseId);
        Database::transaction(function () use ($productId, $warehouseId, $qty, $unitCost, $type, $refType, $refId, $batch, $expiry, $notes) {
            $bal = db_one('SELECT qty, avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
            $oldQty = (float)$bal['qty']; $oldAvg = (float)$bal['avg_cost'];
            $newQty = $oldQty + $qty;
            $newAvg = $newQty > 0 ? (($oldQty * $oldAvg) + ($qty * $unitCost)) / $newQty : 0;
            db_exec('UPDATE stock_balances SET qty = ?, avg_cost = ? WHERE product_id = ? AND warehouse_id = ?',
                [round($newQty, 3), round($newAvg, 4), $productId, $warehouseId]);
            // FIFO layer
            if (self::costingMethod() === 'fifo') {
                db_insert('stock_cost_layers', ['product_id' => $productId, 'warehouse_id' => $warehouseId,
                    'qty_remaining' => $qty, 'unit_cost' => $unitCost, 'source_type' => $type, 'source_id' => $refId, 'created_at' => now_local()]);
            }
            self::movement($productId, $warehouseId, $type, $qty, $unitCost, $qty * $unitCost, $newQty, $refType, $refId, $batch, $expiry, $notes);
        });
    }

    /**
     * Decrease stock (sale, issue, production consumption, adjustment out, damaged/expired).
     * Returns actual unit cost used. Throws on insufficient stock unless negatives allowed.
     */
    public static function issue(int $productId, int $warehouseId, float $qty,
                                 string $type, ?string $refType = null, ?int $refId = null,
                                 ?string $batch = null, ?string $expiry = null, ?string $notes = null,
                                 bool $allowNegative = false): float {
        if ($qty <= 0) throw new InvalidArgumentException('Issue qty must be positive.');
        self::ensureBalanceRow($productId, $warehouseId);
        return Database::transaction(function () use ($productId, $warehouseId, $qty, $type, $refType, $refId, $batch, $expiry, $notes, $allowNegative) {
            $bal = db_one('SELECT qty, avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
            $oldQty = (float)$bal['qty']; $oldAvg = (float)$bal['avg_cost'];
            if (!$allowNegative && !(bool)cfg('allow_negative_stock', false) && ($oldQty - $qty) < -0.0009) {
                $prod = db_val('SELECT name FROM products WHERE id = ?', [$productId]);
                $wh = db_val('SELECT name FROM warehouses WHERE id = ?', [$warehouseId]);
                throw new RuntimeException(sprintf('Insufficient stock for %s in %s (on hand %.3f, requested %.3f).', $prod, $wh, $oldQty, $qty));
            }
            if (self::costingMethod() === 'fifo') {
                $unitCost = self::consumeLayersFifo($productId, $warehouseId, $qty);
            } else {
                $unitCost = $oldAvg;
            }
            $newQty = $oldQty - $qty;
            // weighted average: avg cost unchanged on issue; fifo: recompute weighted avg for display
            $newAvg = self::costingMethod() === 'fifo' ? self::fifoDisplayAvg($productId, $warehouseId) : $oldAvg;
            db_exec('UPDATE stock_balances SET qty = ?, avg_cost = ? WHERE product_id = ? AND warehouse_id = ?',
                [round($newQty, 3), round($newAvg, 4), $productId, $warehouseId]);
            self::movement($productId, $warehouseId, $type, -$qty, $unitCost, $qty * $unitCost, $newQty, $refType, $refId, $batch, $expiry, $notes);
            return round($unitCost, 4);
        });
    }

    /** Transfer between warehouses (does NOT affect accounting; location change only). */
    public static function transfer(int $productId, int $fromWh, int $toWh, float $qty,
                                    string $typeFrom, string $typeTo, ?string $refType = null, ?int $refId = null,
                                    ?string $batch = null, ?string $expiry = null, ?string $notes = null): void {
        Database::transaction(function () use ($productId, $fromWh, $toWh, $qty, $typeFrom, $typeTo, $refType, $refId, $batch, $expiry, $notes) {
            // issue() handles WA/FIFO cost + negative-stock guard; receive() recreates cost at destination
            $unitCost = self::issue($productId, $fromWh, $qty, $typeFrom, $refType, $refId, $batch, $expiry, $notes);
            self::receive($productId, $toWh, $qty, $unitCost, $typeTo, $refType, $refId, $batch, $expiry, $notes);
        });
    }

    private static function ensureBalanceRow(int $productId, int $warehouseId): void {
        $exists = db_val('SELECT 1 FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
        if (!$exists) {
            try { db_insert('stock_balances', ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'qty' => 0, 'avg_cost' => 0]); }
            catch (Throwable $e) { /* concurrent insert — fine */ }
        }
    }

    private static function movement(int $productId, int $wh, string $type, float $qty, float $unitCost,
                                     float $totalCost, float $balanceQty, ?string $refType, ?int $refId,
                                     ?string $batch, ?string $expiry, ?string $notes): void {
        db_insert('stock_movements', [
            'product_id' => $productId, 'warehouse_id' => $wh, 'type' => $type,
            'qty' => round($qty, 3), 'unit_cost' => round($unitCost, 4), 'total_cost' => round($totalCost, 2),
            'balance_qty' => round($balanceQty, 3), 'ref_type' => $refType, 'ref_id' => $refId,
            'batch_no' => $batch, 'expiry_date' => $expiry, 'notes' => $notes,
            'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
        ]);
    }

    private static function consumeLayersFifo(int $productId, int $warehouseId, float $qty): float {
        $remaining = $qty;
        $costSum = 0.0;
        $layers = db_all('SELECT * FROM stock_cost_layers WHERE product_id = ? AND warehouse_id = ? AND qty_remaining > 0 ORDER BY created_at ASC, id ASC',
            [$productId, $warehouseId]);
        foreach ($layers as $layer) {
            if ($remaining <= 0) break;
            $take = min((float)$layer['qty_remaining'], $remaining);
            $costSum += $take * (float)$layer['unit_cost'];
            $remaining -= $take;
            $left = (float)$layer['qty_remaining'] - $take;
            db_exec('UPDATE stock_cost_layers SET qty_remaining = ? WHERE id = ?', [round($left, 3), $layer['id']]);
        }
        if ($remaining > 0.0009) {
            if (!(bool)cfg('allow_negative_stock', false)) {
                throw new RuntimeException(sprintf('Insufficient FIFO layers for product %d in warehouse %d.', $productId, $warehouseId));
            }
            return $qty > 0 ? self::avgCost($productId, $warehouseId) : 0;
        }
        return $qty > 0 ? $costSum / $qty : 0;
    }

    private static function fifoDisplayAvg(int $productId, int $warehouseId): float {
        $val = (float)db_val('SELECT COALESCE(SUM(qty_remaining * unit_cost),0) FROM stock_cost_layers WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
        $qty = self::balance($productId, $warehouseId);
        return $qty > 0.0009 ? $val / $qty : 0;
    }

    /** Low stock: products at/below reorder level */
    public static function lowStock(): array {
        return db_all("SELECT p.id, p.sku, p.name, p.reorder_level, COALESCE(SUM(sb.qty),0) AS on_hand
                       FROM products p JOIN stock_balances sb ON sb.product_id = p.id
                       WHERE p.is_active = 1
                       GROUP BY p.id HAVING on_hand <= p.reorder_level ORDER BY on_hand ASC LIMIT 25");
    }

    public static function stockValue(?int $warehouseId = null): float {
        $sql = 'SELECT COALESCE(SUM(qty * avg_cost),0) FROM stock_balances';
        $p = [];
        if ($warehouseId) { $sql .= ' WHERE warehouse_id = ?'; $p[] = $warehouseId; }
        return round((float)db_val($sql, $p), 2);
    }
}
