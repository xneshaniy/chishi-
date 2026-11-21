<?php
/** Scan key pages in all 3 languages for PHP warnings/fatals/notice leaks. */
$routes = ['dashboard','admin','management','sales/invoices','sales/invoices/new','purchase/invoices','purchase/invoices/new','reports','reports/bonus-report','payroll','employees','loans','products','stock/movements','accounting/journal','cashbank/payments','cashbank/receipts','settings/company','settings/bonus-schemes','notifications','users','users/new','audit','accounting/pnl','accounting/trial-balance','reports/sales-summary','reports/price-difference','reports/salary-expense','reports/pending-salary','sales/returns','expenses','admin/approvals','suppliers','customers','reports/salesman-stock','attendance'];

// Login first as owner via curl-like session using PHP streams
$base = 'http://127.0.0.1:8080/index.php?r=';
function fetchRoute(string $url, array $cookies = []): array {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'header' => $cookies ? 'Cookie: ' . implode('; ', $cookies) . "\r\n" : '', 'follow_location' => 1, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $h, $m)) $status = (int)$m[1];
        }
    }
    return [$status, $body ?: ''];
}

echo "Routes: ", count($routes), "\n";
$bad = 0;
foreach (['en', 'roman', 'ur'] as $lang) {
    foreach ($routes as $r) {
        [$status, $body] = fetchRoute($base . urlencode($r) . '&lang=' . $lang);
        if (preg_match('/(Warning|Fatal error|Notice|Undefined|syntax error|Parse error|Deprecated):/', $body)) {
            echo "BAD[$lang $r]: ", trim(preg_match('/(Warning|Fatal error|Notice|Undefined|syntax error|Parse error|Deprecated):[^<]{0,120}/', $body, $m) ? $m[0] : ''), "\n";
            $bad++;
        }
        if ($status >= 500) { echo "HTTP $status [$lang $r]\n"; $bad++; }
    }
}
echo "bad: $bad\n";
