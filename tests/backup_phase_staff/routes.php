<?php
/**
 * Route map: 'route' => [Controller::class, 'method', 'required.permission'|null]
 * Permissions enforced centrally in index.php.
 */
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/UsersController.php';
require_once __DIR__ . '/controllers/AuditController.php';
require_once __DIR__ . '/controllers/ProductsController.php';
require_once __DIR__ . '/controllers/StockController.php';
require_once __DIR__ . '/controllers/ProductionController.php';
require_once __DIR__ . '/controllers/CustomersController.php';
require_once __DIR__ . '/controllers/SuppliersController.php';
require_once __DIR__ . '/controllers/SalesmenController.php';
require_once __DIR__ . '/controllers/SalesController.php';
require_once __DIR__ . '/controllers/ClaimsController.php';
require_once __DIR__ . '/controllers/PurchaseController.php';
require_once __DIR__ . '/controllers/CashBankController.php';
require_once __DIR__ . '/controllers/ExpensesController.php';
require_once __DIR__ . '/controllers/ConsumptionController.php';
require_once __DIR__ . '/controllers/PayrollController.php';
require_once __DIR__ . '/controllers/VehiclesController.php';
require_once __DIR__ . '/controllers/MaintenanceController.php';
require_once __DIR__ . '/controllers/AccountingController.php';
require_once __DIR__ . '/controllers/ReportsController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/ManagementController.php';
require_once __DIR__ . '/controllers/LoansController.php';
require_once __DIR__ . '/controllers/NotificationsController.php';

