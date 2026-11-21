<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "users cols: ";
foreach ($db->query('PRAGMA table_info(users)') as $c) echo $c['name'], ' ';
echo "\n\n";
$rows = $db->query("SELECT u.id, u.username, GROUP_CONCAT(r.code) AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.id");
foreach ($rows as $r) echo $r['id'], ' | ', $r['username'], ' | ', $r['roles'], "\n";
