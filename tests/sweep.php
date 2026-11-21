<?php
/** Visit every GET route and report HTTP status + whether the page looks like an error page. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
// login
[$c, $body] = req($base . 'auth/login', null, $jar);
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
[$c2, $body2] = req($base . 'auth/login', 'csrf=' . urlencode($m[1] ?? '') . '&username=owner&password=owner1234', $jar);
echo "login: $c2 -> ", substr($body2, 0, 0), "\n";

$routes = ['dashboard','settings/company','settings/branches','settings/warehouses','settings/fiscal','settings/opening','settings/backup','settings/bonus-schemes',
'admin/login','admin','admin/approvals','admin/notifications','admin/approvals/1','admin/archives','admin/archives/products',
'management/login','management',
'users','users/new','users/roles','audit','products','products/new','products/categories','stock/movements','stock/adjustments','stock/adjustments/new','stock/transfers','stock/opening','stock/counts',
'salary','attendance','attendance/report','attendance/hours','attendance/history','management/approvals','management/approvals/1','salary/employee/1','salary/salesman/1','production','production/run','production/report','production/1','production/orders','production/new','production/boms','production/boms/new','customers','customers/new','suppliers','suppliers/new','inventory/raw-usage','inventory/raw-usage/report','personal-loans','personal-loans/new',
'gifts','gifts/new-money','gifts/new-goods','reports/gifts','company-expenses','company-expenses/new','reports/company-expenses','salesmen/returns','reports/company-stock',
'salesmen','salesmen/new','salesmen/bonuses','salesmen/bonuses/new','salesmen/1/claims','advances','advances/employee/1/voucher','advances/salesman/1/voucher','salesmen/1/account','salesmen/2/account','salesmen/1/recovery','salesmen/2/recovery','salesmen/2/recovery/statement','salesmen/1/payments','salesmen/2/payments','salesmen/2/daily','salesmen/2/overview','salesmen/2/daily/print/1','salesmen/2/overview/print','sales/orders','sales/orders/new','sales/invoices','sales/invoices/new','sales/returns','sales/returns/new',
'claims','claims/new','purchase/orders','purchase/orders/new','purchase/invoices','purchase/invoices/new','purchase/raw',
'cashbank/accounts','cashbank/accounts/new','cashbank/receipts','cashbank/receipts/new','cashbank/payments','cashbank/payments/new','cashbank/transfers','cashbank/transfers/new','cashbank/cheques','cashbank/cheques/new','cashbank/reconcile',
'maintenance','maintenance/new','maintenance/assets','maintenance/assets/new','reports/maintenance',
'cashbank/accounts','cashbank/accounts/new','cashbank/receipts','cashbank/receipts/new','cashbank/payments','cashbank/payments/new','cashbank/transfers','cashbank/transfers/new','cashbank/cheques','cashbank/cheques/new','cashbank/reconcile',
'expenses','expenses/new','expenses/categories','expenses/categories/new','employees','employees/new','attendance','payroll',
'employees','employees/new','employees/1','employees/18','employees/3','employees/1&tab=salary','employees/1&tab=attendance','employees/1&tab=advances','employees/1&tab=bonus','employees/1&tab=deductions','employees/1&tab=history','employees/1&tab=ledger','capital','income','jampur','jampur/report','admin','admin/team','admin/team/new','admin/team/19','admin/team/18',
'loans','loans/new','vehicles','vehicles/new','accounting/accounts','accounting/accounts/new','accounting/journal','accounting/journal/new','accounting/ledger','accounting/trial-balance','accounting/pnl','accounting/balance-sheet','accounting/cash-flow','accounting/closing',
'reports','reports/daily-sales','reports/sales-summary','reports/collections','reports/customer-aging','reports/supplier-aging','reports/stock-value','reports/expense-summary','reports/production','reports/salesman-stock','reports/bonus-report','reports/price-difference','reports/salary-expense','reports/pending-salary','reports/day-book','claims/goods','claims/goods/new','notifications'];
$fail = 0;
foreach ($routes as $r) {
    [$c, $body] = req($base . $r, null, $jar);
    $isError = $c >= 400 || stripos($body, 'Fatal error') !== false || stripos($body, 'Uncaught') !== false || stripos($body, 'Something went wrong') !== false || (stripos($body, 'View not found') !== false);
    $flag = $isError ? '  <<< FAIL' : '';
    if ($isError) $fail++;
    echo str_pad($r, 36), " $c", $flag, "\n";
}
echo "\nFailures: $fail\n";
unlink($jar);
