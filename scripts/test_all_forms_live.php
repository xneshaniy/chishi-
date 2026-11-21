<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_forms_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function getCsrf($html) {
    preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

// Login
$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['username' => 'naveed', 'password' => 'naveed@1234', 'csrf' => getCsrf($html)]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

$testForms = [
    'Production Run Form' => "{$baseUrl}/index.php?r=production/run",
    'Production List' => "{$baseUrl}/index.php?r=production",
    'Purchase Invoice New' => "{$baseUrl}/index.php?r=purchase/invoices/new",
    'Purchase Invoices List' => "{$baseUrl}/index.php?r=purchase/invoices",
    'Sales Invoice New' => "{$baseUrl}/index.php?r=sales/invoices/new",
    'Sales Invoices List' => "{$baseUrl}/index.php?r=sales/invoices",
    'Salesmen List' => "{$baseUrl}/index.php?r=salesmen",
    'Salesmen New Form' => "{$baseUrl}/index.php?r=salesmen/new",
    'Customers List' => "{$baseUrl}/index.php?r=customers",
    'Customers New Form' => "{$baseUrl}/index.php?r=customers/new",
    'Bank Accounts List' => "{$baseUrl}/index.php?r=cashbank/accounts",
    'Bank Account New' => "{$baseUrl}/index.php?r=cashbank/accounts/new",
    'Bank Payment New' => "{$baseUrl}/index.php?r=cashbank/payments/new",
    'Bank Receipt New' => "{$baseUrl}/index.php?r=cashbank/receipts/new",
    'Bank Transfer New' => "{$baseUrl}/index.php?r=cashbank/transfers/new",
    'Daily Cash Position' => "{$baseUrl}/index.php?r=cashbank/daily-position",
    'Company Expenses Edit/New' => "{$baseUrl}/index.php?r=company-expenses/new",
    'Attendance Index' => "{$baseUrl}/index.php?r=attendance",
    'Payroll Index' => "{$baseUrl}/index.php?r=payroll",
    'Reports - Daily Overview' => "{$baseUrl}/index.php?r=reports/daily-overview",
    'Reports - PnL' => "{$baseUrl}/index.php?r=reports/pnl",
    'Reports - Balance Sheet' => "{$baseUrl}/index.php?r=reports/balance-sheet",
    'Settings - Company' => "{$baseUrl}/index.php?r=settings/company",
    'Settings - Users' => "{$baseUrl}/index.php?r=users",
];

$results = [];
foreach ($testForms as $name => $u) {
    $ch = curl_init($u);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $hasErr = ($code >= 500) || strpos($res, 'Something went wrong') !== false || strpos($res, 'Fatal error') !== false;
    if ($hasErr) {
        echo "[-] FAIL: {$name} => HTTP {$code}\n";
        $results[$name] = false;
    } else {
        echo "[+] PASS: {$name} => HTTP {$code}\n";
        $results[$name] = true;
    }
}

if (file_exists($cookieFile)) unlink($cookieFile);
$passed = count(array_filter($results));
$total = count($results);
echo "\nTotal Passed: {$passed}/{$total}\n";
