<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$tables = [
    'users', 'user_roles', 'products', 'warehouses', 'cash_bank_accounts',
    'daily_production', 'daily_production_items', 'daily_production_rm_items',
    'sales_invoices', 'sales_invoice_items', 'salesman_ledger',
    'purchase_invoices', 'purchase_items', 'rm_daily_usage',
    'personal_loans', 'personal_loan_repayments', 'expenses',
    'payroll', 'payroll_payments', 'stock_balances', 'stock_movements'
];

foreach ($tables as $t) {
    try {
        $cnt = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo str_pad($t, 28) . ": " . $cnt . " rows\n";
    } catch (Exception $e) {
        echo str_pad($t, 28) . ": table not found or error\n";
    }
}
