<?php
/**
 * Packets-as-base-unit E2E — the required scenario:
 *   Factory produces 1,000 Packets → Main Warehouse holds 1,000.
 *   Issue 300 Packets to a salesman → Main Warehouse 700, salesman van 300, company total 1,000.
 * The issue is posted through the real New Sales Invoice flow (selecting a salesman opens a Goods Issue).
 * No quantity is multiplied by 10 anywhere, and the total is deducted exactly once.
 * Test artifacts are discarded by restoring the pre-test snapshot afterwards.
 */
$base = 'http://127.0.0.1:8080';
$pdo  = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$results = [];
function ok($label, $cond) { global $results; $results[] = [$label, $cond ? 'PASS' : 'FAIL']; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }

function http_req(string $method, string $url, ?array $post = null, array &$cookies = []): array {
    global $base;
    $route = ltrim($url, '/');
    if (str_contains($route, '?')) { [$route, $qs] = explode('?', $route, 2); $full = $base . '/index.php?r=' . rawurlencode($route) . '&' . $qs; }
    else $full = $base . '/index.php?r=' . rawurlencode($route);
    $ch = curl_init($full);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($cookies) { $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; ')); }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($raw, $len);
    foreach (preg_split('/\r?\n/', substr($raw, 0, $len)) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return [$code, $body];
}
function login(string $user, string $pass, array &$cookies): void {
    $cookies = [];
    [$code, $body] = http_req('GET', 'auth/login', null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf on login\n"; exit(1); }
    http_req('POST', 'auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
}
function csrf_from(string $url, array &$cookies): string {
    [$code, $body] = http_req('GET', $url, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $url\n"; exit(1); }
    return $m[1];
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
// Unique per-run snapshot — NEVER a pinned path (a pinned snapshot would silently revert the live dev database to an old state).
$bakPath = 'storage/backups/dev.sqlite.before-stock-e2e-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.bak';
@copy('storage/dev.sqlite', dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bakPath));
// Always restore dev.sqlite at process end — every run reverts to the state captured when this run started.
register_shutdown_function(function () use ($bakPath) {
    clearstatcache();
    $src = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bakPath);
    $dst = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'dev.sqlite';
    if (file_exists($src)) { copy($src, $dst); echo "[cleanup] dev.sqlite restored from this run's snapshot ($bakPath)\n"; }
});

/* ---------- bootstrap a fresh finished product (snapshot is reverted later) ---------- */
$sku = 'E2E-PKT-' . strtoupper(bin2hex(random_bytes(3)));
$pdo->prepare("INSERT INTO products (sku, name, kind, sale_price, issue_rate, min_price, is_active, created_at) VALUES (?, 'E2E Packets Widget', 'finished', 20, 10, 0, 1, ?)")
    ->execute([$sku, date('Y-m-d H:i:s')]);
$pid = (int)$pdo->query("SELECT id FROM products WHERE sku = " . $pdo->quote($sku))->fetchColumn();
$mainWh = (int)$pdo->query("SELECT id FROM warehouses WHERE code = 'WH-MAIN'")->fetchColumn();
$smId = (int)$pdo->query("SELECT id FROM salesmen WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$vanWh = (int)$pdo->query("SELECT warehouse_id FROM salesmen WHERE id = $smId")->fetchColumn();
echo "product=$pid  WH-MAIN=$mainWh  salesman=$smId  van=$vanWh\n\n";

$owner = [];
login('owner', 'owner1234', $owner);

/* ---------- 1. factory produces 1,000 Packets ---------- */
$csrf = csrf_from('production/run', $owner);
[$code] = http_req('POST', 'production/run', [
    'csrf' => $csrf, 'idem_key' => 'e2e-prod-' . bin2hex(random_bytes(5)),
    'date' => $today, 'notes' => 'E2E packets production',
    'items' => ['product_id' => [$pid], 'qty' => ['1000'], 'batch_no' => ['E2E-PKT-B1'], 'line_notes' => ['']],
], $owner);
$run = $pdo->query("SELECT * FROM daily_production WHERE notes = 'E2E packets production' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('production run recorded', $code === 302 && $run);
$runQty = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM daily_production_items WHERE run_id = " . (int)$run['id'])->fetchColumn();
ok('entered qty stored exactly as 1,000 Packets (not 10,000)', $runQty === 1000.0);
$mainAfterProd = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid AND warehouse_id = $mainWh")->fetchColumn();
ok('Main Warehouse = 1,000 Packets after production', $mainAfterProd === 1000.0);
$companyAfterProd = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid")->fetchColumn();
ok('company total = 1,000 Packets after production', $companyAfterProd === 1000.0);

/* ---------- 2. issue 300 Packets to the salesman via New Sales Invoice ---------- */
$csrf = csrf_from('sales/invoices/new', $owner);
[$code, $body] = http_req('POST', 'sales/invoices/new', [
    'csrf' => $csrf, 'idem_key' => 'e2e-issue-' . bin2hex(random_bytes(5)),
    'salesman_id' => (string)$smId, 'date' => $today, 'payment_type' => 'cash',
    'items' => [
        'product_id' => [$pid], 'qty' => ['300'], 'price' => ['999'], // price must be overridden by issue_rate
        'discount' => ['0'], 'tax_rate' => ['0'], 'bonus_qty' => ['0'],
    ],
    'discount' => '0',
], $owner);
$issue = $pdo->query("SELECT * FROM salesman_issues WHERE salesman_id = $smId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('issue posted through the invoice flow', $code === 302 && $issue && $issue['status'] === 'posted');
$issueItem = $pdo->query("SELECT * FROM salesman_issue_items WHERE issue_id = " . (int)$issue['id'] . " ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('paid qty saved as 300 Packets', $issueItem && (float)$issueItem['paid_qty'] === 300.0);
ok('bonus qty saved as 0 Packets', $issueItem && (float)$issueItem['bonus_qty'] === 0.0);
ok('payable uses the salesman issue rate (300 × 10 = 3,000)', $issueItem && (float)$issueItem['issue_rate'] === 10.0 && (float)$issueItem['line_payable'] === 3000.0);

/* ---------- 3. exact stock position: 700 / 300 / 1,000 ---------- */
$mainNow = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid AND warehouse_id = $mainWh")->fetchColumn();
$vanNow = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid AND warehouse_id = $vanWh")->fetchColumn();
$companyNow = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid")->fetchColumn();
ok('Main Warehouse = 700 Packets after issue', $mainNow === 700.0);
ok('salesman van = 300 Packets after issue', $vanNow === 300.0);
ok('company total = 1,000 Packets (no duplication)', $companyNow === 1000.0);
$outQty = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = $pid AND ref_type = 'salesman_issue' AND type = 'salesman_issue_out'")->fetchColumn();
$inQty = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = $pid AND ref_type = 'salesman_issue' AND type = 'salesman_issue_in'")->fetchColumn();
ok('stock ledger moved exactly 300 out of Main Warehouse', $outQty === -300.0);
ok('stock ledger moved exactly 300 into the van', $inQty === 300.0);

/* ---------- 4. reports show Packets as the primary quantity ---------- */
[$code, $body] = http_req('GET', 'reports/company-stock?date=' . $today, null, $owner);
ok('company stock report renders (200)', $code === 200);
ok('company stock report shows produced 1,000 Packets', str_contains($body, '1000'));
ok('company stock report shows factory 700 and van 300', str_contains($body, '700') && str_contains($body, '300'));
ok('company stock report labels quantities in Packets', str_contains($body, 'Packets'));
[$code, $body] = http_req('GET', 'reports/fg-daily?date=' . $today, null, $owner);
ok('FG daily report renders (200)', $code === 200);
ok('FG daily report shows opening/produced/issued/closing in Packets', str_contains($body, 'Packets') && str_contains($body, '1000') && str_contains($body, '700') && str_contains($body, '300'));
[$code, $body] = http_req('GET', 'inventory/finished', null, $owner);
ok('finished stock page shows on hand 700 Packets', $code === 200 && str_contains($body, 'E2E Packets Widget') && str_contains($body, '700'));

$fail = count(array_filter($results, fn($r) => $r[1] === 'FAIL'));
echo "\n", count($results), " checks, $fail failed\n";
exit($fail ? 1 : 0);
