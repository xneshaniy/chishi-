<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/app/core/bootstrap.php';
    $db = db();

    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tablesToTruncate = [
        'approval_requests',
        'areas',
        'attachments',
        'attendance',
        'staff_attendance',
        'staff_attendance_audit',
        'staff_leaves',
        'audit_logs',
        'bank_transfers',
        'bills_of_materials',
        'bom_lines',
        'bonuses',
        'cheques',
        'claim_goods',
        'claim_goods_items',
        'claim_goods_payments',
        'claims',
        'company_expenses',
        'customers',
        'daily_production',
        'daily_production_items',
        'daily_production_rm_items',
        'employee_advances',
        'employee_bonuses',
        'employee_deductions',
        'employee_documents',
        'employee_loans',
        'employees',
        'expenses',
        'factory_issue_items',
        'factory_issues',
        'gifts',
        'grinding_records',
        'grinding_returns',
        'grinding_sends',
        'idempotency_keys',
        'journal_entries',
        'journal_lines',
        'loan_installments',
        'login_attempts',
        'login_history',
        'maintenance_assets',
        'maintenance_parts',
        'maintenance_records',
        'notifications',
        'other_income',
        'payments',
        'payroll',
        'payroll_deductions',
        'payroll_payments',
        'personal_loan_repayments',
        'personal_loans',
        'product_categories',
        'product_packaging',
        'production_inputs',
        'production_orders',
        'products',
        'purchase_invoices',
        'purchase_items',
        'purchase_order_items',
        'purchase_orders',
        'receipts',
        'rm_daily_usage',
        'salary_changes',
        'sales_invoice_items',
        'sales_invoices',
        'sales_order_items',
        'sales_orders',
        'sales_return_items',
        'sales_returns',
        'salesman_collections',
        'salesman_customers',
        'salesman_documents',
        'salesman_expenses',
        'salesman_issue_items',
        'salesman_issues',
        'salesman_ledger',
        'salesman_payment_allocations',
        'salesman_payments',
        'salesman_salary_advances',
        'salesman_salary_payments',
        'salesman_settlements',
        'salesmen',
        'stock_adjustment_items',
        'stock_adjustments',
        'stock_balances',
        'stock_cost_layers',
        'stock_movements',
        'supplier_products',
        'suppliers',
        'user_permissions',
        'vehicle_fuel_logs',
        'vehicle_maintenance',
        'vehicles',
        'voucher_sequences'
    ];

    $truncated = [];
    foreach ($tablesToTruncate as $tbl) {
        $db->exec("TRUNCATE TABLE `$tbl`");
        $truncated[] = $tbl;
    }

    // Clean test warehouses (keep ID 1 and 2: Main Warehouse & Factory Warehouse)
    $db->exec("DELETE FROM warehouses WHERE id > 2 OR type = 'salesman'");
    $db->exec("ALTER TABLE warehouses AUTO_INCREMENT = 3");

    // Reset Cash & Bank account balances
    $db->exec("UPDATE cash_bank_accounts SET opening_balance = 0");

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Clean uploads directory
    $uploadDir = __DIR__ . '/storage/uploads';
    $deletedFiles = 0;
    if (is_dir($uploadDir)) {
        $subdirs = ['employees', 'salesmen', 'products', 'documents', 'attachments'];
        foreach ($subdirs as $sub) {
            $dir = $uploadDir . '/' . $sub;
            if (is_dir($dir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $file) {
                    if ($file->isDir()) {
                        @rmdir($file->getRealPath());
                    } else {
                        @unlink($file->getRealPath());
                        $deletedFiles++;
                    }
                }
            }
        }
    }

    // Check remaining counts
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $remaining = [];
    foreach ($tables as $t) {
        $cnt = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        if ($cnt > 0) {
            $remaining[$t] = $cnt;
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Database and test uploads completely reset to fresh state.",
        "truncated_tables_count" => count($truncated),
        "deleted_upload_files" => $deletedFiles,
        "remaining_tables" => $remaining
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
