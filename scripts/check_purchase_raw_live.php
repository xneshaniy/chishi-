<?php
/**
 * Check purchase/raw page and all its tabs on live server
 * Also reads the last 50 lines of the PHP error log
 */
$baseUrl = 'https://software.chishtitobaccocompany.com/index.php?r=';
$loginUrl = 'https://software.chishtitobaccocompany.com/index.php?r=auth/login';

// Initialize cURL session with cookie jar
$cj = tempnam(sys_get_temp_dir(), 'ctc_cook_');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR  => $cj,
    CURLOPT_COOKIEFILE => $cj,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

// Step 1: GET login page to grab CSRF
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, false);
$loginHtml = curl_exec($ch);
preg_match('/<input[^>]+name="_csrf"[^>]+value="([^"]+)"/', $loginHtml, $m);
$csrf = $m[1] ?? '';

// Step 2: POST login
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_csrf' => $csrf,
    'username' => 'naveed',
    'password' => 'naveed@1234',
]));
$loginResp = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Login HTTP: $loginCode\n";

// Step 3: Test all purchase/raw tabs
$tabs = ['purchases', 'warehouse', 'summary', 'suppliers', 'daily-usage'];
foreach ($tabs as $tab) {
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_URL, $baseUrl . 'purchase/raw&tab=' . $tab);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $hasError = stripos($html, 'Something went wrong') !== false
             || stripos($html, 'SQL') !== false
             || stripos($html, 'exception') !== false
             || stripos($html, 'Fatal error') !== false
             || stripos($html, 'Warning:') !== false;
    
    // Extract error message if any
    $errMsg = '';
    if ($hasError) {
        if (preg_match('/<div[^>]*class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/si', $html, $em)) {
            $errMsg = ' | ' . strip_tags($em[1]);
        }
        if (preg_match('/SQLSTATE\[.*?\].*?(?=<|\n)/i', $html, $em)) {
            $errMsg = ' | SQL: ' . $em[0];
        }
        if (preg_match('/Fatal error.*?(?=<|\n)/i', $html, $em)) {
            $errMsg = ' | ' . $em[0];
        }
    }
    
    echo "Tab [$tab]: HTTP $code " . ($hasError ? "❌ ERROR$errMsg" : "✅ OK") . "\n";
}

// Step 4: Test invoice_new (Add New Purchase form)
curl_setopt($ch, CURLOPT_URL, $baseUrl . 'purchase/invoices/new&raw=1');
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hasError = stripos($html, 'Something went wrong') !== false || stripos($html, 'SQL') !== false;
echo "Invoice New (raw=1): HTTP $code " . ($hasError ? "❌ ERROR" : "✅ OK") . "\n";

// Step 5: Read error log via special probe
curl_setopt($ch, CURLOPT_URL, 'https://software.chishtitobaccocompany.com/index.php?r=purchase/raw&tab=suppliers');
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "\nSuppliers tab: HTTP $code\n";
if (preg_match('/SQLSTATE.*?(?=<br|<\/)/si', $html, $m)) {
    echo "SQL Error: " . strip_tags($m[0]) . "\n";
}

curl_close($ch);
unlink($cj);
echo "\nDone.\n";
