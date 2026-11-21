<?php
/** Log in as owner via HTTP, hit every authenticated route, report any residual `) ?>` artifacts in bodies. */
$base = 'http://127.0.0.1:8080';
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg($GLOBALS['base'] . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    $headers = substr($out, 0, $pos);
    $respBody = substr($out, $pos + 2);
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', $headers, $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => $respBody];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$csrf = $m[1];
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $csrf, 'username' => 'owner', 'password' => 'owner1234']);
echo "login: ", $lr['status'], "\n";
if ($lr['status'] !== 302) { echo "LOGIN FAILED\n", substr($lr['body'], 0, 300), "\n"; exit(1); }

$routes = ['dashboard','employees','payroll','attendance','loans','loans/new','purchase','purchase/orders','purchase/invoices','purchase/raw','products','raw-stock','finished-stock','inventory/movements','stock/adjustments','stock/transfers','stock/opening','stock/counts','production','production/boms','production/orders','cashbank/accounts','cashbank/receipts','cashbank/payments','cashbank/transfers','cashbank/cheques','cashbank/reconcile','expenses','expenses/categories','sales','sales/invoices','sales/orders','salesmen','suppliers','customers','claims','claims/goods','settings/company','settings/branches','settings/warehouses','settings/fiscal','settings/opening','settings/bonus-schemes','settings/backup','admin','admin/approvals','audit','vehicles','maintenance','reports','reports/fg-daily','reports/raw-daily','reports/salesman-stock','reports/bonus','reports/day-book','reports/stock-value','reports/pending-salary','notifications','accounting/accounts','accounting/journal','accounting/closing'];
$needle = ') ?>';
$bad = 0; $total = 0;
foreach ($routes as $route) {
    $r = http('GET', '/index.php?r=' . $route, $jar);
    $total++;
    if ($r['status'] >= 500 || $r['status'] === 404) { echo "HTTP {$r['status']}  $route\n"; $bad++; continue; }
    $n = substr_count($r['body'], $needle);
    if ($n) { echo "ARTIFACT($n) in $route\n"; $bad++; }
}
echo "routes checked: $total, problems: $bad\n";
unlink($jar);
