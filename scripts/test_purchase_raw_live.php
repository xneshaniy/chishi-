<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_cookie_purchase.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Login
$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'username' => 'naveed',
    'password' => 'naveed@1234',
    'csrf' => $csrf
]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

// 2. Test URLs
$urls = [
    'Raw Purchase Default' => "{$baseUrl}/index.php?r=purchase/raw",
    'Raw Purchase Invoices' => "{$baseUrl}/index.php?r=purchase/raw&tab=invoices",
    'Raw Purchase Stock' => "{$baseUrl}/index.php?r=purchase/raw&tab=stock",
    'Raw Purchase Suppliers' => "{$baseUrl}/index.php?r=purchase/raw&tab=suppliers",
    'Raw Purchase Daily Usage' => "{$baseUrl}/index.php?r=purchase/raw&tab=daily-usage",
    'Dedicated Daily Raw Usage' => "{$baseUrl}/index.php?r=inventory/raw-usage",
    'Warehouse Overview' => "{$baseUrl}/index.php?r=warehouse",
    'Production List' => "{$baseUrl}/index.php?r=production",
    'Sales Invoices' => "{$baseUrl}/index.php?r=sales/invoices",
    'Employees List' => "{$baseUrl}/index.php?r=employees",
    'Personal Loans (Dasti)' => "{$baseUrl}/index.php?r=personal-loans",
    'Cash & Bank Accounts' => "{$baseUrl}/index.php?r=cashbank/accounts",
    'Daily Cash Position' => "{$baseUrl}/index.php?r=cashbank/daily-position",
    'Expenses' => "{$baseUrl}/index.php?r=expenses",
    'Reports Simple' => "{$baseUrl}/index.php?r=reports/simple",
];

$errors = [];
foreach ($urls as $name => $u) {
    $ch = curl_init($u);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $is500 = ($code >= 500) || strpos($res, 'Something went wrong') !== false || strpos($res, 'Fatal error') !== false;
    if ($is500) {
        echo "[-] FAIL: {$name} ({$u}) => HTTP {$code}\n";
        echo "    Preview: " . substr(strip_tags($res), 0, 300) . "\n";
        $errors[] = $name;
    } else {
        echo "[+] PASS: {$name} => HTTP {$code}\n";
    }
}

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\nSummary: " . (count($urls) - count($errors)) . "/" . count($urls) . " pages passed!\n";
