<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';

$mgrRole = (int)db_val("SELECT id FROM roles WHERE code = 'manager'");
$permsToGrant = ['company_expenses.create', 'company_expenses.view'];

foreach ($permsToGrant as $pCode) {
    $permId = (int)db_val("SELECT id FROM permissions WHERE code = ?", [$pCode]);
    if ($permId && $mgrRole) {
        db_exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$mgrRole, $permId]);
        echo "Granted $pCode to Manager\n";
    }
}
