<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_prod_cookie.txt';
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

// GET production/run
$ch = curl_init("{$baseUrl}/index.php?r=production/run");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$runHtml = curl_exec($ch);
curl_close($ch);

$csrf = getCsrf($runHtml);
preg_match('/name="target_warehouse_id"[^>]*>.*?<option\s+value="(\d+)"/s', $runHtml, $mWh);
preg_match('/name="items\[0\]\[product_id\]"[^>]*>.*?<option\s+value="(\d+)"/s', $runHtml, $mProd);

$targetWh = $mWh[1] ?? 1;
$fgProd = $mProd[1] ?? 1;

echo "Target WH: {$targetWh}, FG Product: {$fgProd}\n";

$postData = [
    'csrf' => $csrf,
    'date' => date('Y-m-d'),
    'shift' => 'morning',
    'target_warehouse_id' => $targetWh,
    'notes' => 'QA Live Test Production Run',
    'auto_deduct_rm' => '1',
    'items' => [
        [
            'product_id' => $fgProd,
            'qty' => '100'
        ]
    ]
];

$ch = curl_init("{$baseUrl}/index.php?r=production/run");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
if (file_exists($cookieFile)) unlink($cookieFile);

echo "Code: {$code}\n";
echo "Final URL: {$finalUrl}\n";
if (strpos($res, 'Something went wrong') !== false) {
    echo "[-] FAILED: Exception thrown!\n";
    echo substr(strip_tags($res), 0, 400) . "\n";
} else {
    echo "[+] SUCCESS: Production run created and rendered without any error!\n";
}
