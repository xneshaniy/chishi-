<?php
/**
 * Salesman Account redesign E2E — exactly three sections on one existing Salesman profile page.
 *
 * Scenario: issued Rs 10,000 (Rs 2,000 + Rs 8,000), cash Rs 3,000, bank Rs 2,000,
 * one accepted Packet worth Rs 200 returned. Expected remaining = Rs 4,800.
 *
 * Verifies the page structure, searchable salesman selector, detailed invoice lines,
 * collection posting through the existing payment engine, goods return posting through the
 * existing stock/ledger engine, date filters, receipts, statements and permissions.
 */
$base = 'http://127.0.0.1:8080';
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$results = [];
function ok(string $label, bool $cond): void { global $results; $results[] = [$label, $cond]; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }
function val(string $sql, array $args = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
function one(string $sql, array $args = []): ?array { global $pdo; $s = $pdo->prepare($sql); $s->execute($args); $r = $s->fetch(PDO::FETCH_ASSOC); return $r === false ? null : $r; }
function all(string $sql, array $args = []): array { global $pdo; $s = $pdo->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); }

function http_req(string $route, ?array $post = null, array &$cookies = []): array {
    global $base;
    if (str_contains($route, '?')) { [$r, $qs] = explode('?', $route, 2); $url = $base . '/index.php?r=' . rawurlencode($r) . '&' . $qs; }
    else $url = $base . '/index.php?r=' . rawurlencode($route);
    $ch = curl_init($url);
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
    curl_close($ch);
    return [$code, $body];
}
function login(string $user, string $pass, array &$cookies): void {
    [, $body] = http_req('auth/login', null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf on login\n"; exit(1); }
    http_req('auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
}
function csrf(string $route, array &$cookies): string {
    [, $body] = http_req($route, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $route\n"; exit(1); }
    return $m[1];
}
function chip(string $html, string $label, string $value): bool {
    return preg_match('/' . preg_quote($label, '/') . '<\/div>\s*<div class="sc-val"[^>]*>Rs ' . preg_quote($value, '/') . '/', $html) === 1;
}
function section_html(string $html, string $id): string {
    $start = strpos($html, 'id="' . $id . '"');
    if ($start === false) return '';
    $next = strpos($html, '<section', $start);
    $end = $next === false ? strlen($html) : $next;
    return substr($html, $start, $end - $start);
}

/* ---------- isolated snapshot ---------- */
$bak = 'storage/backups/dev.sqlite.before-account-redesign-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.bak';
@mkdir(dirname(__DIR__) . '/storage/backups', 0777, true);
copy(dirname(__DIR__) . '/storage/dev.sqlite', dirname(__DIR__) . '/' . $bak);
register_shutdown_function(function () use ($bak) {
    $src = dirname(__DIR__) . '/' . $bak;
    if (file_exists($src)) { copy($src, dirname(__DIR__) . '/storage/dev.sqlite'); echo "[cleanup] dev.sqlite restored ($bak)\n"; }
});

/* ---------- isolated fixtures ---------- */
$tz = new DateTimeZone('Asia/Karachi');
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$day1 = (new DateTimeImmutable('-1 day', $tz))->format('Y-m-d');
$day2 = $today;
$prodDate = (new DateTimeImmutable('-2 days', $tz))->format('Y-m-d');
$stamp = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$now = $today . ' 08:00:00';
$vanCode = 'E2E-AC-V' . $stamp; $smCode = 'E2E-AC-S' . $stamp; $sku = 'E2E-AC-P' . $stamp;
$pdo->exec("INSERT INTO warehouses (code, name, type, is_active, created_at) VALUES ('$vanCode', 'E2E Account Van $stamp', 'salesman', 1, '$now')");
$vanId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO salesmen (code, name, phone, salary, join_date, is_active, warehouse_id, created_at) VALUES ('$smCode', 'E2E Account Salesman', '03000000000', 0, '2026-01-01', 1, $vanId, '$now')");
$smId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO products (sku, name, kind, sale_price, issue_rate, min_price, is_active, created_at) VALUES ('$sku', 'E2E Account Packet', 'finished', 200, 200, 0, 1, '$now')");
$pid = (int)$pdo->lastInsertId();
$mainId = (int)val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
$cashAcct = (int)val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY id LIMIT 1");
$bankAcct = (int)val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY id LIMIT 1");
$cashGl = (int)val('SELECT gl_account_id FROM cash_bank_accounts WHERE id = ?', [$cashAcct]);
$bankGl = (int)val('SELECT gl_account_id FROM cash_bank_accounts WHERE id = ?', [$bankAcct]);
echo "salesman=$smId van=$vanId product=$pid main=$mainId | day1=$day1 day2=$day2\n\n";

$owner = [];
login('owner', 'owner1234', $owner);

echo "=== 1. Seed 50 Packets and two goods invoices ===\n";
$ignored = http_req('production/run', [
    'csrf' => csrf('production/run', $owner), 'idem_key' => 'e2e-acc-prod-' . $stamp,
    'date' => $prodDate, 'notes' => 'E2E account redesign',
    'items' => ['product_id' => [$pid], 'qty' => ['50'], 'batch_no' => ['E2E-AB'], 'line_notes' => ['']],
], $owner);
foreach ([[$day1, 10, 2000.0, 'a'], [$day2, 40, 8000.0, 'b']] as [$date, $qty, $expected, $tag]) {
    $ignored = http_req('sales/invoices/new', [
        'csrf' => csrf('sales/invoices/new', $owner), 'idem_key' => 'e2e-acc-issue-' . $tag . '-' . $stamp,
        'salesman_id' => (string)$smId, 'date' => $date, 'payment_type' => 'cash',
        'items' => ['product_id' => [$pid], 'qty' => [(string)$qty], 'price' => ['999'], 'discount' => ['0'], 'tax_rate' => ['0'], 'bonus_qty' => ['0']],
        'discount' => '0',
    ], $owner);
    $issue = one("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'credit_issue' ORDER BY id DESC LIMIT 1", [$smId]);
    $line = one('SELECT * FROM salesman_issue_items WHERE issue_id = ? ORDER BY id DESC LIMIT 1', [(int)$issue['id']]);
    ok("invoice $tag payable Rs " . number_format($expected, 2), $issue['status'] === 'posted' && abs((float)$line['line_payable'] - $expected) < 0.01);
    if ($tag === 'a') $issue1 = $issue; else $issue2 = $issue;
}

echo "\n=== 2. One page, three sections, searchable selector ===\n";
[$code, $body] = http_req('salesmen/' . $smId . '/account', null, $owner);
ok('account page loads inside the Salesman section', $code === 200);
preg_match_all('/<section[^>]*id="([^"]+)"/', $body, $m);
ok('exactly three main sections exist', count($m[1]) === 3);
ok('sections are in the required order', ($m[1] ?? []) === ['goods-issued', 'money-collection', 'goods-return']);
ok('searchable salesman selector is present', strpos($body, 'id="accountSmSearch"') !== false && strpos($body, 'id="accountSmSelect"') !== false);
ok('salesman options carry searchable text', strpos($body, 'data-hay=') !== false);
ok('tab bar includes Account tab', preg_match('/class="on"[^>]*>Account/', $body) === 1 || strpos($body, '>Account</a>') !== false);
ok('print options are available', strpos($body, 'Print This Account') !== false && strpos($body, 'recovery%2Fstatement') !== false);

$goodsSection = section_html($body, 'goods-issued');
$moneySection = section_html($body, 'money-collection');
$returnSection = section_html($body, 'goods-return');
ok('Goods Issued shows all four required summary labels',
    strpos($goodsSection, 'Total Products Issued') !== false
    && strpos($goodsSection, 'Total Issued Quantity (Packets)') !== false
    && strpos($goodsSection, 'Total Goods Issued Amount') !== false
    && strpos($goodsSection, 'Outstanding Invoice Amount') !== false);
ok('Total Products Issued = 1', preg_match('/Total Products Issued<\/div>\s*<div class="sc-val">1<\/div>/', $goodsSection) === 1);
ok('Total Issued Quantity = 50 Packets', preg_match('/Total Issued Quantity \(Packets\)<\/div>\s*<div class="sc-val">50<\/div>/', $goodsSection) === 1);
ok('Total Goods Issued Amount = Rs 10,000.00', chip($goodsSection, 'Total Goods Issued Amount', '10,000.00'));
ok('Outstanding Invoice Amount = Rs 10,000.00', chip($goodsSection, 'Outstanding Invoice Amount', '10,000.00'));
foreach (['Date', 'Invoice Number', 'Product Name', 'Regular Qty (Packets)', 'Bonus Qty (Packets)', 'Total Qty', 'Issue Rate', 'Total Payable Amount'] as $col) {
    ok("issued table column: $col", strpos($goodsSection, $col) !== false);
}
ok('both existing invoice numbers appear automatically', strpos($goodsSection, $issue1['issue_no']) !== false && strpos($goodsSection, $issue2['issue_no']) !== false);
ok('Day 1 regular qty 10 is shown', preg_match('/' . preg_quote($issue1['issue_no'], '/') . '[\s\S]{0,500}?<td class="num">10<\/td>/', $goodsSection) === 1);
ok('Day 2 regular qty 40 is shown', preg_match('/' . preg_quote($issue2['issue_no'], '/') . '[\s\S]{0,500}?<td class="num">40<\/td>/', $goodsSection) === 1);

ok('Money Collection has all required summary figures', chip($moneySection, 'Total Goods Amount Payable', '10,000.00')
    && chip($moneySection, 'Total Cash Received', '0.00') && chip($moneySection, 'Total Bank Received', '0.00')
    && chip($moneySection, 'Total Money Collected', '0.00') && chip($moneySection, 'Remaining Amount Payable', '10,000.00'));
ok('Add Money Collection form has all required fields',
    strpos($moneySection, 'id="moneyCollectionForm"') !== false
    && strpos($moneySection, 'name="date"') !== false && strpos($moneySection, 'name="cash_amount"') !== false
    && strpos($moneySection, 'name="bank_amount"') !== false && strpos($moneySection, 'name="bank_account_id"') !== false
    && strpos($moneySection, 'name="bank_reference"') !== false && strpos($moneySection, 'name="notes"') !== false);
ok('cash + bank total is automatically calculated', strpos($moneySection, 'Total Collected (auto)') !== false && strpos($moneySection, 'id="moneyTotalHidden"') !== false);
ok('collection history table is present', strpos($moneySection, 'Collection History') !== false);
ok('Goods Return form has product, qty, invoice, rate and value fields',
    strpos($returnSection, 'id="goodsReturnForm"') !== false && strpos($returnSection, 'name="product_id"') !== false
    && strpos($returnSection, 'name="qty"') !== false && strpos($returnSection, 'name="related_issue_id"') !== false
    && strpos($returnSection, 'id="accountRetRate"') !== false && strpos($returnSection, 'id="accountRetValue"') !== false);
ok('goods return form and history have required labels', strpos($returnSection, 'Returned Goods Value') !== false && strpos($returnSection, 'Return Status') !== false);

echo "\n=== 3. Add Money Collection: cash 3,000 + bank 2,000 through this page ===\n";
$payKey = 'e2e-acc-pay-' . $stamp;
$ignored = http_req('salesmen/' . $smId . '/account', [
    'csrf' => csrf('salesmen/' . $smId . '/account', $owner), 'recovery_action' => 'payment',
    'date' => $day2, 'cash_amount' => '3000', 'bank_amount' => '2000', 'total_amount' => '5000',
    'cash_account_id' => (string)$cashAcct, 'bank_account_id' => (string)$bankAcct,
    'bank_reference' => 'E2E-ACC-TRX-' . $stamp, 'notes' => 'E2E account collection',
    'idem_key' => $payKey, 'allocation_mode' => 'auto',
], $owner);
$payment = one('SELECT * FROM salesman_payments WHERE idem_key = ?', [$payKey]);
ok('collection posted through the existing payment table', $payment && abs((float)$payment['total_amount'] - 5000) < 0.01);
ok('derived method is split', $payment && $payment['payment_method'] === 'split');
$allocs = all('SELECT spa.amount, i.id, i.issue_no FROM salesman_payment_allocations spa JOIN salesman_issues i ON i.id = spa.issue_id WHERE spa.payment_id = ? ORDER BY i.date, i.id', [(int)($payment['id'] ?? 0)]);
ok('collection allocated oldest-invoice-first (2,000 then 3,000)', count($allocs) === 2 && abs((float)$allocs[0]['amount'] - 2000) < 0.01 && abs((float)$allocs[1]['amount'] - 3000) < 0.01);
ok('first allocation is Day 1 invoice', $allocs && (int)$allocs[0]['id'] === (int)$issue1['id']);
$jl = all('SELECT * FROM journal_lines WHERE entry_id = ?', [(int)($payment['journal_entry_id'] ?? 0)]);
$drCash = 0.0; $drBank = 0.0; $cr = 0.0;
foreach ($jl as $ln) {
    if ((int)$ln['account_id'] === $cashGl && (float)$ln['debit'] > 0) $drCash += (float)$ln['debit'];
    if ((int)$ln['account_id'] === $bankGl && (float)$ln['debit'] > 0) $drBank += (float)$ln['debit'];
    $cr += (float)$ln['credit'];
}
ok('cash posts to selected Cash-in-Hand GL', abs($drCash - 3000) < 0.01);
ok('bank posts to selected Bank GL', abs($drBank - 2000) < 0.01);
ok('receivable credited once for Rs 5,000', abs($cr - 5000) < 0.01);
$ledgerCredit = (float)val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment' AND doc_id = ?", [$smId, (int)$payment['id']]);
ok('salesman ledger credited once', abs($ledgerCredit - 5000) < 0.01);

[$code, $body] = http_req('salesmen/' . $smId . '/account', null, $owner);
$moneySection = section_html($body, 'money-collection');
ok('Cash Received summary = Rs 3,000.00', chip($moneySection, 'Total Cash Received', '3,000.00'));
ok('Bank Received summary = Rs 2,000.00', chip($moneySection, 'Total Bank Received', '2,000.00'));
ok('Total Money Collected = Rs 5,000.00', chip($moneySection, 'Total Money Collected', '5,000.00'));
ok('Remaining before return = Rs 5,000.00', chip($moneySection, 'Remaining Amount Payable', '5,000.00'));
ok('collection history shows cash, bank, total and remaining balance', strpos($moneySection, '3,000.00') !== false && strpos($moneySection, '2,000.00') !== false && strpos($moneySection, '5,000.00') !== false);
ok('receipt link is available in the collection history', strpos($moneySection, 'payments%2F' . (int)$payment['id'] . '%2Fprint') !== false);

echo "\n=== 4. Goods Return: one accepted Packet worth Rs 200 ===\n";
$ignored = http_req('salesmen/' . $smId . '/account', [
    'csrf' => csrf('salesmen/' . $smId . '/account', $owner), 'recovery_action' => 'return',
    'date' => $day2, 'related_issue_id' => (string)$issue1['id'], 'product_id' => (string)$pid,
    'qty' => '1', 'return_condition' => 'good', 'reference' => 'E2E-ACC-GR-' . $stamp,
    'notes' => 'E2E accepted packet',
], $owner);
$ret = one("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'salesman_return' ORDER BY id DESC LIMIT 1", [$smId]);
$retLine = $ret ? one('SELECT * FROM salesman_issue_items WHERE issue_id = ? ORDER BY id DESC LIMIT 1', [(int)$ret['id']]) : null;
ok('goods return posted through the existing return engine', $ret && $ret['status'] === 'posted');
ok('return linked to the Day 1 invoice', $ret && (int)($ret['related_issue_id'] ?? 0) === (int)$issue1['id']);
ok('original issue rate auto-fetched at Rs 200', $retLine && abs((float)$retLine['issue_rate'] - 200) < 0.01);
ok('accepted return credit = Rs 200', $retLine && abs((float)$retLine['line_payable'] - 200) < 0.01);
$mainAfter = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $mainId]);
$vanAfter = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $vanId]);
ok('accepted saleable Packet restocked to Main Warehouse', $mainAfter === 1.0);
ok('accepted Packet removed from the van (50 -> 49)', $vanAfter === 49.0);
$returnJournals = (int)val("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'salesman_return' AND reference_id = ?", [(int)($ret['id'] ?? 0)]);
ok('return accounting posted exactly once', $returnJournals === 1);
ok('goods return created no cash/bank payment', (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]) === 1);

