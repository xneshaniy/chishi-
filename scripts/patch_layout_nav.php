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
    // 1. Dashboard (ڈیش بورڈ)
    '__section:dashboard' => [__('Dashboard'), 'dashboard'],
    'dashboard' => ['', __('Dashboard'), 'dashboard'],

    // 2. Raw Material (کچا مال / خریداری)
    '__section:rawmaterial' => [__('Raw Material (کچا مال)'), 'raw'],
    'purchase/raw' => ['purchase', __('Purchases (خریداری)'), 'raw'],
    'purchase/raw-suppliers' => ['purchase', __('Suppliers & Products (سپلائرز اور پروڈکٹس)'), 'employee'],
    'inventory/raw-usage' => ['rm.usage', __('Daily RM Usage (ڈیلی را مٹیریل یوز)'), 'raw'],

    // 3. Warehouse (ویئر ہاؤس اور اسٹاک)
    '__section:warehouse' => [__('Warehouse (ویئر ہاؤس)'), 'stock'],
    'warehouse' => ['inventory.view', __('Warehouse Stock (گودام اسٹاک)'), 'stock'],
    'stock/movements' => ['inventory.view', __('Stock Movements (اسٹاک تفصیل)'), 'transfer'],

    // 4. Production (پروڈکشن / تیاری)
    '__section:production' => [__('Production (پروڈکشن)'), 'production'],
    'production/run' => ['production.create', __('Daily Production (روزانہ پروڈکشن)'), 'production'],
    'production' => ['production.view', __('Production Hub (پروڈکشن ہب)'), 'production'],
    'production/orders' => ['production.view', __('Production Orders (پروڈکشن آرڈرز)'), 'production'],
    'production/boms' => ['production.view', __('Bills of Materials (BOM / فارمولا)'), 'bom'],
    'products' => ['products', __('Finished Products (تیار پروڈکٹس)'), 'product'],
    'production/report' => ['production.view', __('Production Reports (رپورٹس)'), 'reports'],

    // 5. Sales & Distribution (سیلز اور سیلز مین)
    '__section:sales' => [__('Sales (سیلز / فروخت)'), 'invoice'],
    'sales/invoices' => ['sales.view', __('Sales Invoices (سیل انوائسز)'), 'invoice'],
    'sales/invoices/new' => ['sales.create', __('New Sale (نئی سیل)'), 'order'],
    'sales/returns' => ['sales.view', __('Sales Returns (سیل واپسی)'), 'return'],
    'salesmen' => ['salesmen.view', __('Salesman Profiles (سیلز مین)'), 'salesman'],

    // 6. Employees & Attendance (ملازمین اور حاضری)
    '__section:employees' => [__('Employees (ملازمین)'), 'employee'],
    'employees' => ['employees.view', __('Employees Directory (ملازمین)'), 'employee'],
    'attendance' => ['attendance.mark', __('Attendance (حاضری)'), 'attendance'],
    'attendance/history' => ['attendance.mark', __('Attendance History (حاضری ریکارڈ)'), 'attendance'],
    'salary' => ['employees.view', __('Salary Setup (تنخواہیں)'), 'payroll'],
    'payroll' => ['payroll.generate', __('Payroll Run (تنخواہ ادائیگی)'), 'payroll'],
    'advances' => ['advances.request', __('Advances (ایڈوانس)'), 'receipt'],
    'loans' => ['loans.view', __('Employee Loans (ملازم قرض)'), 'loans'],

    // 7. Loans / Dasti (قرض / دستی)
    '__section:loans' => [dasti_label(), 'receipt'],
    'personal-loans' => ['personal_loans.view', dasti_label(), 'receipt'],

    // 8. Finance & Cash/Bank (خزانہ اور کیش/بینک)
    '__section:cashbank' => [__('Finance & Cash/Bank (خزانہ و بینک)'), 'bank'],
    'cashbank/daily-position' => ['cashbank.view', __('Daily Cash Position (آج کا کیش)'), 'cashflow'],
    'cashbank/accounts' => ['cashbank', __('Cash & Bank Accounts (اکاؤنٹس)'), 'bank'],
    'cashbank/receipts' => ['cashbank', __('Receipts (وصولیاں)'), 'receipt'],
    'cashbank/payments' => ['cashbank', __('Payments (ادائیگیاں)'), 'payment'],
    'cashbank/transfers' => ['cashbank', __('Transfers (بینک ٹرانسفر)'), 'swap'],
    'capital' => ['capital.view', __('Capital & Drawings (سرمایہ)'), 'accounts'],
    'accounting/ledger' => ['journal.view', __('General Ledger (جنرل لیجر)'), 'ledger'],

    // 9. Expenses (اخراجات)
    '__section:expense' => [__('Expense (اخراجات)'), 'expense'],
    'expenses' => ['expenses.view', __('Daily Expenses (روزانہ کے اخراجات)'), 'expense'],
    'company-expenses' => ['company_expenses.view', __('Company Expenses (کمپنی اخراجات)'), 'expense'],
    'vehicles' => ['vehicles.view', __('Vehicles (گاڑیاں)'), 'vehicle'],
    'maintenance' => ['maintenance.view', __('Maintenance & Repairs (مرمت)'), 'wrench'],
    'gifts' => ['gifts.view', __('Gifts (تحائف)'), 'gift'],

    // 10. Reporting (رپورٹس)
    '__section:reporting' => [__('Reporting (رپورٹس)'), 'reports'],
    'reports' => ['reports.view', __('Reports Hub (رپورٹس ہب)'), 'reports'],
    'reports/daily-sales' => ['reports.view', __('Daily Sales Report (روزانہ سیل رپورٹ)'), 'receipt'],
    'reports/collections' => ['reports.view', __('Collection / Recovery (وصولی رپورٹ)'), 'receipt'],
    'reports/company-stock' => ['reports.view', __('Company Stock Daily (اسٹاک رپورٹ)'), 'stock'],
    'reports/raw-daily' => ['reports.view', __('Raw Material Daily (را مٹیریل رپورٹ)'), 'raw'],
    'reports/fg-daily' => ['reports.view', __('Finished Product Daily (پروڈکٹ رپورٹ)'), 'stock'],
    'reports/customer-aging' => ['reports.view', __('Customer Aging (گاہک کھاتہ)'), 'balance'],
    'reports/supplier-aging' => ['reports.view', __('Supplier Aging (سپلائر کھاتہ)'), 'balance'],
    'reports/expense-summary' => ['reports.view', __('Expense Summary (اخراجات رپورٹ)'), 'expense'],
    'reports/day-book' => ['reports.view', __('Day Book (روزنامچہ)'), 'ledger'],

    // 11. Payment Approval (ادائیگی منظوری)
    '__section:paymentapproval' => [__('Payment Approval (منظوری)'), 'approval'],
    'admin/approvals' => ['approvals.decide', __('Payment Approval (ایڈمن)'), 'approval'],
    'management/approvals' => ['approvals.view', __('Payment Approval (مینیجر)'), 'approval'],

    // 12. Records (ریکارڈ) — Owner & Admin only
    '__section:records' => [__('Records (ریکارڈ)'), 'folder'],
    'records' => [null, __('Records (کمپنی ریکارڈ)'), 'folder'],

    // 13. Settings (سیٹنگز)
    '__section:more' => [__('Settings (سیٹنگز)'), 'tag'],
    'users' => ['users.view', __('Users & Roles (صارفین اور پرمیشنز)'), 'users'],
    'settings/password' => [null, __('Change Password (پاسورڈ تبدیل کریں)'), 'shield'],
    'audit' => ['audit.view', __('Audit Log (آڈٹ لاگ)'), 'shield'],
    'settings/backup' => ['backup.run', __('Backup & Restore (بیک اپ)'), 'backup'],
PHP;

$content = substr($content, 0, $startPos) . $newNav . "\n" . substr($content, $endPos);
file_put_contents($layoutFile, $content);
echo "layout.php navigation successfully reorganized\n";
