<?php
/** Employee Section: in-memory schema, daily attendance upsert + audit, unique-per-day guarantee, report aggregates. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average'];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/StaffAttendance.php';
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

$now = now_local();
$e1 = (int)db_insert('employees', ['code' => 'EMP-T001', 'name' => 'Ahmed Khan', 'cnic' => '42101-1234567-1', 'phone' => '0300-1111111', 'designation' => 'Machine Operator', 'department' => 'Production', 'join_date' => '2026-01-01', 'salary_type' => 'monthly', 'salary_rate' => 30000, 'is_active' => 1, 'created_at' => $now]);
$e2 = (int)db_insert('employees', ['code' => 'EMP-T002', 'name' => 'Bilal Butt', 'cnic' => '42101-7654321-2', 'phone' => '0300-2222222', 'designation' => 'Technician', 'department' => 'Maintenance', 'join_date' => '2026-02-01', 'salary_type' => 'monthly', 'salary_rate' => 35000, 'is_active' => 1, 'created_at' => $now]);

echo "=== 1. Schema: new tables exist with unique constraint ===\n";
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='employee_documents'") == 1, 'employee_documents table created');
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='staff_attendance'") == 1, 'staff_attendance table created');
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='staff_attendance_audit'") == 1, 'staff_attendance_audit table created');

echo "\n=== 2. Initial marks ===\n";
[$ok1] = StaffAttendance::mark($e1, '2026-11-14', 'present', 1);
[$ok2] = StaffAttendance::mark($e2, '2026-11-14', 'absent', 1);
ok($ok1 && $ok2, 'marked present and absent');
ok(db_val('SELECT COUNT(*) FROM staff_attendance WHERE date = ?', ['2026-11-14']) == 2, 'two attendance rows for the date');

echo "\n=== 3. Unique-per-day: correction updates, never duplicates ===\n";
$dupBefore = (int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ? AND date = ?', [$e1, '2026-11-14']);
[$ok3] = StaffAttendance::mark($e1, '2026-11-14', 'leave', 1, 'Sick leave granted');
ok($ok3, 'correction accepted');
$dupAfter = (int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ? AND date = ?', [$e1, '2026-11-14']);
ok($dupAfter === $dupBefore, 'still exactly one row after correction');
ok(db_val('SELECT status FROM staff_attendance WHERE employee_id = ? AND date = ?', [$e1, '2026-11-14']) === 'leave', 'status updated to leave');

echo "\n=== 4. Audit trail on create and correct ===\n";
ok(db_val('SELECT COUNT(*) FROM staff_attendance_audit') == 3, 'three audit rows (2 initial + 1 correction)');
$audit = db_one("SELECT * FROM staff_attendance_audit WHERE employee_id = ? ORDER BY id DESC LIMIT 1", [$e1]);
ok($audit['old_status'] === 'present' && $audit['new_status'] === 'leave', 'correction audit has before/after status');
ok((int)$audit['changed_by'] === 1, 'audit records who made the change');

echo "\n=== 5. No-op remark creates no audit row ===\n";
$auditBefore = (int)db_val('SELECT COUNT(*) FROM staff_attendance_audit');
[$ok4] = StaffAttendance::mark($e1, '2026-11-14', 'leave', 1);
ok($ok4 && db_val('SELECT COUNT(*) FROM staff_attendance_audit') == $auditBefore, 'same-status remark writes nothing');

echo "\n=== 6. Validation ===\n";
[$badStatus] = StaffAttendance::mark($e1, '2026-11-14', 'sleeping', 1);
ok(!$badStatus, 'invalid status rejected');
[$badEmp] = StaffAttendance::mark(99999, '2026-11-14', 'present', 1);
ok(!$badEmp, 'unknown employee rejected');

echo "\n=== 7. Day totals and report aggregates ===\n";
$tot = StaffAttendance::dayTotals('2026-11-14');
ok($tot['present'] === 0 && $tot['absent'] === 1 && $tot['leave'] === 1, 'day totals reflect final states');
$rep = StaffAttendance::report('2026-11-01', '2026-11-30');
ok(($rep[$e1]['leave'] ?? 0) === 1 && ($rep[$e2]['absent'] ?? 0) === 1, 'monthly report counts per employee per status');

echo "\n=== 8. Employee columns (photo / cnic / address) exist ===\n";
$cols = array_column($pdo->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['photo_path', 'cnic_front_path', 'cnic_back_path', 'address'] as $c) ok(in_array($c, $cols, true), "employees.$c exists");

echo "\n=== 9. Employee totals for profile ===\n";
$t = StaffAttendance::employeeTotals($e1);
ok($t['present'] === 0 && $t['leave'] === 1, 'employee-level totals correct');

echo "\n========================================\nSTAFF SECTION TEST RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
