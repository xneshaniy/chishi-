<?php
/** Capital (Owner Capital/Drawings) + Other Income: in-memory integration test. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average'];
$_SESSION = ['user_id' => 2]; // user 2 = admin (id 2 in seed? let's use 1 owner = seeded owner id)

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Notifications.php';
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

// create owner + admin users for the test (seed_base does not create logins)
$ownerRole = (int)db_val("SELECT id FROM roles WHERE code = 'owner'");
$ownerId = (int)db_insert('users', ['username' => 'owner', 'full_name' => 'Test Owner', 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => now_local()]);
if ($ownerRole) db_insert('user_roles', ['user_id' => $ownerId, 'role_id' => $ownerRole]);
$adminRole = (int)db_val("SELECT id FROM roles WHERE code = 'admin'");
$adminId = (int)db_insert('users', ['username' => 'admin', 'full_name' => 'Test Admin', 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => now_local()]);
if ($adminRole) db_insert('user_roles', ['user_id' => $adminId, 'role_id' => $adminRole]);
ok($ownerId > 0 && $adminId > 0, 'owner + admin users created (seed does not create logins)');
$cashGL = (int)db_val("SELECT id FROM chart_of_accounts WHERE system_key = 'CASH'");
$cashId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'cash' LIMIT 1");
$capId = (int)db_val("SELECT id FROM chart_of_accounts WHERE system_key = 'CAPITAL'");
$drawId = (int)db_val("SELECT id FROM chart_of_accounts WHERE system_key = 'DRAWINGS'");
$oiId = (int)db_val("SELECT id FROM chart_of_accounts WHERE system_key = 'OTHER_INCOME'");
ok($cashId > 0 && $capId > 0 && $drawId > 0 && $oiId > 0, 'core GL accounts exist');

echo "\n=== 1. Permissions seeded for capital/income ===\n";
$capPerms = $pdo->query("SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.code = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('capital.view', $capPerms, true), 'admin has capital.view');
ok(in_array('income.view', $capPerms, true) && in_array('income.create', $capPerms, true), 'admin has income perms');
$mgrPerms = $pdo->query("SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.code = 'manager'")->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('income.view', $mgrPerms, true) && in_array('income.create', $mgrPerms, true), 'manager has income perms');
ok(!in_array('capital.view', $mgrPerms, true), 'manager does NOT have capital.view');

echo "\n=== 2. Capital investment posts immediately and balances ===\n";
$before = (float)db_val("SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE l.account_id = $cashId AND e.status IN ('posted','reversed')");
$jid = Posting::postCapitalInvestment(250000, $cashId, today(), '', 'Owner injected working capital', $ownerId);
$e = db_one("SELECT * FROM journal_entries WHERE id = ?", [$jid]);
ok($e['reference_type'] === 'capital_investment', 'entry tagged as capital_investment');
ok($e['status'] === 'posted', 'entry posted');
$capCredit = (float)db_val("SELECT COALESCE(SUM(credit - debit),0) FROM journal_lines l WHERE l.entry_id = ? AND l.account_id = ?", [$jid, $capId]);
$cashDebit = (float)db_val("SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines l WHERE l.entry_id = ? AND l.account_id = ?", [$jid, $cashGL]);
ok(abs($capCredit - 250000) < 0.01, 'Owner Capital credited 250,000');
ok(abs($cashDebit - 250000) < 0.01, 'Cash GL account debited 250,000');
ok(db_val('SELECT COUNT(*) FROM journal_lines WHERE entry_id = ?', [$jid]) == 2, 'balanced 2-line entry');

echo "\n=== 3. Capital drawing posts immediately and balances ===\n";
$jid2 = Posting::postCapitalDrawing(75000, $cashId, today(), '', 'Owner took cash for personal use', $ownerId);
$dr = (float)db_val("SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines l WHERE l.entry_id = ? AND l.account_id = ?", [$jid2, $drawId]);
ok(abs($dr - 75000) < 0.01, 'Owner Drawings debited 75,000');
ok(db_val("SELECT reference_type FROM journal_entries WHERE id = ?", [$jid2]) === 'capital_drawing', 'entry tagged as capital_drawing');

echo "\n=== 4. Other income: submit pending, approve exactly once, reverse ===\n";
$iid = (int)db_insert('other_income', ['income_no' => 'TMP-TEST-001', 'category' => 'rent', 'date' => today(), 'amount' => 50000, 'payer' => 'Ahmed Plaza', 'description' => 'Shop rent Nov', 'payment_type' => 'cash', 'account_id' => $cashId, 'status' => 'pending', 'created_by' => $ownerId, 'created_at' => now_local()]);
$aid = Approvals::create('other_income', 'other_income', $iid, 50000, 'Other income — Shop rent Nov', $ownerId);
ok($aid > 0, 'approval request created');
ok(db_val('SELECT status FROM approval_requests WHERE id = ?', [$aid]) === 'pending', 'approval pending');
// Re-request = no-op
$aid2 = Approvals::create('other_income', 'other_income', $iid, 50000, 're-request', $ownerId);
ok($aid2 === $aid, 're-request returns same pending approval (no duplicate)');
// Decide: owner cannot approve own request — have a different admin? use decide by another user id
$adminId = (int)db_val("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
try {
    Approvals::decide($aid, true, 'should fail self-approve', $ownerId);
    ok(false, 'owner self-approval blocked');
} catch (Throwable $e) {
    ok(stripos($e->getMessage(), 'yourself') !== false || stripos($e->getMessage(), 'own request') !== false, 'owner self-approval blocked: ' . $e->getMessage());
}
[$action, $refId] = Approvals::decide($aid, true, 'Approved from test', $adminId ?: $ownerId + 100);
ok($action === 'approved', 'income approved (not by requester)');
ok(db_val('SELECT status FROM other_income WHERE id = ?', [$iid]) === 'posted', 'other_income marked posted');
$oiCredit = (float)db_val("SELECT COALESCE(SUM(credit - debit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.reference_type='other_income' AND l.account_id = ? AND e.status IN ('posted','reversed')", [$oiId]);
ok(abs($oiCredit - 50000) < 0.01, 'Other Income account credited 50,000');
// exactly once
Posting::postOtherIncome($iid);
$count = (int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type='other_income' AND reference_id = ?", [$iid]);
ok($count === 1, 'posting exactly once — no duplicate journal');

echo "\n=== 5. Rejected income posts nothing ===\n";
$iid2 = (int)db_insert('other_income', ['income_no' => 'TMP-TEST-002', 'category' => 'scrap', 'date' => today(), 'amount' => 8000, 'payer' => 'Scrap dealer', 'description' => 'Scrap sale', 'payment_type' => 'cash', 'account_id' => $cashId, 'status' => 'pending', 'created_by' => $ownerId, 'created_at' => now_local()]);
$aid3 = Approvals::create('other_income', 'other_income', $iid2, 8000, 'Scrap sale', $ownerId);
[$action3] = Approvals::decide($aid3, false, 'Not genuine income', $adminId ?: $ownerId + 100);
ok($action3 === 'rejected', 'income rejected');
ok(db_val('SELECT status FROM other_income WHERE id = ?', [$iid2]) === 'rejected', 'other_income marked rejected');
ok((int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type='other_income' AND reference_id = ?", [$iid2]) === 0, 'no journal posted for rejected income');

echo "\n========================================\nCAPITAL & INCOME TEST RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
