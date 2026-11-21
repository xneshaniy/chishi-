<?php
/** Per failing file: count occurrences of ` ?></span>` vs the number my repair inserted. */
$repair = [
    'app/views/layout.php' => 5, 'app/views/accounting/accounts.php' => 2, 'app/views/accounting/journal.php' => 2,
    'app/views/accounting/journal_view.php' => 3, 'app/views/accounting/ledger.php' => 1, 'app/views/admin/approvals.php' => 2,
    'app/views/admin/index.php' => 4, 'app/views/audit/index.php' => 2, 'app/views/cashbank/accounts.php' => 2,
    'app/views/cashbank/cheques.php' => 2, 'app/views/cashbank/payment_view.php' => 5, 'app/views/cashbank/receipt_view.php' => 5,
    'app/views/cashbank/reconcile.php' => 2, 'app/views/claims/goods.php' => 1, 'app/views/claims/goods_new.php' => 1,
    'app/views/claims/goods_view.php' => 4, 'app/views/claims/index.php' => 1, 'app/views/claims/receipt.php' => 2,
    'app/views/customers/statement.php' => 5, 'app/views/dashboard/index.php' => 17, 'app/views/expenses/index.php' => 3,
    'app/views/expenses/new.php' => 1, 'app/views/expenses/view.php' => 4, 'app/views/inventory/finished_stock.php' => 1,
    'app/views/inventory/raw_stock.php' => 2, 'app/views/loans/index.php' => 2, 'app/views/loans/new.php' => 1,
    'app/views/loans/view.php' => 2, 'app/views/maintenance/assets.php' => 2, 'app/views/maintenance/index.php' => 4,
    'app/views/maintenance/new.php' => 1, 'app/views/maintenance/report.php' => 1, 'app/views/maintenance/view.php' => 11,
    'app/views/notifications/index.php' => 1, 'app/views/partials/doc_head.php' => 3, 'app/views/payroll/advance_print.php' => 3,
    'app/views/payroll/attendance.php' => 1, 'app/views/payroll/employees.php' => 1, 'app/views/payroll/employee_form.php' => 1,
    'app/views/payroll/employee_ledger.php' => 3, 'app/views/payroll/payroll.php' => 2, 'app/views/payroll/payslip.php' => 5,
    'app/views/production/boms.php' => 1, 'app/views/production/orders.php' => 2, 'app/views/products/index.php' => 2,
    'app/views/purchase/invoices.php' => 3, 'app/views/purchase/invoice_new.php' => 2, 'app/views/purchase/invoice_view.php' => 9,
    'app/views/purchase/orders.php' => 1, 'app/views/purchase/raw.php' => 4, 'app/views/reports/customer_aging.php' => 1,
    'app/views/reports/day_book.php' => 1, 'app/views/reports/fg_daily.php' => 1, 'app/views/reports/pending_salary.php' => 1,
    'app/views/reports/price_difference.php' => 1, 'app/views/reports/raw_daily.php' => 1, 'app/views/reports/salesman_stock.php' => 2,
    'app/views/reports/stock_value.php' => 3, 'app/views/reports/supplier_aging.php' => 1, 'app/views/sales/invoices.php' => 3,
    'app/views/sales/invoice_new.php' => 3, 'app/views/sales/invoice_view.php' => 3, 'app/views/sales/orders.php' => 1,
    'app/views/sales/returns.php' => 2, 'app/views/salesmen/bonuses.php' => 1, 'app/views/salesmen/index.php' => 1,
    'app/views/salesmen/issue.php' => 1, 'app/views/salesmen/issue_view.php' => 5, 'app/views/salesmen/ledger.php' => 5,
    'app/views/salesmen/settle.php' => 3, 'app/views/settings/bonus_schemes.php' => 1, 'app/views/settings/fiscal.php' => 1,
    'app/views/settings/opening.php' => 2, 'app/views/settings/warehouses.php' => 1, 'app/views/stock/adjustments.php' => 2,
    'app/views/stock/counts.php' => 1, 'app/views/stock/movements.php' => 2, 'app/views/suppliers/statement.php' => 7,
    'app/views/users/form.php' => 1, 'app/views/users/roles.php' => 1, 'app/views/vehicles/index.php' => 1,
];
foreach ($repair as $rel => $expected) {
    if (!file_exists($rel)) { echo "MISSING $rel\n"; continue; }
    $n = substr_count(file_get_contents($rel), ' ?></span>');
    $mark = $n === $expected ? 'CLEAN' : ($n > $expected ? 'MIXED ' . ($n - $expected) . ' original' : 'LESS?!');
    printf("%-55s repair=%-3d now=%-3d %s\n", $rel, $expected, $n, $mark);
}
