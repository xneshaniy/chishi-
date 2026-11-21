<?php
/**
 * Raw material screens — purchases report (Owner/Admin) + daily usage + reports.
 *  - Purchase GRNs and all financial details: Owner/Admin only.
 *  - Daily usage: any holder of rm.usage (Owner/Admin/Manager). Manager cannot touch money.
 *  - Cancelling an entry (returns stock) is Owner/Admin only.
 */
final class RawMaterialController {

    /** Daily Raw Material Usage page — form + today's entries. */
    public static function usage(): void {
        if (!Auth::can('rm.usage')) { http_response_code(403); exit('Forbidden'); }
        $date = RawMaterial::validDate(inp('date', '')) ? inp('date') : today();
        $productId = intv(inp('product_id'));
        $note = trim(inp('note'));
        $warehouseId = intv(inp('warehouse_id'));
        $warehouses = RawMaterial::consumptionWarehouses();
        $defaultWh = RawMaterial::defaultWarehouse();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if ($productId <= 0) { flash('error', __('Select the raw material.')); redirect('inventory/raw-usage', ['date' => $date]); }
            $qty = num(inp('qty_kg'));
            try {
                [$ok, $msg] = RawMaterial::recordUsage($date, $productId, $warehouseId ?: $defaultWh, $qty, $note, (int)($_SESSION['user_id'] ?? 0));
                if ($ok) flash('success', __('Daily usage saved — stock updated.'));
                else flash('error', $msg);
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('inventory/raw-usage', ['date' => $date]);
        }

        $where = ['usage_date = ?']; $params = [$date];
        $entries = db_all("SELECT rdu.*, p.name AS product_name, p.sku, u.username AS recorded_by_username
                FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id LEFT JOIN users u ON u.id = rdu.recorded_by
                WHERE rdu.usage_date = ? ORDER BY rdu.id DESC", $params);
        $products = db_all("SELECT p.id, p.sku, p.name, p.kind FROM products p WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 AND COALESCE(p.is_archived, 0) = 0 ORDER BY p.name");
        view('raw/usage', ['entries' => $entries, 'products' => $products, 'warehouses' => $warehouses,
                           'defaultWh' => $defaultWh, 'date' => $date, 'title' => __('Daily Raw Material Usage')]);
    }

    /** Cancel usage (Owner/Admin only) — returns stock exactly once. */
    public static function cancelUsage(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden — only Owner/Admin can cancel usage entries.'); }
        $id = intv(inp('id')); $reason = trim(inp('reason'));
        if ($reason === '') { flash('error', __('A reason is required to cancel.')); redirect('inventory/raw-usage'); }
        try {
            RawMaterial::cancelUsage($id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Usage cancelled — stock returned.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('inventory/raw-usage');
    }

    /** Date-range / material-wise usage report — Owner/Admin see money, Manager sees only qty. */
    public static function report(): void {
        if (!Auth::can('rm.usage')) { http_response_code(403); exit('Forbidden'); }
        $canMoney = is_owner_or_admin();
        $from = RawMaterial::validDate(inp('from', '')) ? inp('from') : date('Y-m-01');
        $to = RawMaterial::validDate(inp('to', '')) ? inp('to') : today();
        $materialId = intv(inp('material_id'));

        $where = ['rdu.usage_date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($materialId) { $where[] = 'rdu.product_id = ?'; $params[] = $materialId; }

        $rows = db_all("SELECT rdu.*, p.name AS product_name, p.sku, p.kind, w.name AS warehouse_name, u.username AS recorded_by_username
                FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id LEFT JOIN users u ON u.id = rdu.recorded_by LEFT JOIN warehouses w ON w.id = rdu.warehouse_id
                WHERE " . implode(' AND ', $where) . " ORDER BY rdu.usage_date DESC, rdu.id DESC", $params);

        $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ymExpr = $driver === 'mysql' ? "DATE_FORMAT(rdu.usage_date, '%Y-%m')" : "strftime('%Y-%m', rdu.usage_date)";
        $monthly = db_all("SELECT $ymExpr AS ym, COALESCE(SUM(rdu.qty_kg),0) AS qty, COUNT(*) AS entries
                FROM rm_daily_usage rdu WHERE rdu.status = 'posted' GROUP BY ym ORDER BY ym DESC LIMIT 24");
        $materials = db_all("SELECT p.id, p.name, COUNT(*) AS entries, COALESCE(SUM(rdu.qty_kg),0) AS qty
                FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id WHERE rdu.status = 'posted' AND rdu.usage_date BETWEEN ? AND ? GROUP BY rdu.product_id ORDER BY qty DESC", [$from, $to]);
        $totQty = 0.0; foreach ($rows as $r) $totQty += ($r['status'] === 'posted' ? (float)$r['qty_kg'] : 0);
        $materialsAll = db_all("SELECT p.id, p.name FROM products p WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 AND COALESCE(p.is_archived, 0) = 0 ORDER BY p.name");

        view('raw/report', ['rows' => $rows, 'from' => $from, 'to' => $to, 'materialId' => $materialId,
                            'monthly' => $monthly, 'materials' => $materials, 'materialsAll' => $materialsAll,
                            'totQty' => $totQty, 'canMoney' => $canMoney,
                            'title' => __('Raw Material Consumption Report')]);
    }
}
