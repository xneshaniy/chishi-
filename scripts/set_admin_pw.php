<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
$hash = password_hash('admin123', PASSWORD_DEFAULT);
db_exec("UPDATE users SET password_hash = ? WHERE username IN ('owner', 'admin1', 'haseeb')", [$hash]);
echo "Updated owner, admin1, and haseeb password to admin123\n";
