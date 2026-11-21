<?php
/** Salesman stock issue with optional bonus — full flow test (stock, payable, PROMO cost, approval). */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
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
    if ($cond) { echo "  PASS  $name\n"; } else { $failures++; echo "  FAIL  $name\n"; }
}
function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }

$now = now_local();
$whMain = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
$whFactory = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-FACTORY'");
$whVan = (int)db_insert('warehouses', ['branch_id' => (int)db_val("SELECT id FROM branches WHERE code='MAIN'"), 'code' => 'WH-SM-TEST', 'name' => 'Test Van', 'type' => 'salesman', 'is_active' => 1]);
$smId = db_insert('salesmen', ['code' => 'SM-TEST', 'name' => 'Test Salesman', 'phone' => '0300-0000002', 'area' => 'Main', 'commission_percent' => 2.5, 'warehouse_id' => $whVan, 'is_active' => 1, 'created_at' => $now]);
$fg = db_insert('products', ['sku' => 'FG-BONUS-1', 'name' => 'Bonus Test Carton', 'kind' => 'finished', 'purchase_price' => 500, 'sale_price' => 720, 'issue_rate' => 10, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
Inventory::receive($fg, $whFactory, 500, 400, 'opening', 'opening_stock', null, null, null, 'Seed');

echo "\n=== 1. Give Bonus = No → plain issue, no bonus anywhere ===\n";
$itemsNoBonus = [['product_id' => $fg, 'paid_qty' => 200, 'bonus_qty' => 0, 'issue_rate' => 10]];
$issueId1 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-1', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
foreach ($itemsNoBonus as $it) db_insert('salesman_issue_items', ['issue_id' => $issueId1, 'product_id' => $it['product_id'], 'paid_qty' => $it['paid_qty'], 'bonus_qty' => $it['bonus_qty'], 'qty' => $it['paid_qty'] + $it['bonus_qty'], 'unit_cost' => 400, 'issue_rate' => $it['issue_rate'], 'line_payable' => $it['paid_qty'] * $it['issue_rate'], 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId1);
$row = db_one('SELECT * FROM salesman_issues WHERE id = ?', [$issueId1]);
ok(approx((float)$row['total_payable'], 2000), 'payable = 200 paid × Rs 10 = Rs 2,000');
ok(approx((float)$row['total_bonus_cost'], 0), 'bonus cost = 0 when no bonus');
ok(approx(Inventory::balance($fg, $whFactory), 300), 'source stock reduced by 200 only (300 left)');
ok(approx(Inventory::balance($fg, $whVan), 200), 'van received 200');
ok(db_val('SELECT bonus_reason FROM salesman_issues WHERE id = ?', [$issueId1]) === null, 'no bonus reason stored when bonus not given');
$led = (float)db_val("SELECT COALESCE(SUM(debit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'issue'", [$smId]);
ok(approx($led, 2000), 'salesman ledger payable = 2,000');

echo "\n=== 2. Give Bonus = Yes → bonus moves, not payable ===\n";
$bonusReason = 'Festive offer';
$itemsBonus = [['product_id' => $fg, 'paid_qty' => 200, 'bonus_qty' => 50, 'issue_rate' => 10]];
$issueId2 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-2', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'bonus_reason' => $bonusReason, 'created_by' => 1, 'created_at' => $now]);
foreach ($itemsBonus as $it) db_insert('salesman_issue_items', ['issue_id' => $issueId2, 'product_id' => $it['product_id'], 'paid_qty' => $it['paid_qty'], 'bonus_qty' => $it['bonus_qty'], 'qty' => $it['paid_qty'] + $it['bonus_qty'], 'unit_cost' => 400, 'issue_rate' => $it['issue_rate'], 'line_payable' => $it['paid_qty'] * $it['issue_rate'], 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId2);
$row2 = db_one('SELECT * FROM salesman_issues WHERE id = ?', [$issueId2]);
ok(approx((float)$row2['total_payable'], 2000), 'payable STILL Rs 2,000 (bonus not charged)');
ok(approx((float)$row2['total_bonus_cost'], 20000), 'bonus cost = 50 × 400 = Rs 20,000 (PROMO)');
ok(approx(Inventory::balance($fg, $whFactory), 50), 'source stock reduced by 250 (50 left)');
ok(approx(Inventory::balance($fg, $whVan), 450), 'van received 250 more (450 total)');
ok($row2['bonus_reason'] === $bonusReason, 'bonus reason stored on the issue');
$item = db_one('SELECT paid_qty, bonus_qty, qty, line_payable FROM salesman_issue_items WHERE issue_id = ?', [$issueId2]);
ok(approx((float)$item['paid_qty'], 200) && approx((float)$item['bonus_qty'], 50) && approx((float)$item['qty'], 250), 'paid / bonus / total qty kept separately (200 / 50 / 250)');
ok(approx((float)$item['line_payable'], 2000), 'line payable = paid qty only');
$led2 = (float)db_val("SELECT COALESCE(SUM(debit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'issue'", [$smId]);
ok(approx($led2, 4000), 'salesman ledger = 2,000 + 2,000 = 4,000 (no bonus)');
$promo = (float)db_val("SELECT COALESCE(SUM(debit),0) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id JOIN chart_of_accounts ca ON ca.id = jl.account_id WHERE ca.system_key = 'PROMO' AND je.status = 'posted'");
ok($promo >= 20000, 'PROMO account carries the bonus cost');

echo "\n=== 3. Bonus over threshold waits for owner/admin approval ===\n";
$threshold = (float)db_val("SELECT setting_value FROM company_settings WHERE setting_key = 'bonus_approval_threshold'");
$bigBonus = $threshold > 0 ? (int)($threshold / 400) + 10 : 10;
$issueId3 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-3', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'pending_approval', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 777, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issueId3, 'product_id' => $fg, 'paid_qty' => 10, 'bonus_qty' => $bigBonus, 'qty' => 10 + $bigBonus, 'unit_cost' => 400, 'issue_rate' => 10, 'line_payable' => 100, 'bonus_cost' => 0]);
Approvals::create('bonus', 'salesman_issues', $issueId3, 100, 'Bonus exceeds threshold', 777);
try { Posting::postSalesmanIssue($issueId3); ok(false, 'direct post of pending issue refused'); }
catch (Throwable $e) { ok(true, 'direct post of pending issue refused (' . $e->getMessage() . ')'); }
$stockBefore = Inventory::balance($fg, $whVan);
$owner = db_val("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = 'owner' LIMIT 1") ?: 1;
$req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'salesman_issues' AND reference_id = ? AND status = 'pending'", [$issueId3]);
ok((int)$req['id'] > 0, 'approval request pending');
Approvals::decide((int)$req['id'], true, 'approved in e2e', (int)$owner);
$row3 = db_one('SELECT status FROM salesman_issues WHERE id = ?', [$issueId3]);
ok($row3['status'] === 'posted', 'approved issue posted via console');
$stockAfter = Inventory::balance($fg, $whVan);
ok(approx($stockAfter - $stockBefore, 10 + $bigBonus), 'stock moved only after approval');

echo "\n=== 6. Issue rate is saved with the order — later product price changes never affect it ===\n";
// Change the product's issue_rate now
db_exec('UPDATE products SET issue_rate = 99 WHERE id = ?', [$fg]);
$rate1 = (float)db_val('SELECT issue_rate FROM salesman_issue_items WHERE issue_id = ?', [$issueId1]);
$rate2 = (float)db_val('SELECT issue_rate FROM salesman_issue_items WHERE issue_id = ?', [$issueId2]);
ok(approx($rate1, 10) && approx($rate2, 10), 'old orders keep rate 10 after product rate changed to 99');
// other product, different rate
$fg2 = db_insert('products', ['sku' => 'FG-BONUS-2', 'name' => 'Bonus Test 20s', 'kind' => 'finished', 'purchase_price' => 900, 'sale_price' => 1450, 'issue_rate' => 20, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
Inventory::receive($fg2, $whFactory, 100, 700, 'opening', 'opening_stock', null, null, null, 'Seed');
$issueId5 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-5', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issueId5, 'product_id' => $fg2, 'paid_qty' => 10, 'bonus_qty' => 0, 'qty' => 10, 'unit_cost' => 700, 'issue_rate' => 25, 'line_payable' => 250, 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId5);
$rate5 = (float)db_val('SELECT issue_rate FROM salesman_issue_items WHERE issue_id = ?', [$issueId5]);
ok(approx($rate5, 25), 'per-line rate override (25) saved — issue rates may differ per product/line');
// same product, different salesman, different rate — issue rate is per order not per product
$whVan2 = (int)db_insert('warehouses', ['branch_id' => (int)db_val("SELECT id FROM branches WHERE code='MAIN'"), 'code' => 'WH-SM-TEST2', 'name' => 'Test Van 2', 'type' => 'salesman', 'is_active' => 1]);
$smId2 = db_insert('salesmen', ['code' => 'SM-TEST2', 'name' => 'Test Salesman 2', 'phone' => '0300-0000003', 'area' => 'Second', 'commission_percent' => 2.5, 'warehouse_id' => $whVan2, 'is_active' => 1, 'created_at' => $now]);
Inventory::receive($fg2, $whFactory, 100, 700, 'opening', 'opening_stock', null, null, null, 'Seed2');
$issueId6 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-6', 'salesman_id' => $smId2, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issueId6, 'product_id' => $fg2, 'paid_qty' => 10, 'bonus_qty' => 0, 'qty' => 10, 'unit_cost' => 700, 'issue_rate' => 18, 'line_payable' => 180, 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId6);
$row6 = db_one('SELECT issue_rate, line_payable FROM salesman_issue_items WHERE issue_id = ?', [$issueId6]);
ok(approx((float)$row6['issue_rate'], 18) && approx((float)$row6['line_payable'], 180), 'same product, second salesman, rate 18 saved — rates are per order line');

echo "\n=== 4. Transfer type with bonus = no payable, bonus moves ===\n";
$issueId4 = db_insert('salesman_issues', ['issue_no' => 'ISS-T-4', 'salesman_id' => $smId, 'from_warehouse_id' => $whFactory, 'date' => today(), 'issue_type' => 'transfer', 'status' => 'approved', 'total_payable' => 0, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issueId4, 'product_id' => $fg, 'paid_qty' => 20, 'bonus_qty' => 10, 'qty' => 30, 'unit_cost' => 400, 'issue_rate' => 10, 'line_payable' => 0, 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId4);
$row4 = db_one('SELECT * FROM salesman_issues WHERE id = ?', [$issueId4]);
ok(approx((float)$row4['total_payable'], 0), 'free transfer payable = 0');
ok(approx(Inventory::balance($fg, $whVan), $stockAfter + 30), 'van received 30 (paid 20 + bonus 10)');

echo "\n=== 5. Trial balance still agrees ===\n";
$tb = Accounting::trialBalance();
ok((bool)($tb['balanced'] ?? false), 'trial balance balanced after all issues');

echo "\n========================================\n";
echo "SALESMAN BONUS ISSUE E2E: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
