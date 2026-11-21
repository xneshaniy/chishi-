<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $db->query("SELECT u.id, u.username, u.name, GROUP_CONCAT(r.code) AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.id");
foreach ($rows as $r) echo $r['id'], ' | ', $r['username'], ' | ', $r['name'], ' | ', $r['roles'], "\n";
