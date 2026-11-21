<?php
/**
 * Test purchase/raw form submissions
 * 1. Add a supplier via POST 
 * 2. Add a raw material product
 * 3. Add a purchase invoice
 */
header('Content-Type: text/plain');

$baseUrl = 'https://software.chishtitobaccocompany.com/index.php?r=';
$loginUrl = 'https://software.chishtitobaccocompany.com/index.php?r=auth/login';

$cj = tempnam(sys_get_temp_dir(), 'ctc_post_');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR  => $cj,
    CURLOPT_COOKIEFILE => $cj,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
]);

// LOGIN
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, false);
$loginHtml = curl_exec($ch);
preg_match('/<input[^>]+name="_csrf"[^>]+value="([^"]+)"/i', $loginHtml, $m);
$csrf = $m[1] ?? '';
echo "CSRF: " . (strlen($csrf) > 0 ? substr($csrf,0,10)."..." : "NOT FOUND") . "\n";

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['_csrf'=>$csrf,'username'=>'naveed','password'=>'naveed@1234']));
curl_exec($ch);
$loginFinal = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
echo "Login redirect: $loginFinal\n";

// ==== TEST 1: Add Supplier via Suppliers tab ====
echo "\n=== TEST 1: Add Supplier ===\n";
// First get the suppliers page to get a fresh CSRF
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, $baseUrl . 'purchase/raw&tab=suppliers');
$html = curl_exec($ch);
preg_match('/<input[^>]+name="_csrf"[^>]+value="([^"]+)"/i', $html, $m2);
$csrf2 = $m2[1] ?? $csrf;

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_URL, $baseUrl . 'purchase/raw&tab=suppliers');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_csrf' => $csrf2,
    'action' => 'add_supplier',
    'name' => 'Khan Tobacco Test',
    'phone' => '0300-1234567',
    'city' => 'Swabi',
]));
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$eff = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$hasErr = stripos($resp,'Something went wrong')!==false || stripos($resp,'SQLSTATE')!==false;
echo "HTTP: $code | Redirect: $eff\n";
if ($hasErr) {
    preg_match('/SQLSTATE.*?(?=<br|<\/p|<div)/si', $resp, $em);
    echo "❌ ERROR: " . strip_tags($em[0] ?? '') . "\n";
    // Show flash messages
    preg_match('/<div[^>]*class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/si', $resp, $em2);
    if ($em2) echo "Flash: " . strip_tags($em2[1]) . "\n";
} else {
    echo "✅ No error visible\n";
    // Check flash success
    preg_match('/(success|successfully|added)/i', $resp, $em3);
    echo "Success match: " . ($em3 ? "yes" : "none") . "\n";
}

// ==== TEST 2: Add Raw Material Product ====
echo "\n=== TEST 2: Add Raw Material ===\n";
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, $baseUrl . 'purchase/raw&tab=suppliers');
$html3 = curl_exec($ch);
preg_match('/<input[^>]+name="_csrf"[^>]+value="([^"]+)"/i', $html3, $m3);
$csrf3 = $m3[1] ?? $csrf;

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_csrf' => $csrf3,
    'action' => 'add_raw_product',
    'product_name' => 'Virginia Leaf Test Grade A',
]));
$resp3 = curl_exec($ch);
$code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hasErr3 = stripos($resp3,'Something went wrong')!==false || stripos($resp3,'SQLSTATE')!==false;
echo "HTTP: $code3\n";
if ($hasErr3) {
    preg_match('/SQLSTATE.*?(?=<br|<\/)/si', $resp3, $em);
    echo "❌ ERROR: " . strip_tags($em[0] ?? 'unknown') . "\n";
} else {
    echo "✅ OK\n";
}

curl_close($ch);
unlink($cj);
echo "\nDone.\n";