echo "\n=== 5. Required final result: Rs 4,800 ===\n";
[$code, $body] = http_req('salesmen/' . $smId . '/account', null, $owner);
$goodsSection = section_html($body, 'goods-issued');
$moneySection = section_html($body, 'money-collection');
$returnSection = section_html($body, 'goods-return');
ok('Goods Issued outstanding = Rs 4,800.00', chip($goodsSection, 'Outstanding Invoice Amount', '4,800.00'));
ok('Remaining Amount Payable = Rs 4,800.00 (authoritative expectation)', chip($moneySection, 'Remaining Amount Payable', '4,800.00'));
ok('Approved Goods Return Credit = Rs 200.00', chip($returnSection, 'Approved Goods Return Credit', '200.00'));
ok('Accepted Saleable Qty = 1 Packet', preg_match('/Accepted Saleable Qty<\/div>\s*<div class="sc-val">1<\/div>/', $returnSection) === 1);
ok('return history shows the required columns', strpos($returnSection, 'Total Return Value') !== false && strpos($returnSection, 'Return Date') !== false);
ok('return row shows product, qty, rate, value, date and status',
    strpos($returnSection, $sku) !== false && strpos($returnSection, '200.00') !== false
    && strpos($returnSection, 'Accepted / Saleable') !== false);
