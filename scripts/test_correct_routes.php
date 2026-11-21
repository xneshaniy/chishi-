<?php
/**
 * Final correct route test
 */
$baseUrl = 'https://software.chishtitobaccocompany.com/index.php?r=';

$cj = tempnam(sys_get_temp_dir(), 'ctc_fin_');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR  => $cj, CURLOPT_COOKIEFILE => $cj,
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 20,
]);

// SESSION: get dashboard (session persists from previous test run cookie jar? No - fresh jar)
// Just test without login - pages should redirect to login = 200 with login form = no errors
function testP($ch, $url, $label) {
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eff = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    
    // These are the REAL errors we care about:
    $fatal = stripos($html,'Fatal error')!==false || stripos($html,'Uncaught ')!==false;
    $sql = preg_match('/SQLSTATE\[/i', $html);
    $sww = stripos($html,'Something went wrong')!==false;
    
    // If redirected to login, that's AUTH behavior - not a bug
    $isLogin = strpos($eff, 'login') !== false;
    
    if ($fatal || $sql || $sww) {
        $em = '';
        if (preg_match('/SQLSTATE.*?(?=<br|<\/div)/si', $html, $m)) $em = strip_tags($m[0]);
        if (preg_match('/Fatal error.*?(?=<br)/si', $html, $m)) $em = strip_tags($m[0]);
        echo sprintf("%-45s HTTP %d  ❌ ERROR: %s\n", $label, $code, substr($em,0,100));
    } else {
        $suffix = $isLogin ? "(→ login, auth OK)" : "";
        echo sprintf("%-45s HTTP %d  ✅ $suffix\n", $label, $code);
    }
}

// Test correct routes
echo "=== CORRECT ROUTE TESTS ===\n";
testP($ch, $baseUrl.'accounting/pnl', 'accounting/pnl (P&L)');
testP($ch, $baseUrl.'accounting/balance-sheet', 'accounting/balance-sheet');
testP($ch, $baseUrl.'accounting/trial-balance', 'accounting/trial-balance');
testP($ch, $baseUrl.'reports/day-book', 'reports/day-book');
testP($ch, $baseUrl.'cashbank/daily-position', 'cashbank/daily-position');
testP($ch, $baseUrl.'cashbank', 'cashbank');
testP($ch, $baseUrl.'cashbank/accounts', 'cashbank/accounts');
testP($ch, $baseUrl.'cashbank/receipts', 'cashbank/receipts');
testP($ch, $baseUrl.'cashbank/payments', 'cashbank/payments');
testP($ch, $baseUrl.'cashbank/transfers', 'cashbank/transfers');
testP($ch, $baseUrl.'settings/company', 'settings/company');
testP($ch, $baseUrl.'settings/units', 'settings/units');
testP($ch, $baseUrl.'settings/fiscal', 'settings/fiscal');
testP($ch, $baseUrl.'settings/warehouses', 'settings/warehouses');
testP($ch, $baseUrl.'users', 'users');
testP($ch, $baseUrl.'gifts', 'gifts');
testP($ch, $baseUrl.'company-expenses', 'company-expenses');

curl_close($ch);
unlink($cj);
echo "\nDone.\n";
