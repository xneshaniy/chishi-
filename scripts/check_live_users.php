<?php
header('Content-Type: text/plain');
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../app/core/Database.php';

$users = db_all("SELECT u.id, u.username, u.full_name, u.is_active, GROUP_CONCAT(r.code SEPARATOR ',') as roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id");
print_r($users);
