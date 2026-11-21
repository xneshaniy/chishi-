<?php
$raw = file_get_contents('database/schema.sqlite.sql');
$i = strpos($raw, 'CREATE TABLE expenses');
$end = strpos($raw, ');', $i) + 2;
$block = substr($raw, $i, $end - $i);
// strip comments entirely and try
$block2 = preg_replace('/--[^\n]*/', '', $block);
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE chart_of_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, type VARCHAR(12) NOT NULL, parent_id INT NULL, is_system INTEGER NOT NULL DEFAULT 0, allow_posting INTEGER NOT NULL DEFAULT 1, system_key VARCHAR(40) NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec($block2);
    echo "expenses OK (comments stripped)\n";
} catch (Throwable $e) {
    echo "ERR (comments stripped): ", $e->getMessage(), "\n";
}
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE chart_of_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, type VARCHAR(12) NOT NULL, parent_id INT NULL, is_system INTEGER NOT NULL DEFAULT 0, allow_posting INTEGER NOT NULL DEFAULT 1, system_key VARCHAR(40) NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec($block);
    echo "expenses OK\n";
} catch (Throwable $e) {
    echo "ERR: ", $e->getMessage(), "\n";
}
// print exact bytes of person_name line
$j = strpos($block, 'person_name');
for ($k = 0; $k < 12; $k++) { printf("%02X ", ord($block[$j - 1 + $k])); }
echo "\n";
echo $block, "\n";
