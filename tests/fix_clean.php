<?php
/** Add back the paren eaten by fix2 — only for files where every ` ?></span>` was mine. */
$clean = [
    'app/views/accounting/accounts.php', 'app/views/accounting/journal.php', 'app/views/accounting/journal_view.php',
    'app/views/accounting/ledger.php', 'app/views/admin/index.php', 'app/views/audit/index.php',
    'app/views/cashbank/cheques.php', 'app/views/cashbank/payment_view.php', 'app/views/cashbank/receipt_view.php',
    'app/views/cashbank/reconcile.php', 'app/views/claims/goods_new.php', 'app/views/claims/goods_view.php',
    'app/views/claims/index.php', 'app/views/claims/receipt.php', 'app/views/dashboard/index.php',
    'app/views/expenses/new.php', 'app/views/expenses/view.php', 'app/views/inventory/finished_stock.php',
    'app/views/inventory/raw_stock.php', 'app/views/loans/index.php', 'app/views/loans/new.php',
    'app/views/loans/view.php', 'app/views/maintenance/assets.php', 'app/views/maintenance/index.php',
    'app/views/maintenance/new.php', 'app/views/maintenance/report.php', 'app/views/notifications/index.php',
    'app/views/payroll/advance_print.php', 'app/views/payroll/attendance.php', 'app/views/payroll/employee_form.php',
    'app/views/payroll/employee_ledger.php', 'app/views/payroll/payroll.php', 'app/views/payroll/payslip.php',
    'app/views/production/boms.php', 'app/views/production/orders.php', 'app/views/purchase/invoices.php',
    'app/views/purchase/invoice_new.php', 'app/views/purchase/invoice_view.php', 'app/views/purchase/orders.php',
    'app/views/purchase/raw.php', 'app/views/reports/customer_aging.php', 'app/views/reports/day_book.php',
    'app/views/reports/fg_daily.php', 'app/views/reports/pending_salary.php', 'app/views/reports/price_difference.php',
    'app/views/reports/raw_daily.php', 'app/views/reports/salesman_stock.php', 'app/views/reports/stock_value.php',
    'app/views/reports/supplier_aging.php', 'app/views/sales/invoices.php', 'app/views/sales/invoice_new.php',
    'app/views/sales/orders.php', 'app/views/sales/returns.php', 'app/views/salesmen/bonuses.php',
    'app/views/salesmen/index.php', 'app/views/salesmen/issue.php', 'app/views/salesmen/issue_view.php',
    'app/views/salesmen/ledger.php', 'app/views/salesmen/settle.php', 'app/views/settings/bonus_schemes.php',
    'app/views/settings/fiscal.php', 'app/views/settings/opening.php', 'app/views/stock/adjustments.php',
    'app/views/stock/counts.php', 'app/views/stock/movements.php', 'app/views/suppliers/statement.php',
    'app/views/users/form.php', 'app/views/users/roles.php', 'app/views/vehicles/index.php',
];
foreach ($clean as $rel) {
    $s = file_get_contents($rel);
    $n = substr_count($s, ' ?></span>');
    file_put_contents($rel, str_replace(' ?></span>', ') ?></span>', $s));
    printf("FIXED %-55s %d\n", $rel, $n);
}
