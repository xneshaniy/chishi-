<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_acc_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function getCsrf($html) {
    preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

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

$accRoutes = [
    'PnL' => "{$baseUrl}/index.php?r=accounting/pnl",
    'Balance Sheet' => "{$baseUrl}/index.php?r=accounting/balance-sheet",
    'Trial Balance' => "{$baseUrl}/index.php?r=accounting/trial-balance",
    'Day Book' => "{$baseUrl}/index.php?r=reports/day-book",
    'Sales Summary' => "{$baseUrl}/index.php?r=reports/sales-summary",
    'Stock Value Report' => "{$baseUrl}/index.php?r=reports/stock-value",
];

foreach ($accRoutes as $name => $u) {
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
    echo ($hasErr ? "[-] FAIL: " : "[+] PASS: ") . "{$name} => HTTP {$code}\n";
}

if (file_exists($cookieFile)) unlink($cookieFile);
