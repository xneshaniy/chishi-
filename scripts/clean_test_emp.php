<?php
$configPath = file_exists(__DIR__ . '/config/config.php') ? __DIR__ . '/config/config.php' : __DIR__ . '/../config/config.php';
$config = require $configPath;
$db = $config['db'];

$pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}", $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->exec("DELETE FROM employees WHERE code LIKE 'EMP-TEST-%'");
echo "Deleted test employee. Clean!\n";