ok('return link to original invoice is visible', strpos($returnSection, $issue1['issue_no']) !== false);

echo "\n=== 6. Date-wise history, print and receipt ===\n";
[$code, $filtered] = http_req('salesmen/' . $smId . '/account?from=' . $day2 . '&to=' . $day2, null, $owner);
$fg = section_html($filtered, 'goods-issued');
$fc = section_html($filtered, 'money-collection');
$fr = section_html($filtered, 'goods-return');
ok('date filter loads', $code === 200);
ok('filtered issued table excludes Day 1 invoice', strpos($fg, $issue1['issue_no']) === false && strpos($fg, $issue2['issue_no']) !== false);
ok('filtered collection history still shows the collection', strpos($fc, $payment['receipt_no']) !== false);
ok('filtered goods return history shows the return', strpos($fr, $ret['issue_no']) !== false);
[$code, $statement] = http_req('salesmen/' . $smId . '/recovery/statement?from=' . $day1 . '&to=' . $day2, null, $owner);
ok('date-wise printable statement opens', $code === 200 && strpos($statement, 'SALESMAN RECOVERY STATEMENT') !== false);
ok('statement shows Rs 4,800.00', strpos($statement, '4,800.00') !== false);
[$code, $receipt] = http_req('salesmen/' . $smId . '/payments/' . (int)$payment['id'] . '/print', null, $owner);
ok('individual collection receipt opens', $code === 200 && strpos($receipt, 'SALESMAN RECOVERY RECEIPT') !== false);
ok('receipt links back to the account page', strpos($receipt, 'Back to Account') !== false);
ok('receipt shows the allocated invoices', strpos($receipt, $issue1['issue_no']) !== false && strpos($receipt, $issue2['issue_no']) !== false);

