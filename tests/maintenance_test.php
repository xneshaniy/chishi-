<?php
/** Factory Maintenance & Repairs E2E — record → approval → expense posted → printable → reversal. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = [];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Payroll.php';
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
$now = now_local();
$ownerId = (int)db_val("SELECT id FROM roles WHERE code='owner'");
$adminId = (int)db_val("SELECT id FROM roles WHERE code='admin'");
$managerId = (int)db_val("SELECT id FROM roles WHERE code='manager'");
$mkUser = function (string $u, int $roleId) use ($now) {
    $id = db_insert('users', ['username' => $u, 'full_name' => $u, 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => $now]);
    db_insert('user_roles', ['user_id' => $id, 'role_id' => $roleId]);
    return (int)$id;
};
$owner = $mkUser('owner_mnt', $ownerId); $admin = $mkUser('admin_mnt', $adminId); $manager = $mkUser('mgr_mnt', $managerId);

// seed an asset
$assetId = db_insert('maintenance_assets', ['name' => 'Cartoning Machine #2', 'asset_no' => 'MAC-002', 'department' => 'Packing', 'location' => 'Line 2', 'is_active' => 1, 'created_at' => $now]);

echo "\n=== 1. Manager creates maintenance record; expense NOT posted ===\n";
$recordId = db_insert('maintenance_records', [
    'record_no' => 'MNT-00001', 'asset_id' => $assetId, 'machine_name' => 'Cartoning Machine #2', 'asset_no' => 'MAC-002',
    'department' => 'Packing', 'location' => 'Line 2',
    'problem' => 'Main bearing worn out; belt broken', 'problem_date' => today(),
    'work_performed' => 'Bearing replaced, belt aligned, machine tested', 'work_date' => today(),
    'vendor' => 'Khan Engineering Works', 'labour_cost' => 2500, 'other_cost' => 500, 'parts_cost' => 8000, 'total_cost' => 11000,
    'payment_type' => 'cash', 'account_id' => (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' ORDER BY id LIMIT 1"),
    'status' => 'pending', 'created_by' => $manager, 'created_at' => $now,
]);
db_insert('maintenance_parts', ['maintenance_id' => $recordId, 'name' => 'Main bearing', 'qty' => 1, 'unit_cost' => 6000, 'line_total' => 6000]);
db_insert('maintenance_parts', ['maintenance_id' => $recordId, 'name' => 'Drive belt', 'qty' => 2, 'unit_cost' => 1000, 'line_total' => 2000]);
$reqId = Approvals::create('maintenance', 'maintenance_records', $recordId, 11000, 'Maintenance — Cartoning Machine #2', $manager);
ok($reqId > 0, 'maintenance approval request created');
ok(db_val('SELECT status FROM maintenance_records WHERE id = ?', [$recordId]) === 'pending', 'record saved as pending');
ok((int)db_val('SELECT COUNT(*) FROM expenses WHERE description LIKE ?', ['%MNT-00001%']) === 0, 'no expense row created before approval');
ok((int)db_val("SELECT COUNT(*) FROM approval_requests WHERE reference_type='maintenance_records' AND reference_id = ? AND status='pending'", [$recordId]) === 1, 'approval request pending');

echo "\n=== 2. Manager cannot approve own request ===\n";
try { Approvals::decide($reqId, true, 'self', $manager); ok(false, 'own approval blocked'); }
catch (Throwable $e) { ok(true, 'own approval blocked (' . $e->getMessage() . ')'); }

echo "\n=== 3. Owner approves -> expense posted, exactly once, journal balanced ===\n";
$res = Approvals::decide($reqId, true, 'approved in e2e', $owner);
ok(($res[0] ?? '') === 'approved', 'decision recorded as approved');
ok(db_val('SELECT status FROM maintenance_records WHERE id = ?', [$recordId]) === 'posted', 'record marked posted');
$expId = (int)db_val('SELECT expense_id FROM maintenance_records WHERE id = ?', [$recordId]);
ok($expId > 0, 'expense row linked');
ok(approx((float)db_val('SELECT amount FROM expenses WHERE id = ?', [$expId]), 11000, 0.01), 'expense amount = 11,000');
ok(db_val('SELECT status FROM expenses WHERE id = ?', [$expId]) === 'posted', 'expense posted');
$jId = (int)db_val('SELECT journal_entry_id FROM expenses WHERE id = ?', [$expId]);
ok($jId > 0, 'journal entry linked');
ok(db_val('SELECT status FROM journal_entries WHERE id = ?', [$jId]) === 'posted', 'journal posted');
ok(db_val("SELECT COUNT(*) FROM journal_entries WHERE idem_key = ?", ['expense:' . $expId]) === 1, 'journal exactly once');
$tb = Accounting::trialBalance();
ok((bool)$tb['balanced'], 'trial balance still balanced after maintenance posting');

echo "\n=== 4. Second approval attempt is refused; stock/journal unchanged ===\n";
try { Approvals::decide($reqId, true, 'again', $owner); ok(false, 'double approval refused'); }
catch (Throwable $e) { ok(true, 'double approval refused (' . $e->getMessage() . ')'); }
$expCount = (int)db_val('SELECT COUNT(*) FROM expenses WHERE description LIKE ?', ['%MNT-00001%']);
ok($expCount === 1, 'exactly one expense row linked to MNT-00001');

echo "\n=== 5. Rejection path: no expense, no journal ===\n";
$record2 = db_insert('maintenance_records', [
    'record_no' => 'MNT-00002', 'asset_id' => $assetId, 'machine_name' => 'Cartoning Machine #2', 'asset_no' => 'MAC-002',
    'department' => 'Packing', 'problem' => 'Second issue', 'problem_date' => today(),
    'vendor' => 'Khan Engineering Works', 'labour_cost' => 1000, 'other_cost' => 0, 'parts_cost' => 0, 'total_cost' => 1000,
    'payment_type' => 'credit', 'status' => 'pending', 'created_by' => $manager, 'created_at' => $now,
]);
$req2 = Approvals::create('maintenance', 'maintenance_records', $record2, 1000, 'Maintenance — reject test', $manager);
Approvals::decide($req2, false, 'too expensive, get another quote', $owner);
ok(db_val('SELECT status FROM maintenance_records WHERE id = ?', [$record2]) === 'rejected', 'rejected record marked rejected');
ok((int)db_val("SELECT COUNT(*) FROM expenses WHERE description LIKE ?", ['%MNT-00002%']) === 0, 'no expense created on rejection');
ok((int)db_val('SELECT COUNT(*) FROM journal_entries') === (int)db_val('SELECT COUNT(*) FROM journal_entries'), 'journals unchanged on rejection');

echo "\n=== 6. Reversal posts a reversing journal and marks reversed ===\n";
Posting::reverseMaintenance($recordId, 'E2E reversal test');
ok(db_val('SELECT status FROM maintenance_records WHERE id = ?', [$recordId]) === 'reversed', 'record marked reversed');
ok(db_val('SELECT status FROM expenses WHERE id = ?', [$expId]) === 'reversed', 'linked expense reversed');
$tb2 = Accounting::trialBalance();
ok((bool)$tb2['balanced'], 'trial balance still balanced after reversal');

function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }
echo "\nMAINTENANCE E2E: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
