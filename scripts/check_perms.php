<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';

$allPerms = db_all("SELECT code, module, description FROM permissions WHERE code LIKE '%expense%'");
print_r($allPerms);

$mgrPerms = db_all("SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = 4 AND p.code LIKE '%expense%'");
echo "Manager expense perms:\n";
print_r($mgrPerms);
