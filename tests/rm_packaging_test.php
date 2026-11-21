<?php
/**
 * Raw Material purchasing (TON/KG/MAN), KG base-unit inventory, factory consumption,
 * production costing via consumption absorption, FG packaging display, salesman issue.
 * Mirrors the acceptance workflow: 2 TON + 100 KG → 2100 → issue 150 → produce → FG → salesman.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Production.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}
function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }

$kg = (int)db_val("SELECT id FROM units WHERE short = 'KG'");
$ton = (int)db_val("SELECT id FROM units WHERE short = 'TON'");
$man = (int)db_val("SELECT id FROM units WHERE short = 'MAN'");
$pcs = (int)db_val("SELECT id FROM units WHERE short = 'PCS'");
$whFactory = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-FACTORY'");
$whMain = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
$now = now_local();
$supplier = db_insert('suppliers', ['code' => 'SUP-T-1', 'name' => 'Test Supplier', 'is_active' => 1, 'created_at' => $now]);

$rm = db_insert('products', ['sku' => 'RM-T-1', 'name' => 'Tobacco Leaf Test', 'kind' => 'raw', 'unit_id' => $kg, 'purchase_price' => 0, 'sale_price' => 0, 'issue_rate' => 0, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
$fg = db_insert('products', ['sku' => 'FG-T-1', 'name' => 'JUSTI Test 10s', 'kind' => 'finished', 'unit_id' => $pcs, 'purchase_price' => 0, 'sale_price' => 2500, 'issue_rate' => 2500, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
db_insert('product_packaging', ['product_id' => $fg, 'pack_pieces' => 12, 'bundle_packs' => 10, 'packet_bundles' => 8, 'updated_at' => $now]);

function insertPurchase(int $invoiceId, int $productId, float $qty, string $unit, float $costPerOriginalUnit, int $kgId): int {
    $qtyKg = UnitConverter::toKg($qty, $unit);
    return db_insert('purchase_items', ['invoice_id' => $invoiceId, 'product_id' => $productId, 'qty' => $qty, 'unit_short' => $unit, 'qty_kg' => $qtyKg, 'unit_cost' => $costPerOriginalUnit, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => round($qty * $costPerOriginalUnit, 2), 'is_bonus' => 0]);
}
function newInvoice(int $supplier, int $warehouse, string $no): int {
    return db_insert('purchase_invoices', ['invoice_no' => 'TMP-' . bin2hex(random_bytes(4)), 'supplier_id' => $supplier, 'supplier_invoice_no' => $no, 'date' => today(), 'payment_type' => 'credit', 'warehouse_id' => $warehouse, 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0, 'status' => 'posted', 'payment_status' => 'unpaid', 'amount_paid' => 0, 'created_by' => 1, 'created_at' => now_local()]);
}

echo "=== 1. Purchase 2 TON of raw material (per-ton rate) → 2,000 KG in stock ===\n";
$inv1 = newInvoice($supplier, $whFactory, 'SI-001');
insertPurchase($inv1, $rm, 2, 'TON', 90000, $kg); // Rs 90,000/ton
db_exec("UPDATE purchase_invoices SET total = (SELECT SUM(line_total) FROM purchase_items WHERE invoice_id = ?) WHERE id = ?", [$inv1, $inv1]);
Posting::postPurchaseInvoice($inv1);
$bal = Inventory::balance($rm, $whFactory);
ok(approx($bal, 2000, 0.001), 'stock = 2,000 KG after 2 TON purchase');
$avg = (float)db_val('SELECT avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$rm, $whFactory]);
ok(approx($avg, 90), 'avg cost = Rs 90/kg (90,000 per ton)');

echo "\n=== 2. Purchase 100 KG at same rate → 2,100 KG total ===\n";
$inv2 = newInvoice($supplier, $whFactory, 'SI-002');
insertPurchase($inv2, $rm, 100, 'KG', 90, $kg); // Rs 90/kg
db_exec("UPDATE purchase_invoices SET total = (SELECT SUM(line_total) FROM purchase_items WHERE invoice_id = ?) WHERE id = ?", [$inv2, $inv2]);
Posting::postPurchaseInvoice($inv2);
ok(approx(Inventory::balance($rm, $whFactory), 2100, 0.001), 'stock = 2,100 KG');
ok(approx((float)db_val('SELECT avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$rm, $whFactory]), 90), 'avg cost still Rs 90/kg');
ok(approx((float)db_val('SELECT COALESCE(SUM(qty_kg),0) FROM purchase_items'), 2100), 'purchase history shows 2,100 KG in KG base');

echo "\n=== 3. MAUND conversion: 1 MAN = 40 KG ===\n";
ok(approx(UnitConverter::toKg(1, 'MAN'), 40), '1 MAN = 40 KG');
ok(approx(UnitConverter::toKg(2, 'MAN'), 80), '2 MAN = 80 KG');
ok(approx(UnitConverter::fromKg(2100, 'TON'), 2.1), '2100 KG = 2.1 TON equivalent');
ok(approx(UnitConverter::fromKg(2100, 'MAN'), 52.5), '2100 KG = 52.5 MAN equivalent');

echo "\n=== 4. Factory consumption of 150 KG (exactly-once posting) → 1,950 KG remaining ===\n";
$issueId = db_insert('factory_issues', ['issue_no' => 'FC-T-0001', 'date' => today(), 'batch_no' => 'BATCH-T-1', 'details' => 'Test batch', 'issued_by' => 1, 'approved_by' => 1, 'status' => 'pending', 'created_at' => $now]);
db_insert('factory_issue_items', ['issue_id' => $issueId, 'product_id' => $rm, 'warehouse_id' => $whFactory, 'qty_kg' => 150, 'unit_cost' => 0, 'total_cost' => 0]);
Posting::postFactoryIssue($issueId);
ok(approx(Inventory::balance($rm, $whFactory), 1950, 0.001), 'stock = 1,950 KG after consumption');
$cost = (float)db_val('SELECT total_cost FROM factory_issue_items WHERE issue_id = ?', [$issueId]);
ok(approx($cost, 150 * 90), 'consumption cost = 150 kg × Rs 90 = Rs 13,500');
ok(db_val('SELECT status FROM factory_issues WHERE id = ?', [$issueId]) === 'posted', 'issue status = posted');
Posting::postFactoryIssue($issueId); // idempotency
ok(approx(Inventory::balance($rm, $whFactory), 1950, 0.001), 're-posting is a no-op (exactly once)');

echo "\n=== 5. Over-issue is blocked ===\n";
try {
    $badIssue = db_insert('factory_issues', ['issue_no' => 'FC-T-0002', 'date' => today(), 'batch_no' => 'BATCH-T-2', 'details' => 'Over-issue', 'issued_by' => 1, 'status' => 'pending', 'created_at' => $now]);
    db_insert('factory_issue_items', ['issue_id' => $badIssue, 'product_id' => $rm, 'warehouse_id' => $whFactory, 'qty_kg' => 9999, 'unit_cost' => 0, 'total_cost' => 0]);
    Posting::postFactoryIssue($badIssue);
    ok(false, 'over-issue blocked');
} catch (RuntimeException $e) {
    ok(str_contains($e->getMessage(), 'Insufficient'), 'over-issue blocked (Insufficient stock)');
}

echo "\n=== 6. Production order completes — consumption absorbed, no double-deduct ===\n";
$bom = db_insert('bills_of_materials', ['product_id' => $fg, 'name' => 'JUSTI Test 10s v1', 'output_qty' => 10, 'is_active' => 1]);
db_insert('bom_lines', ['bom_id' => $bom, 'material_id' => $rm, 'qty' => 100]);
$order = db_insert('production_orders', ['order_no' => 'PO-T-0001', 'product_id' => $fg, 'warehouse_id' => $whFactory, 'bom_id' => $bom, 'qty_planned' => 10, 'qty_produced' => 0, 'status' => 'released', 'batch_no' => 'BATCH-T-1', 'created_by' => 1, 'created_at' => $now]);
// Consumption (BATCH-T-1) was posted BEFORE completion
Database::transaction(function () use ($order, $fg, $whFactory) {
    // Exercise exactly what orderComplete does for a batch with posted consumption
    $absorb = ProductionService::absorbBatchConsumption($order, 'BATCH-T-1');
    $materials = (float)($absorb['materials_cost'] ?? 0);
    $total = round($materials + 500 + 250, 2);
    $unitCost = 10 > 0 ? round($total / 10, 4) : 0;
    Inventory::receive($fg, $whFactory, 10, $unitCost, 'production_in', 'production_order', $order, 'BATCH-T-1', null, 'Produced');
    db_exec("UPDATE production_orders SET status = 'completed', qty_produced = 10, labor_cost = 500, overhead_cost = 250, materials_cost = ?, total_cost = ?, completed_at = ?, waste_qty = 0 WHERE id = ?", [$materials, $total, now_local(), $order]);
});
ok(approx(Inventory::balance($rm, $whFactory), 1950, 0.001), 'RM stock STAYS at 1,950 KG (no double-deduct by production)');
ok(approx(Inventory::balance($fg, $whFactory), 10, 0.001), 'FG stock = 10 units received');
$fgCost = (float)db_val('SELECT avg_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$fg, $whFactory]);
ok(approx($fgCost, 1425), 'FG unit cost = 14,250 / 10 = Rs 1,425');

echo "\n=== 7. FG packaging display: Packets are primary, Bundle is an optional equivalent ===\n";
$disp = UnitConverter::packagingDisplay(10, $fg);
echo "  display: $disp\n";
ok(str_contains($disp, '10.000 Packets'), '10 Packets shown as the primary stock quantity (no piece division)');
ok(str_contains($disp, '1.00 Bundles'), 'optional Bundle equivalent shown (10 / 10 = 1.00 Bundles)');
$thisCfg = UnitConverter::productPackaging($fg);
ok(approx($thisCfg['pack_pieces'], 12) && approx($thisCfg['bundle_packs'], 10) && approx($thisCfg['packet_bundles'], 8), 'per-product packaging config still resolves for reference');
$inherited = UnitConverter::packagingDefaults();
ok($inherited['pack_pieces'] === 12.0, 'global default = 12 pcs/pack');

echo "\n=== 8. Issue 5 pieces to a salesman — FG stock and payable ===\n";
$smWh = (int)db_insert('warehouses', ['code' => 'WH-SM-T1', 'name' => 'Test Van T1', 'type' => 'salesman', 'is_active' => 1]);
$smId = db_insert('salesmen', ['code' => 'SM-TEST1', 'name' => 'Test salesman 1', 'phone' => '0301-1111111', 'area' => 'Test', 'commission_percent' => 2.5, 'warehouse_id' => $smWh, 'is_active' => 1, 'created_at' => $now]);
$issue = db_insert('salesman_issues', ['issue_no' => 'ISS-T-1', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issue, 'product_id' => $fg, 'paid_qty' => 5, 'bonus_qty' => 0, 'qty' => 5, 'unit_cost' => 1425, 'issue_rate' => 2500, 'line_payable' => 12500, 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issue);
ok(approx(Inventory::balance($fg, $whFactory), 5, 0.001), 'FG stock = 5 after salesman issue');
ok(approx((float)db_val('SELECT total_payable FROM salesman_issues WHERE id = ?', [$issue]), 12500), 'salesman payable = Rs 12,500 (5 × 2,500)');

echo "\n=== 9. Reports show RM consumption via the COMPANY consumption path ===\n";
$consumed = (float)db_val("SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted'");
ok(approx($consumed, 150), 'factory consumption report = 150 KG');
ok(($disp = db_val("SELECT COUNT(*) FROM products WHERE kind = 'raw' AND unit_id = ?", [$kg])) === 1 ? true : false, 'raw products locked to KG unit');

echo "\nRMS PACKAGING E2E: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
