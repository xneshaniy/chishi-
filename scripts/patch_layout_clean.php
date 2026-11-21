<?php
$layoutFile = __DIR__ . '/../app/views/layout.php';
$content = file_get_contents($layoutFile);

$startMarker = '$nav = [';
$endMarker = '];' . "\n" . '$active = $_GET[\'r\']';

$startPos = strpos($content, $startMarker);
$endPos = strpos($content, $endMarker);

if ($startPos === false || $endPos === false) {
    echo "ERROR: Could not locate nav array markers\n";
    exit(1);
}

$newNav = <<<'PHP'
$nav = [
    // 1. Dashboard
    '__section:dashboard' => [__('Dashboard'), 'dashboard'],
    'dashboard' => ['', __('Dashboard'), 'dashboard'],

    // 2. Raw Material
    '__section:rawmaterial' => [__('Raw Material'), 'raw'],
    'purchase/raw' => ['purchase', __('Purchases'), 'raw'],
    'purchase/raw-suppliers' => ['purchase', __('Suppliers & Products'), 'employee'],
    'inventory/raw-usage' => ['rm.usage', __('Daily RM Usage'), 'raw'],

    // 3. Warehouse
    '__section:warehouse' => [__('Warehouse'), 'stock'],
    'warehouse' => ['inventory.view', __('Warehouse Stock'), 'stock'],
    'stock/movements' => ['inventory.view', __('Stock Movements'), 'transfer'],

    // 4. Production
    '__section:production' => [__('Production'), 'production'],
    'production/run' => ['production.create', __('Daily Production'), 'production'],
    'production' => ['production.view', __('Production Hub'), 'production'],
    'production/orders' => ['production.view', __('Production Orders'), 'production'],
    'production/boms' => ['production.view', __('Bills of Materials'), 'bom'],
    'products' => ['products', __('Finished Products'), 'product'],
    'production/report' => ['production.view', __('Production Reports'), 'reports'],

    // 5. Sales & Distribution
    '__section:sales' => [__('Sales'), 'invoice'],
    'sales/invoices/new' => ['sales.create', __('New Sale'), 'order'],
    'sales/invoices' => ['sales.view', __('Sales Invoices'), 'invoice'],
    'sales/returns' => ['sales.view', __('Sales Returns'), 'return'],
    'salesmen' => ['salesmen.view', __('Salesman Profiles'), 'salesman'],

    // 6. Employees & Staff
    '__section:employees' => [__('Employees'), 'employee'],
    'employees' => ['employees.view', __('Employees Directory'), 'employee'],
    'attendance' => ['attendance.mark', __('Attendance'), 'attendance'],
    'attendance/history' => ['attendance.mark', __('Attendance History'), 'attendance'],
    'salary' => ['employees.view', __('Salary Setup'), 'payroll'],
    'payroll' => ['payroll.generate', __('Payroll Run'), 'payroll'],
    'advances' => ['advances.request', __('Advances'), 'receipt'],
    'loans' => ['loans.view', __('Employee Loans'), 'loans'],

    // 7. Loans / Dasti
    '__section:loans' => [dasti_label(), 'receipt'],
    'personal-loans' => ['personal_loans.view', dasti_label(), 'receipt'],

    // 8. Finance & Cash/Bank
    '__section:cashbank' => [__('Finance & Cash/Bank'), 'bank'],
    'cashbank/daily-position' => ['cashbank.view', __('Daily Cash Position'), 'cashflow'],
    'cashbank/accounts' => ['cashbank', __('Cash & Bank Accounts'), 'bank'],
    'cashbank/receipts' => ['cashbank', __('Receipts'), 'receipt'],
    'cashbank/payments' => ['cashbank', __('Payments'), 'payment'],
    'cashbank/transfers' => ['cashbank', __('Transfers'), 'swap'],
    'capital' => ['capital.view', __('Capital & Drawings'), 'accounts'],
    'accounting/ledger' => ['journal.view', __('General Ledger'), 'ledger'],

    // 9. Expenses & Maintenance
    '__section:expense' => [__('Expenses'), 'expense'],
    'expenses' => ['expenses.view', __('Daily Expenses'), 'expense'],
    'company-expenses' => ['company_expenses.view', __('Company Expenses'), 'expense'],
    'vehicles' => ['vehicles.view', __('Vehicles'), 'vehicle'],
    'maintenance' => ['maintenance.view', __('Maintenance & Repairs'), 'wrench'],
    'gifts' => ['gifts.view', __('Gifts'), 'gift'],

    // 10. Reporting
    '__section:reporting' => [__('Reporting'), 'reports'],
    'reports' => ['reports.view', __('Reports Hub'), 'reports'],
    'reports/daily-sales' => ['reports.view', __('Daily Sales Report'), 'receipt'],
    'reports/collections' => ['reports.view', __('Collection / Recovery'), 'receipt'],
    'reports/company-stock' => ['reports.view', __('Company Stock Daily'), 'stock'],
    'reports/raw-daily' => ['reports.view', __('Raw Material Daily'), 'raw'],
    'reports/fg-daily' => ['reports.view', __('Finished Product Daily'), 'stock'],
    'reports/customer-aging' => ['reports.view', __('Customer Aging'), 'balance'],
    'reports/supplier-aging' => ['reports.view', __('Supplier Aging'), 'balance'],
    'reports/expense-summary' => ['reports.view', __('Expense Summary'), 'expense'],
    'reports/day-book' => ['reports.view', __('Day Book'), 'ledger'],

    // 11. Payment Approval
    '__section:paymentapproval' => [__('Payment Approval'), 'approval'],
    'admin/approvals' => ['approvals.decide', __('Payment Approval'), 'approval'],
    'management/approvals' => ['approvals.view', __('Payment Approval'), 'approval'],

    // 12. Records (Owner & Admin only)
    '__section:records' => [__('Records'), 'folder'],
    'records' => [null, __('Company Records'), 'folder'],

    // 13. Settings
    '__section:more' => [__('Settings'), 'tag'],
    'users' => ['users.view', __('Users & Roles'), 'users'],
    'settings/password' => [null, __('Change Password'), 'shield'],
    'audit' => ['audit.view', __('Audit Log'), 'shield'],
    'settings/backup' => ['backup.run', __('Backup & Restore'), 'backup'],
PHP;

$content = substr($content, 0, $startPos) . $newNav . "\n" . substr($content, $endPos);
file_put_contents($layoutFile, $content);
echo "Clean standard navigation applied to layout.php\n";
