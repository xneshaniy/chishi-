<?php
$p = 'app/views/layout.php';
$s = file_get_contents($p);
$start = strpos($s, '$nav = [');
$end = strpos($s, "\n];", $start);
if ($start === false || $end === false) { echo "ANCHOR FAILED start=$start end=$end\n"; exit(1); }
$nav = <<<'PHP'
$nav = [
    // 1. Dashboard
    '__section:dashboard' => [__('Dashboard'), 'dashboard'],
    'dashboard' => ['', __('Dashboard'), 'dashboard'],

    // 2. Salesman
    '__section:salesman' => ['Salesman', 'salesman'],
    'salesmen' => ['salesmen', __('Salesman'), 'salesman'],
    'salesmen/issue' => ['salesman.view', __('Goods Issue'), 'invoice'],
    'sales' => ['sales', __('Sales'), 'invoice'],
    'sales/invoices' => ['sales', __('Sales Invoices'), 'invoice'],
    'sales/orders' => ['sales', __('Sales Orders'), 'order'],
    'sales/returns' => ['sales', __('Sales Returns'), 'return'],
    'salesmen/claims' => ['claims.view', __('Salesman Claims'), 'claim'],
    'claims' => ['claims', __('Claims'), 'claim'],

    // 3. Employees
    '__section:employees' => [__('Employees'), 'employee'],
    'employees' => ['employees.view', __('Employees'), 'employee'],
    'salary' => ['employees.view', __('Salary'), 'payroll'],
    'attendance' => ['attendance.mark', __('Attendance'), 'attendance'],
    'attendance/report' => ['attendance.mark', __('Attendance Report'), 'attendance'],
    'attendance/history' => ['attendance.mark', __('Attendance History'), 'attendance'],
    'advances' => ['advances.request', __('Advances'), 'receipt'],
    'loans' => ['loans.view', __('Employee Loans'), 'loans'],
    'payroll' => ['payroll.generate', __('Payroll Run'), 'payroll'],

    // 4. Raw Material
    '__section:rawmaterial' => [__('Raw Material'), 'raw'],
    'purchase/raw' => ['purchase', __('Purchase Raw Material'), 'raw'],
    'suppliers' => ['suppliers', __('Suppliers'), 'supplier'],
    'inventory/raw' => ['inventory.view', __('Raw Material Stock'), 'raw'],
    'inventory/raw-usage' => ['rm.usage', __('Daily Raw Material Usage'), 'raw'],
    'products' => ['products', __('Products'), 'product'],
    'stock/movements' => ['inventory', __('Stock Ledger'), 'ledger'],
    'stock/adjustments' => ['inventory', __('Stock Adjustments'), 'adjust'],
    'stock/transfers' => ['inventory', __('Stock Transfers'), 'transfer'],
    'stock/opening' => ['inventory', __('Opening Stock'), 'opening'],
    'stock/counts' => ['inventory', __('Physical Stock Count'), 'count'],
    'stock/consumption' => ['inventory.view', __('Factory Consumption'), 'production'],
    'jampur' => ['inventory.view', __('Jampur Grinding'), 'jampur'],
    'jampur/report' => ['inventory.view', __('Jampur Reports'), 'reports'],

    // 5. Production
    '__section:production' => [__('Production'), 'production'],
    'production' => ['production.view', __('Production'), 'production'],
    'production/run' => ['production.create', __('Daily Production'), 'production'],
    'production/orders' => ['production.view', __('Production Orders'), 'production'],
    'production/boms' => ['production.view', __('Bills of Materials'), 'bom'],
    'production/report' => ['production.view', __('Production Report'), 'production'],
    'purchase/orders' => ['purchase', __('Purchase Orders'), 'po'],
    'purchase/invoices' => ['purchase', __('Purchase Invoices / GRN'), 'grn'],

    // 6. Reporting
    '__section:reporting' => [__('Reporting'), 'reports'],
    'reports' => ['reports.view', __('Reports Hub'), 'reports'],
    'reports/simple' => ['reports.view', __('Simple Reports'), 'reports'],
    'reports/daily-sales' => ['reports.view', __('Daily Sales Report'), 'receipt'],
    'reports/sales-summary' => ['reports.view', __('Sales Summary'), 'pnl'],
    'reports/collections' => ['reports.view', __('Collection / Recovery'), 'receipt'],
    'reports/customer-aging' => ['reports.view', __('Customer Aging'), 'balance'],
    'reports/supplier-aging' => ['reports.view', __('Supplier Aging'), 'balance'],
    'reports/stock-value' => ['reports.view', __('Stock Valuation'), 'stock'],
    'reports/production' => ['reports.view', __('Production Report'), 'production'],
    'reports/salesman-stock' => ['reports.view', __('Salesman Stock & Settlement'), 'salesman'],
    'reports/fg-daily' => ['reports.view', __('Finished Product Daily'), 'stock'],
    'reports/raw-daily' => ['reports.view', __('Raw Material Daily'), 'raw'],
    'reports/bonus-report' => ['reports.view', __('Bonus & Free Stock'), 'bonus'],
    'reports/price-difference' => ['reports.view', __('Salesman Price Difference'), 'pnl'],
    'reports/salary-expense' => ['reports.view', __('Salary Expense'), 'payroll'],
    'reports/pending-salary' => ['reports.view', __('Pending Salary'), 'payroll'],
    'reports/expense-summary' => ['reports.view', __('Expense Summary'), 'expense'],
    'reports/day-book' => ['reports.view', __('Day Book'), 'ledger'],
    'reports/maintenance' => ['reports.view', __('Maintenance & Repairs'), 'wrench'],
    'reports/gifts' => ['reports.view', __('Gift Reports'), 'gift'],

    // 7. Capital
    '__section:capital' => ['Capital', 'accounts'],
    'capital' => ['capital.view', __('Capital & Drawings'), 'accounts'],
    'income' => ['income.view', __('Other Income'), 'pnl'],
    'personal-loans' => ['personal_loans.view', __('Loans'), 'accounts'],
    'cashbank/accounts' => ['cashbank', __('Cash & Bank Accounts'), 'bank'],
    'cashbank/receipts' => ['cashbank', __('Receipts (Customer)'), 'receipt'],
    'cashbank/payments' => ['cashbank', __('Payments (Supplier/Owner)'), 'payment'],
    'cashbank/transfers' => ['cashbank', __('Cash — Bank Transfers'), 'swap'],
    'cashbank/cheques' => ['cashbank', __('Cheque Register'), 'cheque'],
    'cashbank/reconcile' => ['bank.reconcile', __('Bank Reconciliation'), 'reconcile'],
    'accounting/journal' => ['journal.view', __('Dasti — Manual Journal'), 'journal'],
    'accounting/accounts' => ['accounts.view', __('Chart of Accounts'), 'accounts'],
    'accounting/ledger' => ['journal.view', __('General Ledger'), 'ledger'],
    'accounting/trial-balance' => ['accounts.view', __('Trial Balance'), 'balance'],
    'accounting/pnl' => ['accounts.view', __('Profit & Loss'), 'pnl'],
    'accounting/balance-sheet' => ['accounts.view', __('Balance Sheet'), 'ledger'],
    'accounting/cash-flow' => ['accounts.view', __('Cash Flow'), 'cashflow'],
    'accounting/closing' => ['fiscal.close', __('Fiscal Year Closing'), 'closing'],

    // 8. Expense — sub-sections: Daily Expenses, Maintenance Assets, Vehicles, Gifts
    '__section:expense' => [__('Expense'), 'expense'],
    'expenses' => ['expenses.view', __('Daily Expenses'), 'expense'],
    'expenses/categories' => ['expenses.view', __('Expense Categories'), 'tag'],
    'maintenance' => ['maintenance.view', __('Maintenance Assets & Repairs'), 'wrench'],
    'maintenance/assets' => ['maintenance.view', __('Maintenance Assets'), 'toolbox'],
    'vehicles' => ['vehicles.view', __('Vehicles'), 'vehicle'],
    'gifts' => ['gifts.view', __('Gifts'), 'gift'],

    // Payment Approval (Owner/Admin & Manager by permission)
    '__section:paymentapproval' => [__('Payment Approval'), 'approval'],
    'admin/approvals' => ['approvals.decide', __('Payment Approval'), 'approval'],
    'management/approvals' => ['approvals.view', __('Payment Approval'), 'approval'],

    // Admin settings only
    '__section:more' => [__('settings'), 'tag'],
    'settings/company' => ['settings.view', __('Company Profile & Logo'), 'company'],
    'settings/branches' => ['settings.branches', __('Branches / Factories'), 'branch'],
    'settings/warehouses' => ['settings.warehouses', __('Warehouses'), 'warehouse'],
    'settings/fiscal' => ['settings.fiscal', __('Financial Year & Periods'), 'fiscal'],
    'settings/opening' => ['settings.fiscal', __('Opening Balances'), 'opening'],
    'settings/units' => ['settings.view', __('Units & Packaging'), 'swap'],
    'settings/bonus-schemes' => ['bonus.manage', __('Bonus Schemes & Rules'), 'bonus'],
    'users' => ['users.view', __('users'), 'users'],
    'audit' => ['audit.view', __('Audit Log'), 'shield'],
    'settings/backup' => ['backup.run', __('Backup & Restore'), 'backup'],
PHP;
$out = substr($s, 0, $start) . $nav . substr($s, $end + 4);
file_put_contents($p, $out);
echo "nav rebuilt\n";
