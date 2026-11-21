<?php
$raw = file_get_contents('database/schema.sqlite.sql');
$i = strpos($raw, 'CREATE TABLE expenses');
$end = strpos($raw, ');', $i) + 2;
$block = substr($raw, $i, $end - $i);
$block_nc = preg_replace('/--[^\n]*/', '', $block);
// drop the two CONSTRAINT FK lines
$block2 = preg_replace('/[ \t]*CONSTRAINT[^\n]*\n/', '', $block_nc);
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec($block2);
    echo "OK without constraints\n";
} catch (Throwable $e) {
    echo "ERR (no constraints): ", $e->getMessage(), "\n";
}
// also test with only FK line removed
$block3 = preg_replace('/[ \t]*CONSTRAINT fk_exp_acc[^\n]*\n/', '', $block_nc);
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec($block3);
    echo "OK without fk_exp_acc\n";
} catch (Throwable $e) {
    echo "ERR (no fk_exp_acc): ", $e->getMessage(), "\n";
}
