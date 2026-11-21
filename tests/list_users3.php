<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ($db->query("SELECT id, username, full_name, is_active FROM users") as $r) echo $r['id'], ' | ', $r['username'], ' | ', $r['full_name'], ' | active=', $r['is_active'], "\n";
echo "roles:\n";
foreach ($db->query("SELECT ur.user_id, r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id") as $r) echo $r['user_id'], ' -> ', $r['code'], "\n";
