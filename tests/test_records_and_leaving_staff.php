<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/RecordsController.php';

function run_records_tests() {
    $tests = [];
    $record = function($name, $pass, $msg = '') use (&$tests) {
        $tests[] = ['name' => $name, 'pass' => $pass, 'msg' => $msg];
        echo ($pass ? "[PASS] " : "[FAIL] ") . $name . ($msg ? " ($msg)" : "") . "\n";
    };

    // Test 1: Check database columns
    $smCols = array_column(db()->query('PRAGMA table_info(salesmen)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $empCols = array_column(db()->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $record('salesmen table has left_date column', in_array('left_date', $smCols, true));
    $record('salesmen table has departure_reason column', in_array('departure_reason', $smCols, true));
    $record('employees table has left_date column', in_array('left_date', $empCols, true));
    $record('employees table has departure_reason column', in_array('departure_reason', $empCols, true));

    // Test 2: Check routes registered
    $routes = require __DIR__ . '/../app/routes.php';
    $record('Route records is registered', isset($routes['records']));
    $record('Route records/mark-left is registered', isset($routes['records/mark-left']));
    $record('Route records/restore is registered', isset($routes['records/restore']));
    $record('Route records/delete is registered', isset($routes['records/delete']));

    // Test 3: Check sidebar navigation visibility for Admin/Owner vs Non-Admin
    // Admin/Owner session
    $_SESSION = [
        'user_id' => 1,
        'username' => 'owner',
        'full_name' => 'Dev Owner (CTC)',
        'role_ids' => [1],
        'lang' => 'en'
    ];
    ob_start();
    $currentUser = db_one('SELECT * FROM users WHERE id = 1');
    $user = $currentUser;
    $title = 'Company Records';
    $notifUnreadIds = [];
    $notifUnread = 0;
    $notifPrefs = ['sound' => 0, 'desktop' => 0];
    $viewFile = __DIR__ . '/../app/views/records/index.php';
    $tab = 'salesmen';
    $salesmen = [];
    $employees = [];
    $masters = [];
    $archivedSalesmenCount = 0;
    $archivedEmployeesCount = 0;
    $activeSalesmenCount = 0;
    $activeEmployeesCount = 0;
    require __DIR__ . '/../app/views/layout.php';
    $adminHtml = ob_get_clean();

    $record('Admin sees Records (ریکارڈ) section in sidebar', strpos($adminHtml, 'Records') !== false || strpos($adminHtml, 'ریکارڈ') !== false);
    $record('Admin sees Records route link in sidebar', strpos($adminHtml, e(url('records'))) !== false);

    // Non-Admin (Manager) session on dashboard
    $mgr = db_one("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.code = 'manager' LIMIT 1");
    if (!$mgr) $mgr = db_one('SELECT * FROM users WHERE id = 4');
    $_SESSION = [
        'user_id' => (int)$mgr['id'],
        'username' => $mgr['username'],
        'full_name' => $mgr['full_name'],
        'role_ids' => [3],
        'lang' => 'en'
    ];
    ob_start();
    $currentUser = $mgr;
    $user = $mgr;
    $viewFile = __DIR__ . '/../app/views/settings/change_password.php';
    require __DIR__ . '/../app/views/layout.php';
    $mgrHtml = ob_get_clean();

    $record('Manager CANNOT see Records link in sidebar', strpos($mgrHtml, e(url('records'))) === false);

    // Test 4: Create a test salesman and mark as left
    $testSmCode = 'SM-TEST-' . time();
    $testSmId = db_insert('salesmen', [
        'code' => $testSmCode,
        'name' => 'Departing Salesman Test',
        'phone' => '03001234567',
        'is_active' => 1,
        'is_archived' => 0,
        'join_date' => '2026-01-01',
        'created_at' => now_local()
    ]);

    // Active query should include this salesman
    $activeBefore = (int)db_val('SELECT COUNT(*) FROM salesmen WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$testSmId]);
    $record('Test salesman is initially active and unarchived', $activeBefore === 1);

    // Mark as left via DB update (simulating RecordsController::markLeft)
    $leaveDate = '2026-11-20';
    $leaveReason = 'Resigned and joined another firm';
    db_exec("UPDATE salesmen SET is_archived = 1, is_active = 0, left_date = ?, departure_reason = ?, deactivated_at = ?, deactivated_by = 1 WHERE id = ?", [
        $leaveDate,
        $leaveReason,
        now_local(),
        $testSmId
    ]);

    // Active query should NOT include this salesman anymore!
    $activeAfter = (int)db_val('SELECT COUNT(*) FROM salesmen WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$testSmId]);
    $record('Test salesman is removed from active salesmen query after leaving', $activeAfter === 0);

    // Records query should find this salesman in archived records!
    $archivedSm = db_one('SELECT * FROM salesmen WHERE id = ? AND COALESCE(is_archived, 0) = 1', [$testSmId]);
    $record('Test salesman appears in archived records', (bool)$archivedSm);
    $record('Test salesman has correct left_date preserved', $archivedSm['left_date'] === $leaveDate);
    $record('Test salesman has departure_reason preserved', $archivedSm['departure_reason'] === $leaveReason);

    // Restore salesman back to active service
    db_exec("UPDATE salesmen SET is_archived = 0, is_active = 1, left_date = NULL, departure_reason = NULL, deactivated_at = NULL, deactivated_by = NULL WHERE id = ?", [$testSmId]);
    $activeRestored = (int)db_val('SELECT COUNT(*) FROM salesmen WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$testSmId]);
    $record('Test salesman is restored to active status', $activeRestored === 1);

    // Clean up test salesman
    db_exec('DELETE FROM salesmen WHERE id = ?', [$testSmId]);

    // Test 5: Create a test employee and mark as left
    $testEmpCode = 'EMP-TEST-' . time();
    $testEmpId = db_insert('employees', [
        'code' => $testEmpCode,
        'name' => 'Departing Staff Test',
        'designation' => 'Labour',
        'department' => 'Factory Packing',
        'salary_rate' => 25000,
        'is_active' => 1,
        'is_archived' => 0,
        'join_date' => '2026-02-01',
        'created_at' => now_local()
    ]);

    $empActiveBefore = (int)db_val('SELECT COUNT(*) FROM employees WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$testEmpId]);
    $record('Test employee is initially active and unarchived', $empActiveBefore === 1);

    // Mark employee as left
    $empLeaveReason = 'Left for personal family reasons';
    db_exec("UPDATE employees SET is_archived = 1, is_active = 0, left_date = ?, departure_reason = ?, deactivated_at = ?, deactivated_by = 1 WHERE id = ?", [
        $leaveDate,
        $empLeaveReason,
        now_local(),
        $testEmpId
    ]);

    $empActiveAfter = (int)db_val('SELECT COUNT(*) FROM employees WHERE id = ? AND is_active = 1 AND COALESCE(is_archived, 0) = 0', [$testEmpId]);
    $record('Test employee is removed from active employee query after leaving', $empActiveAfter === 0);

    $archivedEmp = db_one('SELECT * FROM employees WHERE id = ? AND COALESCE(is_archived, 0) = 1', [$testEmpId]);
    $record('Test employee appears in archived records', (bool)$archivedEmp);
    $record('Test employee has correct left_date preserved', $archivedEmp['left_date'] === $leaveDate);
    $record('Test employee has departure_reason preserved', $archivedEmp['departure_reason'] === $empLeaveReason);

    // Clean up test employee
    db_exec('DELETE FROM employees WHERE id = ?', [$testEmpId]);

    // Test 6: Records index view renders cleanly
    $_SESSION = [
        'user_id' => 1,
        'username' => 'owner',
        'full_name' => 'Dev Owner (CTC)',
        'role_ids' => [1],
        'lang' => 'en'
    ];
    ob_start();
    RecordsController::index();
    $recordsPageHtml = ob_get_clean();

    $record('Records index page renders Company Records title', strpos($recordsPageHtml, 'Company Records') !== false || strpos($recordsPageHtml, 'کمپنی ریکارڈ') !== false);
    $record('Records index page has Departed Salesmen tab', strpos($recordsPageHtml, 'Departed Salesmen') !== false || strpos($recordsPageHtml, 'سیلزمین ریکارڈ') !== false);
    $record('Records index page has Departed Employees tab', strpos($recordsPageHtml, 'tab=employees') !== false);

    $passed = count(array_filter($tests, fn($t) => $t['pass']));
    $total = count($tests);
    echo "\nSummary: {$passed} / {$total} tests passed.\n";
    if ($passed !== $total) {
        exit(1);
    }
}

run_records_tests();