echo "\n=== 7. Legacy pages and duplicate protection still work ===\n";
[$code, $recovery] = http_req('salesmen/' . $smId . '/recovery', null, $owner);
ok('previous Recovery tab still renders', $code === 200 && strpos($recovery, 'Recovery / Payment Collection') !== false);
ok('previous Recovery tab shows Rs 4,800.00', chip($recovery, 'Total Outstanding Amount', '4,800.00'));
ok('legacy /payments URL still redirects', http_req('salesmen/' . $smId . '/payments', null, $owner)[0] === 302);
$beforePay = (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]);
$ignored = http_req('salesmen/' . $smId . '/account', [
    'csrf' => csrf('salesmen/' . $smId . '/account', $owner), 'recovery_action' => 'payment',
    'date' => $day2, 'cash_amount' => '3000', 'bank_amount' => '2000', 'total_amount' => '5000',
    'cash_account_id' => (string)$cashAcct, 'bank_account_id' => (string)$bankAcct,
    'bank_reference' => 'E2E-ACC-TRX-' . $stamp, 'idem_key' => $payKey,
], $owner);
ok('duplicate collection POST created no second payment', (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]) === $beforePay);

echo "\n=== 8. Viewer permissions unchanged ===\n";
$viewer = [];
login('accountant1', 'Accountant@1234x', $viewer);
[$code, $viewBody] = http_req('salesmen/' . $smId . '/account', null, $viewer);
ok('viewer can read the account page', $code === 200);
ok('viewer does not see entry forms', strpos($viewBody, 'moneyCollectionForm') === false && strpos($viewBody, 'goodsReturnForm') === false);
[$code] = http_req('salesmen/' . $smId . '/account', [
    'recovery_action' => 'payment', 'date' => $day2, 'cash_amount' => '100',
    'bank_amount' => '0', 'total_amount' => '100', 'idem_key' => 'e2e-acc-forbidden-' . $stamp,
], $viewer);
ok('viewer POST is forbidden (403)', $code === 403);

$fail = count(array_filter($results, fn($r) => !$r[1]));
echo "\nSALESMAN ACCOUNT REDESIGN E2E: ", count($results), " tests, $fail failures\n";
exit($fail ? 1 : 0);
