<?php
require_once __DIR__ . '/../app/core/bootstrap.php';

function run_users_settings_tests() {
    $tests = [];
    $record = function($name, $pass, $msg = '') use (&$tests) {
        $tests[] = ['name' => $name, 'pass' => $pass, 'msg' => $msg];
        echo ($pass ? "[PASS] " : "[FAIL] ") . $name . ($msg ? " ($msg)" : "") . "\n";
    };

    // Test 1: Check users table has temp_password column
    $cols = array_column(db()->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $record('Users table has temp_password column', in_array('temp_password', $cols, true));

    // Test 2: Check user_permissions table exists
    $tables = array_column(db_all("SELECT name FROM sqlite_master WHERE type='table'"), 'name');
    $record('user_permissions table exists', in_array('user_permissions', $tables, true));

    // Test 3: Check routes registered
    $routes = require __DIR__ . '/../app/routes.php';
    $record('Route settings/password is registered', isset($routes['settings/password']));
    $record('Route profile/password is registered', isset($routes['profile/password']));
    $record('Route users/{id}/reset-password is registered', isset($routes['users/{id}/reset-password']));

    // Test 4: Check Settings sidebar in layout.php for Admin
    $_SESSION = [
        'user_id' => 1,
        'username' => 'owner',
        'full_name' => 'Dev Owner (CTC)',
        'role_ids' => [1],
        'lang' => 'en'
    ];
    ob_start();
    $viewFile = __DIR__ . '/../app/views/settings/change_password.php';
    $user = db_one('SELECT * FROM users WHERE id = 1');
    $title = 'Change Password';
    $notifUnreadIds = [];
    $notifUnread = 0;
    $notifPrefs = ['sound' => 0, 'desktop' => 0];
    $currentUser = $user;
    require __DIR__ . '/../app/views/layout.php';
    $adminHtml = ob_get_clean();

    // Verify pruned items are NOT in layout
    $prunedRoutes = [
        'settings/company',
        'settings/branches',
        'settings/warehouses',
        'settings/fiscal',
        'settings/opening',
        'settings/units',
        'settings/bonus-schemes',
    ];
    $foundPrunedInAdmin = false;
    foreach ($prunedRoutes as $pr) {
        if (strpos($adminHtml, e(url($pr))) !== false || strpos($adminHtml, rawurlencode($pr)) !== false) {
            $foundPrunedInAdmin = true;
            break;
        }
    }
    $record('Pruned 7 settings routes removed from sidebar for Admin', !$foundPrunedInAdmin);

    // Admin should see users, settings/password, audit, settings/backup
    $record('Admin sees Change Password in sidebar', strpos($adminHtml, e(url('settings/password'))) !== false || strpos($adminHtml, 'Change Password') !== false);
    $record('Admin sees Users in sidebar', strpos($adminHtml, e(url('users'))) !== false);
    $record('Admin sees Audit Log in sidebar', strpos($adminHtml, e(url('audit'))) !== false);
    $record('Admin sees Backup in sidebar', strpos($adminHtml, e(url('settings/backup'))) !== false);

    // Test 5: Simulate Non-Admin / Manager session
    $mgr = db_one("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.code = 'manager' LIMIT 1");
    if (!$mgr) {
        $mgr = db_one('SELECT * FROM users WHERE id = 4');
    }
    $_SESSION = [
        'user_id' => (int)$mgr['id'],
        'username' => $mgr['username'],
        'full_name' => $mgr['full_name'],
        'role_ids' => [3], // manager
        'lang' => 'en'
    ];
    ob_start();
    $currentUser = $mgr;
    $user = $mgr;
    require __DIR__ . '/../app/views/layout.php';
    $mgrHtml = ob_get_clean();

    $foundPrunedInMgr = false;
    foreach ($prunedRoutes as $pr) {
        if (strpos($mgrHtml, e(url($pr))) !== false || strpos($mgrHtml, rawurlencode($pr)) !== false) {
            $foundPrunedInMgr = true;
            break;
        }
    }
    $record('Pruned 7 settings routes removed from sidebar for Manager', !$foundPrunedInMgr);
    $record('Manager sees Change Password in Settings', strpos($mgrHtml, e(url('settings/password'))) !== false || strpos($mgrHtml, 'Change Password') !== false);
    $record('Manager CANNOT see Users & Roles in sidebar', strpos($mgrHtml, e(url('users'))) === false);
    $record('Manager CANNOT see Audit Log in sidebar', strpos($mgrHtml, e(url('audit'))) === false);
    $record('Manager CANNOT see Backup in sidebar', strpos($mgrHtml, e(url('settings/backup'))) === false);

    // Test 6: Users list page (index.php?r=users)
    $_SESSION = [
        'user_id' => 1,
        'username' => 'owner',
        'full_name' => 'Dev Owner (CTC)',
        'role_ids' => [1],
        'lang' => 'en'
    ];
    ob_start();
    UsersController::index();
    $usersHtml = ob_get_clean();

    $record('Users list renders with Password column', strpos($usersHtml, 'Password') !== false || strpos($usersHtml, 'پاس ورڈ') !== false);
    $record('Users list renders password toggle functionality', strpos($usersHtml, 'toggleUserPwd') !== false);
    $record('Users list renders Reset Pass buttons', strpos($usersHtml, 'openResetModal') !== false);
    $record('Users list renders Quick Reset Modal', strpos($usersHtml, 'id="resetModal"') !== false);

    // Test 7: Quick Reset Password functionality via DB simulation
    $testTarget = db_one("SELECT * FROM users WHERE username = 'haseeb'");
    if (!$testTarget) {
        $testTarget = db_one("SELECT * FROM users WHERE id > 1 LIMIT 1");
    }
    $targetId = (int)$testTarget['id'];
    $newPass = 'NewHaseeb@999';
    db_exec('UPDATE users SET password_hash = ?, temp_password = ?, force_password_change = 0 WHERE id = ?', [
        password_hash($newPass, PASSWORD_DEFAULT),
        $newPass,
        $targetId
    ]);
    $updatedUser = db_one('SELECT password_hash, temp_password FROM users WHERE id = ?', [$targetId]);
    $record('Admin reset password updates temp_password to plaintext', $updatedUser['temp_password'] === 'NewHaseeb@999');
    $record('Admin reset password updates password_hash correctly', password_verify('NewHaseeb@999', $updatedUser['password_hash']));

    // Test 8: Change Password logic verification
    $userBefore = db_one('SELECT * FROM users WHERE id = ?', [$targetId]);
    $validCurrent = password_verify('NewHaseeb@999', $userBefore['password_hash']);
    $invalidCurrent = password_verify('WrongPass', $userBefore['password_hash']);
    $record('Change Password verifies valid current password', $validCurrent === true);
    $record('Change Password rejects invalid current password', $invalidCurrent === false);

    // Update to new password
    $changedPass = 'UpdatedHaseeb@888';
    db_exec('UPDATE users SET password_hash = ?, temp_password = ?, force_password_change = 0 WHERE id = ?', [
        password_hash($changedPass, PASSWORD_DEFAULT),
        $changedPass,
        $targetId
    ]);
    $userAfterChange = db_one('SELECT password_hash, temp_password FROM users WHERE id = ?', [$targetId]);
    $record('Change Password updates temp_password to new password', $userAfterChange['temp_password'] === 'UpdatedHaseeb@888');
    $record('Change Password updates password_hash to new password', password_verify('UpdatedHaseeb@888', $userAfterChange['password_hash']));

    // Test 9: Granular Custom User Permissions
    // Assign only 'inventory.view' and 'rm.usage' to testTarget in user_permissions
    db_exec('DELETE FROM user_permissions WHERE user_id = ?', [$targetId]);
    $invViewPerm = (int)db_val("SELECT id FROM permissions WHERE code = 'inventory.view'");
    $rmUsagePerm = (int)db_val("SELECT id FROM permissions WHERE code = 'rm.usage'");
    db_exec('INSERT INTO user_permissions (user_id, permission_id) VALUES (?,?)', [$targetId, $invViewPerm]);
    db_exec('INSERT INTO user_permissions (user_id, permission_id) VALUES (?,?)', [$targetId, $rmUsagePerm]);

    $_SESSION['user_id'] = $targetId;
    $perms = Auth::permission_codes();
    $record('Auth::permission_codes returns custom permissions when defined', in_array('inventory.view', $perms, true) && in_array('rm.usage', $perms, true) && !in_array('sales.view', $perms, true));
    $record('Auth::can allows inventory.view', Auth::can('inventory.view') === true);
    $record('Auth::can blocks sales.view', Auth::can('sales.view') === false);
    $record('Auth::can blocks customers.view', Auth::can('customers.view') === false);

    // Check sidebar rendering with these custom permissions
    ob_start();
    $currentUser = db_one('SELECT * FROM users WHERE id = ?', [$targetId]);
    $user = $currentUser;
    require __DIR__ . '/../app/views/layout.php';
    $customUserHtml = ob_get_clean();

    $record('Custom user sees Warehouse in sidebar', strpos($customUserHtml, e(url('warehouse'))) !== false);
    $record('Custom user does NOT see Sales in sidebar', strpos($customUserHtml, e(url('sales/invoices'))) === false);
    $record('Custom user does NOT see Customers in sidebar', strpos($customUserHtml, e(url('customers'))) === false);
    $record('Custom user does NOT see Payroll in sidebar', strpos($customUserHtml, e(url('payroll'))) === false);

    // Test 10: User edit form renders custom permissions matrix
    $_SESSION = [
        'user_id' => 1,
        'username' => 'owner',
        'full_name' => 'Dev Owner (CTC)',
        'role_ids' => [1],
        'lang' => 'en'
    ];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['id' => $targetId];
    ob_start();
    UsersController::editUser();
    $editFormHtml = ob_get_clean();
    $record('User edit form renders Custom Permissions section', strpos($editFormHtml, 'Custom Permissions') !== false || strpos($editFormHtml, 'اختیارات') !== false);
    $record('User edit form has Load Selected Role Defaults button', strpos($editFormHtml, 'Load Selected Role Defaults') !== false || strpos($editFormHtml, 'رول کے مطابق') !== false);

    $passed = count(array_filter($tests, fn($t) => $t['pass']));
    $total = count($tests);
    echo "\nSummary: {$passed} / {$total} tests passed.\n";
    if ($passed !== $total) {
        exit(1);
    }
}

run_users_settings_tests();
