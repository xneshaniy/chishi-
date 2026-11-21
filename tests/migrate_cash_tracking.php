<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach (['sales_invoices', 'purchase_invoices'] as $table) {
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('cash_account_id', $cols, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN cash_account_id INT NULL");
        echo "added cash_account_id to $table\n";
    } else {
        echo "$table.cash_account_id already exists\n";
    }
}
