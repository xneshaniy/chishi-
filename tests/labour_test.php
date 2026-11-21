<?php
/** Labour module: attendance + salary changes + deductions (in-memory integration). */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average'];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Notifications.php';
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

echo "\n=== 1. Labour schema tables exist ===\n";
foreach (['staff_leaves', 'salary_changes', 'employee_deductions', 'staff_attendance', 'staff_attendance_audit'] as $t) {
    ok((bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='$t'")->fetchColumn(), "table $t exists (staff_leaves kept for historical records)");
}

echo "\n=== 2. Labour employees are stored in employees with kind=labour (no separate record) ===\n";
$labourId = (int)db_insert('employees', ['code' => 'LAB-T001', 'name' => 'Test Labour One', 'phone' => '03000000001', 'designation' => 'Loader', 'department' => 'Factory', 'join_date' => '2025-01-01', 'kind' => 'labour', 'salary_type' => 'daily', 'salary_rate' => 1200, 'is_active' => 1, 'created_at' => now_local()]);
$empId = (int)db_insert('employees', ['code' => 'EMP-T001', 'name' => 'Test Employee One', 'kind' => 'employee', 'salary_type' => 'monthly', 'salary_rate' => 40000, 'is_active' => 1, 'created_at' => now_local()]);
ok($labourId > 0 && $empId > 0, 'labour + employee records created in shared employees table');
ok(db_val('SELECT COUNT(*) FROM employees WHERE code = ?', ['LAB-T001']) == 1, 'one record per worker (no duplicates)');

echo "\n=== 3. One attendance mark per worker per date (unique) + audit ===\n";
[$ok1] = StaffAttendance::mark($labourId, '2026-11-14', 'present', 1, '');
ok($ok1, 'mark present saved');
[$ok2] = StaffAttendance::mark($labourId, '2026-11-14', 'present', 1, '');
ok($ok2, 're-mark present accepted as no-op');
ok((int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ? AND date = ?', [$labourId, '2026-11-14']) === 1, 'still exactly one row for the day');
$auditBefore = (int)db_val('SELECT COUNT(*) FROM staff_attendance_audit WHERE employee_id = ?', [$labourId]);
[$ok3] = StaffAttendance::mark($labourId, '2026-11-14', 'half_day', 1, 'corrected by admin');
ok($ok3, 'correction allowed');
ok((int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ? AND date = ?', [$labourId, '2026-11-14']) === 1, 'correction updates the same row');
ok(db_val('SELECT status FROM staff_attendance WHERE employee_id = ? AND date = ?', [$labourId, '2026-11-14']) === 'half_day', 'status updated to half_day');
ok((int)db_val('SELECT COUNT(*) FROM staff_attendance_audit WHERE employee_id = ?', [$labourId]) === $auditBefore + 1, 'correction wrote an audit row');
ok((int)db_val("SELECT COUNT(*) FROM staff_attendance_audit WHERE employee_id = ? AND old_status = 'present' AND new_status = 'half_day'", [$labourId]) === 1, 'audit captured old→new status');

echo "\n=== 4. Invalid status / missing worker rejected ===\n";
[$okBad] = StaffAttendance::mark($labourId, '2026-11-15', 'banana', 1, '');
ok(!$okBad, 'invalid status rejected');
[$okGone] = StaffAttendance::mark(999999, '2026-11-15', 'present', 1, '');
ok(!$okGone, 'unknown worker rejected');

echo "\n=== 5. Labour marks: present / absent / half_day only (no leave status) ===\n";
StaffAttendance::mark($labourId, '2026-11-02', 'present', 1, '');
StaffAttendance::mark($labourId, '2026-11-03', 'present', 1, '');
StaffAttendance::mark($labourId, '2026-11-04', 'absent', 1, '');
$t = StaffAttendance::employeeTotals($labourId);
ok($t['present'] === 2 && $t['absent'] === 1 && $t['half_day'] === 1, 'employeeTotals present=2 absent=1 half_day=1');
ok(StaffAttendance::validStatus('present') && StaffAttendance::validStatus('absent') && StaffAttendance::validStatus('half_day'), 'labour statuses are the three allowed');
$d = StaffAttendance::dayTotals('2026-11-14');
ok($d['half_day'] === 1, 'dayTotals counts half_day');
$cntAll = (int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ?', [$labourId]);
ok($cntAll === 4, 'exactly the seeded marks exist for the worker');

echo "\n=== 6. Salary change: rate updated + history row with effective date ===\n";
$oldRate = (float)db_val('SELECT salary_rate FROM employees WHERE id = ?', [$labourId]);
Database::transaction(function () use ($labourId, $oldRate) {
    db_exec("UPDATE employees SET salary_type = 'monthly', salary_rate = ? WHERE id = ?", [21000, $labourId]);
    db_insert('salary_changes', ['employee_id' => $labourId, 'salary_type' => 'monthly', 'old_rate' => $oldRate, 'new_rate' => 21000, 'effective_date' => '2026-12-01', 'note' => 'test raise', 'created_by' => 1, 'created_at' => now_local()]);
});
ok(abs((float)db_val('SELECT salary_rate FROM employees WHERE id = ?', [$labourId]) - 21000) < 0.01, 'employee salary_rate updated');
ok(db_val('SELECT salary_type FROM employees WHERE id = ?', [$labourId]) === 'monthly', 'salary type updated');
$sc = db_one('SELECT * FROM salary_changes WHERE employee_id = ?', [$labourId]);
ok(abs((float)$sc['old_rate'] - $oldRate) < 0.01 && abs((float)$sc['new_rate'] - 21000) < 0.01, 'history row stores old and new rate');
ok($sc['effective_date'] === '2026-12-01', 'effective date recorded');

echo "\n=== 7. Deduction stored and linked to worker ===\n";
$dedId = (int)db_insert('employee_deductions', ['employee_id' => $labourId, 'date' => '2026-11-14', 'reason' => 'test deduction', 'amount' => 750, 'approved_by' => 1, 'created_by' => 1, 'created_at' => now_local()]);
ok($dedId > 0, 'deduction stored');
ok(abs((float)db_val('SELECT COALESCE(SUM(amount),0) FROM employee_deductions WHERE employee_id = ?', [$labourId]) - 750) < 0.01, 'deductions total per worker correct');

echo "\n=== 8. Statement joins: attendance/deductions/salary-changes all resolve for the worker ===\n";
$cnt = 0;
$cnt += (int)db_val('SELECT COUNT(*) FROM staff_attendance WHERE employee_id = ?', [$labourId]) > 0 ? 1 : 0;
$cnt += (int)db_val('SELECT COUNT(*) FROM employee_deductions WHERE employee_id = ?', [$labourId]) > 0 ? 1 : 0;
$cnt += (int)db_val('SELECT COUNT(*) FROM salary_changes WHERE employee_id = ?', [$labourId]) > 0 ? 1 : 0;
ok($cnt === 3, 'attendance + deductions + salary changes linked to worker');

echo "\n========================================\nLABOUR TEST RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
