<?php
// Patch PurchaseController to add daily-usage data loading and new view variables
$file = __DIR__ . '/../app/controllers/PurchaseController.php';
$src  = file_get_contents($file);

$old = <<<'PHP'
        $rawProductsAll = db_all("SELECT id, sku, name, kind FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 ORDER BY name");

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
            'title'         => 'Raw Material Purchases',
        ]);
PHP;

$new = <<<'PHP'
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
        $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ymExpr = $driver === 'mysql' ? "DATE_FORMAT(usage_date,'%Y-%m')" : "strftime('%Y-%m',usage_date)";
        $monthTotalKg = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE status='posted' AND $ymExpr=strftime('%Y-%m','now')");

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
PHP;

if (strpos($src, $old) === false) {
    echo "ERROR: target string not found!\n";
    exit(1);
}
$src = str_replace($old, $new, $src);
file_put_contents($file, $src);
echo "OK: PurchaseController patched.\n";
