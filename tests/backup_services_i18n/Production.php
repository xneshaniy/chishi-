<?php
/**
 * Production engine — complete production orders from factory consumption (or BOM fallback).
 * Shared by the HTTP controller and tests.
 */
final class ProductionService {

    /**
     * If a batch has posted factory consumption lines, DELETE planned inputs and INSERT the
     * actual consumption lines (cost traceability). Returns [absorbed_count, materials_cost]
     * or null when no consumption exists for the batch (caller should fall back to BOM inputs).
     */
    public static function absorbBatchConsumption(int $orderId, ?string $batchNo): ?array {
        if ($batchNo === null || $batchNo === '') return null;
        $items = db_all("SELECT fii.* FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted' AND fi.batch_no = ?", [$batchNo]);
        if (!$items) return null;
        db_exec('DELETE FROM production_inputs WHERE production_order_id = ?', [$orderId]);
        $materialsCost = 0.0;
        foreach ($items as $it) {
            $lineCost = (float)$it['total_cost'];
            db_insert('production_inputs', ['production_order_id' => $orderId, 'product_id' => (int)$it['product_id'], 'qty' => (float)$it['qty_kg'], 'unit_cost' => (float)$it['unit_cost'], 'total_cost' => $lineCost]);
            $materialsCost += $lineCost;
        }
        return ['count' => count($items), 'materials_cost' => round($materialsCost, 2)];
    }

    /** Consume all planned BOM inputs at WA/FIFO cost. Returns total materials cost. */
    public static function consumePlannedInputs(int $orderId, int $warehouseId, string $orderNo): float {
        $materialsCost = 0.0;
        foreach (db_all('SELECT * FROM production_inputs WHERE production_order_id = ?', [$orderId]) as $input) {
            $unitCost = Inventory::issue((int)$input['product_id'], $warehouseId, (float)$input['qty'],
                'production_consumption', 'production_order', $orderId, null, null, 'Consumed for ' . $orderNo);
            $total = round($unitCost * (float)$input['qty'], 2);
            db_exec('UPDATE production_inputs SET unit_cost = ?, total_cost = ? WHERE id = ?', [$unitCost, $total, $input['id']]);
            $materialsCost += $total;
        }
        return round($materialsCost, 2);
    }
}
