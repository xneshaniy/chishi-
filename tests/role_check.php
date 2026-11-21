<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "roles:\n";
foreach ($db->query("SELECT code, name FROM roles ORDER BY id") as $r) echo $r['code'], ' | ', $r['name'], "\n";
echo "\npurchasing/inventory perms granted to accountant:\n";
foreach ($db->query("SELECT p.code, p.module FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.code = 'accountant' AND p.module IN ('purchase','inventory','reports') ORDER BY p.module, p.code") as $r) echo $r['module'], '.', $r['code'], "\n";
