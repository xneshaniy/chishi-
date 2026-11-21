<?php
$b1 = file_get_contents('tests/_b1.sql');
$b2 = file_get_contents('tests/_b2.sql');
foreach (['b1 (exp_acc only)' => $b1, 'b2 (exp_cat only)' => $b2] as $lbl => $block) {
    try {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE expense_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, gl_account_id INT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
        $pdo->exec("CREATE TABLE cash_bank_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(120) NOT NULL, type VARCHAR(10) NOT NULL, opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0, gl_account_id INT NULL, is_active INTEGER NOT NULL DEFAULT 1)");
        $pdo->exec($block);
        echo "$lbl OK\n";
    } catch (Throwable $e) {
        echo "$lbl ERR: ", $e->getMessage(), "\n";
    }
}
