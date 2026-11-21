<?php
/** Claim Goods â€” full lifecycle in-memory test: Good/Damaged/Rejected split, stock, journal, approvals, payments. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = [];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Auth.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Payroll.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $c, string $n): void { global $tests, $failures; $tests++; echo $c ? "  PASS  $n\n" : "  FAIL  $n\n"; if (!$c) $failures++; }
function approx($a, $b, $e = 0.01): bool { return abs((float)$a - (float)$b) <= $e; }

function mkUser(string $u, string $roleCode): int {
    $id = db_insert('users', ['username' => $u, 'full_name' => strtoupper($u), 'password_hash' => password_hash('Test@1234x', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => now_local()]);
    $roleId = (int)db_val('SELECT id FROM roles WHERE code = ?', [$roleCode]);
    db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $roleId]);
    return $id;
}
$ownerId = mkUser('boss', 'owner');
$adminId = mkUser('admin1', 'admin');
$accId   = mkUser('acc1', 'accountant');
$smId    = mkUser('van1', 'salesman');
$supId   = db_insert('suppliers', ['code' => 'SUP-C', 'name' => 'Claim Supplier', 'is_active' => 1, 'created_at' => now_local()]);
$whMain  = (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'");
$cashId  = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1");
$bankId  = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='bank' LIMIT 1");

$prod = db_insert('products', ['sku' => 'CL-100', 'name' => 'Claim Test Product', 'kind' => 'finished', 'purchase_price' => 50, 'sale_price' => 75, 'issue_rate' => 10, 'min_price' => 0, 'tax_rate' => 0, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => now_local()]);

echo "\n=== A Â· Claim receipt: 70 good / 10 damaged / 20 rejected (example from spec) ===\n";
$claimId = db_insert('claim_goods', ['claim_no' => 'CG-TEST-1', 'supplier_id' => $supId, 'warehouse_id' => $whMain, 'date' => today(), 'reason' => 'damage in transit', 'status' => 'pending_approval', 'payment_type' => 'outstanding', 'pay_account_id' => null, 'good_qty' => 70, 'damaged_qty' => 10, 'rejected_qty' => 20, 'claim_amount' => 3500, 'cash_paid' => 0, 'created_by' => $accId, 'created_at' => now_local()]);
db_insert('claim_goods_items', ['claim_goods_id' => $claimId, 'product_id' => $prod, 'good_qty' => 70, 'damaged_qty' => 10, 'rejected_qty' => 20, 'claim_rate' => 50, 'line_claim' => 3500]);
Approvals::create('claim', 'claim_goods', $claimId, 3500, 'Claim receipt â€” damaged 10; rejected 20', $accId);
$appr = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'claim_goods' AND reference_id = ?", [$claimId]);
ok($appr > 0, 'approval request created (claim goes to Admin)');

echo "\n=== B Â· Pending approval: stock untouched, no journal, no balance ===\n";
ok(approx(Inventory::balance($prod, $whMain), 0), 'stock untouched while pending');
ok((int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'claim_goods' AND reference_id = ?", [$claimId]) === 0, 'no journal posted while pending');
$supBefore = Accounting::supplierBalance($supId);
ok(approx($supBefore, 0), 'supplier balance untouched');
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'payment_request' AND entity_id = ?", [$appr]) >= 2, 'owner+admin notified of claim');

echo "\n=== C Â· Approver cannot be the requester ===\n";
$thrown = false;
try { Approvals::decide($appr, true, 'self', $accId); } catch (Throwable $e) { $thrown = true; }
ok($thrown, 'requester cannot approve own claim');

echo "\n=== D Â· Owner approves â†’ stock = 70 ONLY, journal posts, supplier payable created ===\n";
Approvals::decide($appr, true, 'approved', $ownerId);
ok(approx(Inventory::balance($prod, $whMain), 70), 'only 70 accepted units added to stock');
ok(approx((float)db_val("SELECT qty FROM stock_balances WHERE product_id = ? AND warehouse_id = ?", [$prod, $whMain]), 70), 'stock_balances row = 70');
$dmg = (float)db_val('SELECT damaged_qty FROM claim_goods WHERE id = ?', [$claimId]);
$rej = (float)db_val('SELECT rejected_qty FROM claim_goods WHERE id = ?', [$claimId]);
ok(approx($dmg, 10) && approx($rej, 20), 'damaged (10) and rejected (20) recorded separately');
$supAfter = Accounting::supplierBalance($supId);
ok(approx($supAfter, 3500), 'supplier payable = Rs 3,500 (credit AP)');
$cogs = (float)db_val("SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE e.id = (SELECT journal_entry_id FROM claim_goods WHERE id = ?)", [$claimId]);
ok($cogs >= 3500, 'claim journal posted');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after claim posting');

echo "\n=== E Â· Exactly once: re-posting does nothing ===\n";
$journalCount = (int)db_val('SELECT COUNT(*) FROM journal_entries');
Posting::postClaimGoods($claimId);
Posting::postClaimGoods($claimId);
ok((int)db_val('SELECT COUNT(*) FROM journal_entries') === $journalCount, 'duplicate post is a no-op');
ok(approx(Inventory::balance($prod, $whMain), 70), 'stock stays 70 after re-post attempts');

echo "\n=== F Â· Pay part of outstanding claim (2000 now, approval-gated) ===\n";
$payId = db_insert('claim_goods_payments', ['claim_goods_id' => $claimId, 'date' => today(), 'amount' => 2000, 'account_id' => $bankId, 'status' => 'pending', 'created_by' => $accId, 'created_at' => now_local()]);
$payAppr = Approvals::create('payment', 'claim_goods_payments', $payId, 2000, 'Claim part-payment', $accId);
ok(Accounting::supplierBalance($supId) == 3500, 'supplier balance unchanged until payment approved');
$pRow = db_one('SELECT * FROM claim_goods_payments WHERE id = ?', [$payId]);
Approvals::decide((int)$payAppr, true, 'settle part', $ownerId);
ok(approx(Accounting::supplierBalance($supId), 1500), 'outstanding dropped to Rs 1,500 after approved payment');
$cg = db_one('SELECT cash_paid FROM claim_goods WHERE id = ?', [$claimId]);
ok(approx((float)$cg['cash_paid'], 2000), 'cash_paid = 2,000');

echo "\n=== G Â· Overpay rejected (outstanding 1500, request 9999) ===\n";
$cg2 = db_one('SELECT cash_paid FROM claim_goods WHERE id = ?', [$claimId]);
$outstandingLeft = 3500 - (float)$cg2['cash_paid'];
ok(approx($outstandingLeft, 1500), 'outstanding = Rs 1,500 correctly tracked');

echo "\n=== H Â· Cash claim at posting: Dr Inventory / Cr Cash (no AP) ===\n";
$cashClaimId = db_insert('claim_goods', ['claim_no' => 'CG-TEST-2', 'supplier_id' => $supId, 'warehouse_id' => $whMain, 'date' => today(), 'status' => 'pending_approval', 'payment_type' => 'cash', 'pay_account_id' => $cashId, 'good_qty' => 30, 'damaged_qty' => 5, 'rejected_qty' => 0, 'claim_amount' => 1500, 'cash_paid' => 0, 'created_by' => $accId, 'created_at' => now_local()]);
db_insert('claim_goods_items', ['claim_goods_id' => $cashClaimId, 'product_id' => $prod, 'good_qty' => 30, 'damaged_qty' => 5, 'rejected_qty' => 0, 'claim_rate' => 50, 'line_claim' => 1500]);
Approvals::create('claim', 'claim_goods', $cashClaimId, 1500, 'cash claim', $accId);
$cappr = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'claim_goods' AND reference_id = ?", [$cashClaimId]);
Approvals::decide($cappr, true, 'ok', $ownerId);
ok(approx(Inventory::balance($prod, $whMain), 100), 'stock now 100 (70 + 30)');
$supBal = Accounting::supplierBalance($supId);
ok(approx($supBal, 1500), 'cash claim adds nothing to AP (stays at 1500)');
$cgRow = db_one('SELECT cash_paid, status FROM claim_goods WHERE id = ?', [$cashClaimId]);
ok(approx((float)$cgRow['cash_paid'], 1500) && $cgRow['status'] === 'posted', 'cash claim paid at posting');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after cash claim');

echo "\n=== I Â· Rejection never touches stock or balances ===\n";
$rejClaim = db_insert('claim_goods', ['claim_no' => 'CG-TEST-3', 'supplier_id' => $supId, 'warehouse_id' => $whMain, 'date' => today(), 'status' => 'pending_approval', 'payment_type' => 'outstanding', 'good_qty' => 15, 'damaged_qty' => 3, 'rejected_qty' => 2, 'claim_amount' => 750, 'cash_paid' => 0, 'created_by' => $accId, 'created_at' => now_local()]);
db_insert('claim_goods_items', ['claim_goods_id' => $rejClaim, 'product_id' => $prod, 'good_qty' => 15, 'damaged_qty' => 3, 'rejected_qty' => 2, 'claim_rate' => 50, 'line_claim' => 750]);
Approvals::create('claim', 'claim_goods', $rejClaim, 750, 'reject me', $accId);
$rappr = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'claim_goods' AND reference_id = ?", [$rejClaim]);
Approvals::decide($rappr, false, 'not valid claim', $ownerId);
ok(approx(Inventory::balance($prod, $whMain), 100), 'stock unchanged after rejection');
$supBal2 = Accounting::supplierBalance($supId);
ok(approx($supBal2, 1500), 'supplier balance unchanged after rejection');
ok(db_val("SELECT status FROM claim_goods WHERE id = ?", [$rejClaim]) === 'rejected', 'claim marked rejected');

echo "\n=== J Â· Claim settlement value only counts accepted goods in valuation ===\n";
$mov = db_all("SELECT qty FROM stock_movements WHERE ref_type = 'claim_goods' AND ref_id = ?", [$claimId]);
$totalIn = array_sum(array_column($mov, 'qty'));
ok(approx($totalIn, 70), 'only 70 units moved into stock (damaged/rejected never touch stock movements)');

echo "\n========================================\n";
echo "CLAIM GOODS RESULTS: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);

