<?php
$s = file_get_contents('database/schema.sqlite.sql');
$i = strpos($s, 'CREATE TABLE expenses');
$end = strpos($s, ');', $i) + 2;
$block = substr($s, $i, $end - $i);
echo $block, "\n";
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // create minimal deps: expense_categories, cash_bank_accounts, chart_of_accounts
    $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE chart_of_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, type VARCHAR(12) NOT NULL, parent_id INT NULL, is_system INTEGER NOT NULL DEFAULT 0, allow_posting INTEGER NOT NULL DEFAULT 1, system_key VARCHAR(40) NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec($block);
    echo "expenses OK\n";
} catch (Throwable $e) {
    echo "ERR: ", $e->getMessage(), "\n";
}