return [
    'auth/login'                 => [AuthController::class, 'login', null],
    'auth/logout'                => [AuthController::class, 'logout', null],

    // Separate login areas (server-side gated)
    'admin/login'                => [AdminController::class, 'adminLogin', null],
    'admin'                      => [AdminController::class, 'admin', null],
    'admin/approvals'            => [AdminController::class, 'approvals', null],
    'admin/approvals/decide'     => [AdminController::class, 'approvalsDecide', null],
    'management/login'           => [ManagementController::class, 'managementLogin', null],
    'management'                 => [ManagementController::class, 'management', null],

    'dashboard'                  => [DashboardController::class, 'index', 'dashboard.view'],

    'settings/bonus-schemes'     => [SettingsController::class, 'bonusSchemes', 'bonus.manage'],

    'settings/units'             => [SettingsController::class, 'units', 'settings.view'],
    'settings/company'           => [SettingsController::class, 'company', 'settings.view'],
    'settings/branches'          => [SettingsController::class, 'branches', 'settings.branches'],
    'settings/warehouses'        => [SettingsController::class, 'warehouses', 'settings.warehouses'],
    'settings/fiscal'            => [SettingsController::class, 'fiscal', 'settings.fiscal'],
    'settings/opening'           => [SettingsController::class, 'opening', 'settings.fiscal'],
    'settings/backup'            => [SettingsController::class, 'backup', 'backup.run'],

    'users'                      => [UsersController::class, 'index', 'users.view'],
    'users/new'                  => [UsersController::class, 'newUser', 'users.create'],
    'users/{id}/edit'            => [UsersController::class, 'editUser', 'users.edit'],
    'users/{id}/toggle'          => [UsersController::class, 'toggleUser', 'users.edit'],
    'users/roles'                => [UsersController::class, 'roles', 'users.view'],
    'users/roles/{id}'           => [UsersController::class, 'editRole', 'users.edit'],

    'audit'                      => [AuditController::class, 'index', 'audit.view'],

    'losses'                     => [ReportsController::class, 'losses', 'reports.view'],
    'products'                   => [ProductsController::class, 'index', 'products.view'],
    'products/new'               => [ProductsController::class, 'newProduct', 'products.create'],
    'products/{id}/edit'         => [ProductsController::class, 'editProduct', 'products.edit'],
    'products/categories'        => [ProductsController::class, 'categories', 'products.create'],

    'stock/movements'            => [StockController::class, 'movements', 'inventory.view'],
    'stock/adjustments'          => [StockController::class, 'adjustments', 'inventory.view'],
    'stock/adjustments/new'      => [StockController::class, 'adjustmentNew', 'inventory.adjust'],
    'stock/adjustments/{id}/approve' => [StockController::class, 'adjustmentApprove', 'inventory.adjust_approve'],
    'stock/transfers'            => [StockController::class, 'transferNew', 'inventory.transfer'],
    'stock/opening'              => [StockController::class, 'openingNew', 'inventory.move'],
    'stock/counts'               => [StockController::class, 'counts', 'inventory.count'],
    'inventory/raw'              => [StockController::class, 'rawStock', 'inventory.view'],
    'inventory/finished'         => [StockController::class, 'finishedStock', 'inventory.view'],

    'production/orders'          => [ProductionController::class, 'orders', 'production.view'],
    'production/new'             => [ProductionController::class, 'orderNew', 'production.create'],
    'production/{id}/release'    => [ProductionController::class, 'orderRelease', 'production.release'],
    'production/{id}/complete'   => [ProductionController::class, 'orderComplete', 'production.complete'],
    'production/{id}/cancel'     => [ProductionController::class, 'orderCancel', 'production.create'],
    'production/boms'            => [ProductionController::class, 'boms', 'bom.manage'],
    'production/boms/new'        => [ProductionController::class, 'bomNew', 'bom.manage'],

    'customers'                  => [CustomersController::class, 'index', 'customers.view'],
    'customers/new'              => [CustomersController::class, 'newCustomer', 'customers.create'],
    'customers/{id}/edit'        => [CustomersController::class, 'editCustomer', 'customers.edit'],
    'customers/{id}/statement'   => [CustomersController::class, 'statement', 'customers.view'],

    'suppliers'                  => [SuppliersController::class, 'index', 'suppliers.view'],
    'suppliers/new'              => [SuppliersController::class, 'newSupplier', 'suppliers.create'],
    'suppliers/{id}/edit'        => [SuppliersController::class, 'editSupplier', 'suppliers.edit'],
    'suppliers/{id}/statement'   => [SuppliersController::class, 'statement', 'suppliers.view'],

    'reports/production'         => [StockController::class, 'productionReport', 'reports.view'],
    'salesmen'                   => [SalesmenController::class, 'index', 'salesmen.view'],
    'salesmen/new'               => [SalesmenController::class, 'newSalesman', 'salesmen.create'],
    'salesmen/{id}/edit'         => [SalesmenController::class, 'editSalesman', 'salesmen.edit'],
    'salesmen/{id}/settle'       => [SalesmenController::class, 'settle', 'salesmen.settle'],
    'salesmen/issue'             => [SalesmenController::class, 'issuePicker', 'salesmen.view'],
    'salesmen/{id}/issue'        => [SalesmenController::class, 'issueForm', 'salesmen.settle'],
    'salesmen/{id}/issues/{issue_id}/view' => [SalesmenController::class, 'issueView', 'salesmen.view'],
    'salesmen/bonuses'           => [SalesmenController::class, 'bonuses', 'bonuses.view'],
    'salesmen/bonuses/new'       => [SalesmenController::class, 'bonusNew', 'bonuses.manage'],
    'salesmen/{id}/salary'     => [SalesmenController::class, 'salary', 'salesmen.view'],
    'salesmen/{id}/expense'    => [SalesmenController::class, 'expense', 'salesmen.view'],
    'salesmen/{id}/settlement' => [SalesmenController::class, 'settlement', 'salesmen.view'],
    'salesmen/{id}/account'    => [SalesmenController::class, 'account', 'salesmen.view'],
    'salesmen/{id}/ledger'       => [SalesmenController::class, 'ledger', 'salesmen.view'],
    'salesmen/{id}/pay'          => [SalesmenController::class, 'payToCompany', 'salesmen.settle'],
    'salesmen/{id}/return'       => [SalesmenController::class, 'salesmanReturn', 'salesmen.settle'],

    'sales/orders'               => [SalesController::class, 'orders', 'sales.view'],
    'sales/orders/new'           => [SalesController::class, 'orderNew', 'sales.create'],
    'sales/orders/{id}/confirm'  => [SalesController::class, 'orderConfirm', 'sales.edit'],
    'sales/invoices'             => [SalesController::class, 'invoices', 'sales.view'],
    'sales/invoices/new'         => [SalesController::class, 'invoiceNew', 'sales.create'],
    'sales/invoices/{id}'        => [SalesController::class, 'invoiceView', 'sales.view'],
    'sales/invoices/{id}/reverse'=> [SalesController::class, 'invoiceReverse', 'journal.reverse'],
    'sales/returns'              => [SalesController::class, 'returns', 'sales.view'],
    'sales/returns/new'          => [SalesController::class, 'returnNew', 'sales.return'],
    'sales/returns/{id}/reverse' => [SalesController::class, 'returnReverse', 'journal.reverse'],

    'claims'                     => [ClaimsController::class, 'index', 'claims.view'],
    'claims/new'                 => [ClaimsController::class, 'newClaim', 'claims.create'],
    'claims/{id}/status'         => [ClaimsController::class, 'setStatus', 'claims.approve'],
    'claims/goods'               => [ClaimsController::class, 'goodsList', 'claims.view'],
    'claims/goods/new'           => [ClaimsController::class, 'goodsNew', 'claims.create'],
    'claims/goods/{id}'          => [ClaimsController::class, 'goodsView', 'claims.view'],
    'claims/goods/{id}/pay'      => [ClaimsController::class, 'goodsPay', 'claims.approve'],
    'claims/goods/{id}/receipt'  => [ClaimsController::class, 'goodsReceipt', 'claims.view'],

    'purchase/orders'            => [PurchaseController::class, 'orders', 'purchase.view'],
    'purchase/orders/new'        => [PurchaseController::class, 'orderNew', 'purchase.create'],
    'purchase/orders/{id}/approve' => [PurchaseController::class, 'orderApprove', 'purchase.edit'],
    'purchase/orders/{id}/cancel'  => [PurchaseController::class, 'orderCancel', 'purchase.edit'],
    'purchase/invoices'          => [PurchaseController::class, 'invoices', 'purchase.view'],
    'purchase/invoices/new'      => [PurchaseController::class, 'invoiceNew', 'purchase.receive'],
    'purchase/invoices/{id}/reverse' => [PurchaseController::class, 'invoiceReverse', 'journal.reverse'],
    'purchase/invoices/{id}'      => [PurchaseController::class, 'invoiceView', 'purchase.view'],

    'cashbank/accounts'          => [CashBankController::class, 'accounts', 'cashbank.view'],
    'cashbank/accounts/new'      => [CashBankController::class, 'accountNew', 'cashbank.view'],
    'cashbank/receipts'          => [CashBankController::class, 'receipts', 'cashbank.view'],
    'cashbank/receipts/new'      => [CashBankController::class, 'receiptNew', 'cashbank.receipt'],
    'cashbank/payments'          => [CashBankController::class, 'payments', 'cashbank.view'],
    'cashbank/payments/new'      => [CashBankController::class, 'paymentNew', 'cashbank.payment'],
    'cashbank/transfers'         => [CashBankController::class, 'transfers', 'cashbank.view'],
    'cashbank/transfers/new'     => [CashBankController::class, 'transferNew', 'cashbank.transfer'],
    'cashbank/cheques'           => [CashBankController::class, 'cheques', 'cheques.manage'],
    'cashbank/reconcile'         => [CashBankController::class, 'reconcile', 'bank.reconcile'],
    'cashbank/payments/{id}'     => [CashBankController::class, 'paymentView', 'cashbank.view'],
    'cashbank/receipts/{id}'     => [CashBankController::class, 'receiptView', 'cashbank.view'],
    'cashbank/cheques/new'       => [CashBankController::class, 'chequeNew', 'cheques.manage'],
    'cashbank/cheques/{id}/status' => [CashBankController::class, 'chequeStatus', 'cheques.manage'],

    'expenses'                   => [ExpensesController::class, 'index', 'expenses.view'],
    'expenses/new'               => [ExpensesController::class, 'newExpense', 'expenses.create'],
    'expenses/{id}/post'         => [ExpensesController::class, 'expensePost', 'expenses.approve'],
    'expenses/{id}/reverse'      => [ExpensesController::class, 'expenseReverse', 'journal.reverse'],
    'expenses/categories'        => [ExpensesController::class, 'categories', 'expenses.view'],
    'expenses/categories/new'    => [ExpensesController::class, 'categoryNew', 'expenses.create'],
    'expenses/{id}'              => [ExpensesController::class, 'view', 'expenses.view'],

    'employees'                  => [PayrollController::class, 'employees', 'employees.view'],
    'loans'                      => [LoansController::class, 'index', 'loans.view'],
    'notifications'              => [NotificationsController::class, 'index', null],
    'notifications/unread'       => [NotificationsController::class, 'unread', null],
    'notifications/mark'         => [NotificationsController::class, 'mark', null],
    'notifications/sound'        => [NotificationsController::class, 'toggleSound', null],
    'notifications/desktop'      => [NotificationsController::class, 'toggleDesktop', null],
    'loans/new'                  => [LoansController::class, 'newLoan', 'loans.manage'],
    'loans/{id}'                 => [LoansController::class, 'view', 'loans.view'],
    'loans/{id}/repay'           => [LoansController::class, 'repay', 'loans.manage'],
    'employees/new'              => [PayrollController::class, 'employeeNew', 'employees.create'],
    'employees/{id}/edit'        => [PayrollController::class, 'employeeEdit', 'employees.edit'],
    'employees/{id}/ledger'      => [PayrollController::class, 'employeeLedger', 'employees.view'],
    'employees/{id}/advance' => [PayrollController::class, 'advanceRequest', 'advances.request'],
    'employees/{id}/advances/{aid}/print' => [PayrollController::class, 'advancePrint', 'employees.view'],
    'employees/{id}/advances/{aid}/cancel' => [PayrollController::class, 'advanceCancel', 'advances.request'],
    'attendance'                 => [PayrollController::class, 'attendance', 'attendance.mark'],
    'payroll/payslip/{id}'       => [PayrollController::class, 'payslip', 'payroll.generate'],
    'payroll'                    => [PayrollController::class, 'payroll', 'payroll.generate'],
    'payroll/generate'           => [PayrollController::class, 'payrollGenerate', 'payroll.generate'],
    'payroll/{id}/pay'           => [PayrollController::class, 'payrollPay', 'payroll.pay'],
    'payroll/{id}/reverse'       => [PayrollController::class, 'payrollReverse', 'journal.reverse'],

    'vehicles'                   => [VehiclesController::class, 'index', 'vehicles.view'],
    'vehicles/new'               => [VehiclesController::class, 'vehicleNew', 'vehicles.manage'],
    'vehicles/{id}'              => [VehiclesController::class, 'vehicleView', 'vehicles.view'],
    'vehicles/{id}/fuel'         => [VehiclesController::class, 'fuelNew', 'vehicles.manage'],
    'vehicles/{id}/maintenance'  => [VehiclesController::class, 'maintenanceNew', 'vehicles.manage'],
    'stock/consumption'          => [ConsumptionController::class, 'index', 'inventory.view'],
    'stock/consumption/new'      => [ConsumptionController::class, 'newIssue', 'production.release'],
    'stock/consumption/{id}'     => [ConsumptionController::class, 'view', 'inventory.view'],
    'stock/consumption/{id}/cancel' => [ConsumptionController::class, 'cancel', 'journal.reverse'],
    'maintenance'                => [MaintenanceController::class, 'index', 'maintenance.view'],
    'maintenance/new'            => [MaintenanceController::class, 'newRecord', 'maintenance.create'],
    'maintenance/assets'         => [MaintenanceController::class, 'assets', 'maintenance.view'],
    'maintenance/assets/new'     => [MaintenanceController::class, 'assetNew', 'maintenance.create'],
    'maintenance/assets/{id}/edit' => [MaintenanceController::class, 'assetEdit', 'maintenance.create'],
    'maintenance/{id}'           => [MaintenanceController::class, 'view', 'maintenance.view'],
    'maintenance/{id}/attachment' => [MaintenanceController::class, 'attachment', 'maintenance.view'],
    'maintenance/{id}/approve'   => [MaintenanceController::class, 'approve', 'approvals.decide'],
    'maintenance/{id}/reverse'   => [MaintenanceController::class, 'reverse', 'approvals.decide'],
    'reports/maintenance'        => [MaintenanceController::class, 'report', 'reports.view'],

    'accounting/accounts'        => [AccountingController::class, 'accounts', 'accounts.view'],
    'accounting/accounts/new'    => [AccountingController::class, 'accountNew', 'accounts.manage'],
    'accounting/journal'         => [AccountingController::class, 'journal', 'journal.view'],
    'accounting/journal/new'     => [AccountingController::class, 'journalNew', 'journal.create'],
    'accounting/journal/{id}'    => [AccountingController::class, 'journalView', 'journal.view'],
    'accounting/journal/{id}/post'    => [AccountingController::class, 'journalPost', 'journal.post'],
    'accounting/journal/{id}/reverse' => [AccountingController::class, 'journalReverse', 'journal.reverse'],
    'accounting/ledger'          => [AccountingController::class, 'ledger', 'journal.view'],
    'accounting/trial-balance'   => [AccountingController::class, 'trialBalance', 'accounts.view'],
    'accounting/pnl'             => [AccountingController::class, 'pnl', 'accounts.view'],
    'accounting/balance-sheet'   => [AccountingController::class, 'balanceSheet', 'accounts.view'],
    'accounting/cash-flow'       => [AccountingController::class, 'cashFlow', 'accounts.view'],
    'accounting/closing'         => [AccountingController::class, 'closing', 'fiscal.close'],

    'reports/simple'             => [ReportsController::class, 'simple', 'reports.view'],
    'reports'                    => [ReportsController::class, 'hub', 'reports.view'],
    'reports/bonus-report'       => [ReportsController::class, 'bonusReport', 'reports.view'],
    'reports/price-difference'   => [ReportsController::class, 'priceDifference', 'reports.view'],
    'reports/salary-expense'     => [ReportsController::class, 'salaryExpense', 'reports.view'],
    'reports/pending-salary'     => [ReportsController::class, 'pendingSalary', 'reports.view'],
    'reports/daily-sales'        => [ReportsController::class, 'dailySales', 'reports.view'],
    'reports/sales-summary'      => [ReportsController::class, 'salesSummary', 'reports.view'],
    'reports/collections'        => [ReportsController::class, 'collections', 'reports.view'],
    'reports/customer-aging'     => [ReportsController::class, 'customerAging', 'reports.view'],
    'reports/supplier-aging'     => [ReportsController::class, 'supplierAging', 'reports.view'],
    'reports/stock-value'        => [ReportsController::class, 'stockValue', 'reports.view'],
    'reports/expense-summary'    => [ReportsController::class, 'expenseSummary', 'reports.view'],
    'reports/raw-daily'          => [StockController::class, 'rawDaily', 'reports.view'],
    'reports/fg-daily'           => [StockController::class, 'fgDaily', 'reports.view'],
    'purchase/raw'               => [PurchaseController::class, 'rawMaterials', 'purchase.view'],
    'reports/salesman-stock'     => [ReportsController::class, 'salesmanStock', 'reports.view'],
    'reports/day-book'           => [ReportsController::class, 'dayBook', 'reports.view'],
];
