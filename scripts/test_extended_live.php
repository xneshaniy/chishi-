<?php
/**
 * Extended smoke test - all major pages
 */
$baseUrl = 'https://software.chishtitobaccocompany.com/index.php?r=';
$loginUrl = 'https://software.chishtitobaccocompany.com/index.php?r=auth/login';

$cj = tempnam(sys_get_temp_dir(), 'ctc_ext_');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_COOKIEJAR  => $cj,
    CURLOPT_COOKIEFILE => $cj,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
]);

// LOGIN - follow redirects to get session cookie
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, false);
$html = curl_exec($ch);
preg_match('/name="_csrf"[^>]+value="([^"]+)"/i', $html, $m);
$csrf = $m[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_csrf' => $csrf, 'username' => 'naveed', 'password' => 'naveed@1234'
]));
$lr = curl_exec($ch);
$lc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$lurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
echo "Login: HTTP=$lc URL=$lurl\n";
if (strpos($lurl, 'login') !== false) {
    // Try session cookie approach - get dashboard directly
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_URL, $baseUrl . 'dashboard');
    $dh = curl_exec($ch);
    $dc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Dashboard after re-attempt: HTTP=$dc\n";
}

function testPage($ch, $url, $label) {
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $errors = [];
    if (stripos($html, 'Something went wrong') !== false) $errors[] = 'Something went wrong';
    if (preg_match('/SQLSTATE\[.*?\](.*?)(?=<br|<\/)/si', $html, $m)) $errors[] = 'SQL: ' . substr(strip_tags($m[0]), 0, 80);
    if (preg_match('/Fatal error.*?(?=<br|<\/)/si', $html, $m)) $errors[] = 'Fatal: ' . substr(strip_tags($m[0]), 0, 80);
    if (preg_match('/Uncaught.*?Exception.*?(?=<br|<\/|Stack)/si', $html, $m)) $errors[] = 'Exception: ' . substr(strip_tags($m[0]), 0, 80);
    if (preg_match('/Warning:.*?(?=<br)/si', $html, $m)) {
        $w = strip_tags($m[0]);
        if (stripos($w, 'deprecated') === false) $errors[] = 'Warn: ' . substr($w, 0, 60);
    }
    if (strpos($html, 'auth/login') !== false && $code === 200 && strlen($html) < 5000) $errors[] = 'Redirected to login';
    
    $status = empty($errors) ? "✅ OK" : "❌ " . implode(' | ', $errors);
    echo sprintf("%-40s HTTP %d  %s\n", $label, $code, $status);
    return empty($errors);
}

$baseR = $baseUrl;
echo "\n=== PURCHASE/RAW TABS ===\n";
testPage($ch, $baseR.'purchase/raw&tab=purchases', 'purchase/raw (purchases)');
testPage($ch, $baseR.'purchase/raw&tab=warehouse', 'purchase/raw (warehouse)');
testPage($ch, $baseR.'purchase/raw&tab=summary', 'purchase/raw (summary)');
testPage($ch, $baseR.'purchase/raw&tab=suppliers', 'purchase/raw (suppliers)');
testPage($ch, $baseR.'purchase/raw&tab=daily-usage', 'purchase/raw (daily-usage)');

echo "\n=== PURCHASE INVOICE FORMS ===\n";
testPage($ch, $baseR.'purchase/invoices/new&raw=1', 'purchase/invoices/new?raw=1');
testPage($ch, $baseR.'purchase/invoices', 'purchase/invoices list');
testPage($ch, $baseR.'purchase/orders', 'purchase/orders list');
testPage($ch, $baseR.'purchase/orders/new', 'purchase/orders/new');

echo "\n=== REPORTS ===\n";
testPage($ch, $baseR.'reports/simple', 'reports/simple');
testPage($ch, $baseR.'reports/pnl', 'reports/pnl');
testPage($ch, $baseR.'reports/balance-sheet', 'reports/balance-sheet');
testPage($ch, $baseR.'reports/trial-balance', 'reports/trial-balance');
testPage($ch, $baseR.'reports/expense-summary', 'reports/expense-summary');
testPage($ch, $baseR.'reports/collections', 'reports/collections');
testPage($ch, $baseR.'reports/customer-aging', 'reports/customer-aging');
testPage($ch, $baseR.'reports/supplier-aging', 'reports/supplier-aging');
testPage($ch, $baseR.'reports/stock-value', 'reports/stock-value');
testPage($ch, $baseR.'reports/bonus-report', 'reports/bonus-report');
testPage($ch, $baseR.'reports/gifts', 'reports/gifts');

echo "\n=== ACCOUNTING ===\n";
testPage($ch, $baseR.'accounting/journal', 'accounting/journal');
testPage($ch, $baseR.'accounting/ledger', 'accounting/ledger');
testPage($ch, $baseR.'accounting/cash-flow', 'accounting/cash-flow');
testPage($ch, $baseR.'accounting/day-book', 'accounting/day-book');

echo "\n=== HR & PAYROLL ===\n";
testPage($ch, $baseR.'employees', 'employees list');
testPage($ch, $baseR.'employees/new', 'employees/new');
testPage($ch, $baseR.'payroll', 'payroll list');
testPage($ch, $baseR.'attendance', 'attendance');

echo "\n=== SALES ===\n";
testPage($ch, $baseR.'sales/invoices', 'sales/invoices');
testPage($ch, $baseR.'sales/invoices/new', 'sales/invoices/new');
testPage($ch, $baseR.'salesmen', 'salesmen');
testPage($ch, $baseR.'customers', 'customers');

echo "\n=== INVENTORY ===\n";
testPage($ch, $baseR.'warehouse', 'warehouse');
testPage($ch, $baseR.'production', 'production');
testPage($ch, $baseR.'inventory/raw-usage', 'inventory/raw-usage');

echo "\n=== CASH/BANK ===\n";
testPage($ch, $baseR.'cash-bank', 'cash-bank');
testPage($ch, $baseR.'daily-cash', 'daily-cash');
testPage($ch, $baseR.'expenses', 'expenses');

echo "\n=== OTHER ===\n";
testPage($ch, $baseR.'claims', 'claims');
testPage($ch, $baseR.'advances', 'advances');
testPage($ch, $baseR.'loans', 'loans');
testPage($ch, $baseR.'personal-loans', 'personal-loans');
testPage($ch, $baseR.'maintenance', 'maintenance');
testPage($ch, $baseR.'settings', 'settings');

curl_close($ch);
unlink($cj);
echo "\nAll done.\n";
