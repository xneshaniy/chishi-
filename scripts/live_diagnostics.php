<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';

echo "=== LIVE USERS & ROLES ===\n";
$users = db_all("SELECT u.id, u.username, u.full_name, u.is_active, GROUP_CONCAT(r.code SEPARATOR ', ') as roles 
                 FROM users u 
                 LEFT JOIN user_roles ur ON ur.user_id = u.id 
                 LEFT JOIN roles r ON r.id = ur.role_id 
                 GROUP BY u.id");
foreach ($users as $u) {
    echo "ID: {$u['id']} | User: {$u['username']} | Name: {$u['full_name']} | Active: {$u['is_active']} | Roles: {$u['roles']}\n";
}

echo "\n=== MANAGER PERMISSIONS ===\n";
$mgrRole = db_one("SELECT id, code, name FROM roles WHERE code = 'manager'");
if ($mgrRole) {
    $perms = db_all("SELECT p.code, p.module, p.description 
                     FROM role_permissions rp 
                     JOIN permissions p ON p.id = rp.permission_id 
                     WHERE rp.role_id = ? 
                     ORDER BY p.module, p.code", [$mgrRole['id']]);
    echo "Total permissions for Manager: " . count($perms) . "\n";
    $byModule = [];
    foreach ($perms as $p) {
        $byModule[$p['module']][] = $p['code'];
    }
    foreach ($byModule as $mod => $codeList) {
        echo "  [$mod]: " . implode(', ', $codeList) . "\n";
    }
}

echo "\n=== APPROVAL REQUESTS IN DB ===\n";
$appr = db_all("SELECT ar.*, u.username as requester FROM approval_requests ar LEFT JOIN users u ON u.id = ar.requested_by ORDER BY ar.id DESC LIMIT 10");
echo "Count: " . count($appr) . "\n";
foreach ($appr as $a) {
    echo "ID: {$a['id']} | Type: {$a['type']} | Ref: {$a['reference_type']}#{$a['reference_id']} | Amount: {$a['amount']} | Status: {$a['status']} | By: {$a['requester']}\n";
}

echo "\n=== SYSTEM STATS ===\n";
echo "Suppliers: " . db_val("SELECT COUNT(*) FROM suppliers") . "\n";
echo "Products: " . db_val("SELECT COUNT(*) FROM products") . "\n";
echo "Stock Balances: " . db_val("SELECT COUNT(*) FROM stock_balances") . "\n";
echo "Purchase Invoices: " . db_val("SELECT COUNT(*) FROM purchase_invoices") . "\n";
echo "Journal Entries: " . db_val("SELECT COUNT(*) FROM journal_entries") . "\n";
