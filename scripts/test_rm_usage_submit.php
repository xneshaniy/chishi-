<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_rm_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function getCsrf($html) {
    preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

// 1. Login
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

// Test A: Submitting via purchase/raw?tab=daily-usage
echo "=== Test A: purchase/raw (action=add_daily_usage) ===\n";
$ch = curl_init("{$baseUrl}/index.php?r=purchase/raw&tab=daily-usage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$pageA = curl_exec($ch);
curl_close($ch);

$csrfA = getCsrf($pageA);
preg_match('/name="product_id"[^>]*>.*?<option\s+value="(\d+)"/s', $pageA, $mProd);
preg_match('/name="warehouse_id"[^>]*>.*?<option\s+value="(\d+)"/s', $pageA, $mWh);
$prodId = $mProd[1] ?? 1;
$whId = $mWh[1] ?? 1;

$postDataA = [
    'csrf' => $csrfA,
    'action' => 'add_daily_usage',
    'usage_date' => date('Y-m-d'),
    'product_id' => $prodId,
    'warehouse_id' => $whId,
    'qty_kg' => '12.500',
    'note' => 'QA test from purchase/raw'
];

$ch = curl_init("{$baseUrl}/index.php?r=purchase/raw");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataA);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resA = curl_exec($ch);
$codeA = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$urlA = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

echo "Code: {$codeA}, Final URL: {$urlA}\n";
if (strpos($resA, 'Something went wrong') !== false) {
    echo "[-] FAILED: Exception thrown!\n";
} else {
    echo "[+] SUCCESS: No exception on purchase/raw daily usage submission!\n";
}

// Test B: Submitting via inventory/raw-usage
echo "\n=== Test B: inventory/raw-usage ===\n";
$ch = curl_init("{$baseUrl}/index.php?r=inventory/raw-usage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$pageB = curl_exec($ch);
curl_close($ch);

$csrfB = getCsrf($pageB);
$postDataB = [
    'csrf' => $csrfB,
    'date' => date('Y-m-d'),
    'material_id' => $prodId,
    'warehouse_id' => $whId,
    'qty_kg' => '8.250',
    'notes' => 'QA test from inventory/raw-usage'
];

$ch = curl_init("{$baseUrl}/index.php?r=inventory/raw-usage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataB);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resB = curl_exec($ch);
$codeB = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$urlB = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

echo "Code: {$codeB}, Final URL: {$urlB}\n";
if (strpos($resB, 'Something went wrong') !== false) {
    echo "[-] FAILED: Exception thrown!\n";
} else {
    echo "[+] SUCCESS: No exception on inventory/raw-usage submission!\n";
}

if (file_exists($cookieFile)) unlink($cookieFile);
