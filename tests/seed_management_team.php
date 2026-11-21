<?php
/** Create/link dev-only demo management accounts for testing (dev DB only, excluded from ZIP). */
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite:' . __DIR__ . '/../storage/dev.sqlite']];
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/services/ManagementTeam.php';

// 1) Dev Accountant One — create full profile if absent
$existing = db_val("SELECT id FROM users WHERE username = 'accountant1'");
if ($existing) {
    echo "accountant1 already exists, linking employee\n";
    ManagementTeam::ensureEmployeeLink((int)$existing, 'Senior Accountant');
} else {
    $empId = ManagementTeam::createProfile([
        'username' => 'accountant1',
        'full_name' => 'Dev Accountant One',
        'role' => 'accountant',
        'password' => 'Accountant@1234x',
        'cnic' => '35202-1234567-1',
        'phone' => '03001112233',
        'designation' => 'Senior Accountant',
        'department' => 'Accounts',
        'join_date' => '2026-02-01',
        'salary_type' => 'monthly',
        'salary_rate' => 45000,
        'payment_method' => 'bank',
    ]);
    echo "accountant1 created, employee link id: $empId\n";
}

// 2) manager1 already exists — link his user to a management profile with a salary
$m = db_one("SELECT u.id, u.full_name FROM users u WHERE u.username = 'manager1'");
$empId = ManagementTeam::ensureEmployeeLink((int)$m['id'], 'Manager');
db_exec("UPDATE employees SET designation = 'Operations Manager', department = 'Operations', salary_type = 'monthly', salary_rate = 55000, payment_method = 'bank', join_date = '2025-11-01' WHERE id = ?", [$empId]);
echo "manager1 linked to employee id: $empId\n";

// 3) show the roster
foreach (ManagementTeam::listProfiles() as $p) {
    echo $p['employee_id'], ' | ', $p['username'], ' | ', $p['full_name'], ' | ', $p['role_code'], ' | salary ', $p['salary_type'], ' ', $p['salary_rate'], "\n";
}
