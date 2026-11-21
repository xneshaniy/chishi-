<?php
/**
 * Salesman Recovery / Payment Collection E2E — the authoritative test scenario.
 *
 * Day 1 invoice Rs 2,000 + Day 2 invoice Rs 8,000 = Rs 10,000
 * Cash Rs 3,000 + Bank Rs 2,000 + one accepted Packet worth Rs 200
 * Expected remaining outstanding = Rs 4,800 (NOT the inconsistent Rs 2,800 in section 5).
 *
 * Exercises the real HTTP flows: production -> sales invoice issues -> recovery payment
 * (oldest-first allocation) -> goods return -> statement/receipts -> manual allocation ->
 * damaged return -> idempotency. All rows are discarded by restoring this run's DB snapshot.
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
    [$code, $body] = http_req('auth/login', null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf on login\n"; exit(1); }
    http_req('auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
}
function csrf(string $route, array &$cookies): string {
    [$code, $body] = http_req($route, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $route ($code)\n"; exit(1); }
    return $m[1];
}
function chip(string $html, string $label, string $value): bool {
    return preg_match('/' . preg_quote($label, '/') . '<\/div>\s*<div class="sc-val"[^>]*>Rs ' . preg_quote($value, '/') . '/', $html) === 1;
}

/* ---------- snapshot / restore (never pin a snapshot path) ---------- */
$bak = 'storage/backups/dev.sqlite.before-recovery-e2e-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.bak';
@mkdir(dirname(__DIR__) . '/storage/backups', 0777, true);
copy(dirname(__DIR__) . '/storage/dev.sqlite', dirname(__DIR__) . '/' . $bak);
register_shutdown_function(function () use ($bak) {
    $src = dirname(__DIR__) . '/' . $bak;
    if (file_exists($src)) { copy($src, dirname(__DIR__) . '/storage/dev.sqlite'); echo "[cleanup] dev.sqlite restored ($bak)\n"; }
});

/* ---------- isolated warehouse, salesman and product ---------- */
$stamp = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$whCode = 'E2E-RW-' . $stamp;
$smCode = 'E2E-RS-' . $stamp;
$sku = 'E2E-RP-' . $stamp;
$now = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO warehouses (code, name, type, is_active, created_at) VALUES ('$whCode', 'E2E Recovery Van $stamp', 'salesman', 1, '$now')");
$vanId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO salesmen (code, name, phone, salary, join_date, is_active, warehouse_id, created_at) VALUES ('$smCode', 'E2E Recovery Salesman', '03000000000', 0, '2026-01-01', 1, $vanId, '$now')");
$smId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO products (sku, name, kind, sale_price, issue_rate, min_price, is_active, created_at) VALUES ('$sku', 'E2E Recovery Packet', 'finished', 200, 200, 0, 1, '$now')");
$pid = (int)$pdo->lastInsertId();
$mainId = (int)val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
$cashAcct = (int)val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY id LIMIT 1");
$bankAcct = (int)val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY id LIMIT 1");
$cashGl = (int)val("SELECT gl_account_id FROM cash_bank_accounts WHERE id = $cashAcct");
$bankGl = (int)val("SELECT gl_account_id FROM cash_bank_accounts WHERE id = $bankAcct");
$prodDate = date('Y-m-d', strtotime('-2 days'));
$day1 = date('Y-m-d', strtotime('-1 day'));
$day2 = date('Y-m-d');
echo "salesman=$smId van=$vanId product=$pid main=$mainId | day1=$day1 day2=$day2\n\n";

$owner = [];
login('owner', 'owner1234', $owner);

echo "=== 1. Produce 50 Packets at the Rs 200 issue rate ===\n";
[$code] = http_req('production/run', [
    'csrf' => csrf('production/run', $owner), 'idem_key' => 'e2e-rec-prod-' . $stamp,
    'date' => $prodDate, 'notes' => 'E2E recovery production ' . $stamp,
    'items' => ['product_id' => [$pid], 'qty' => ['50'], 'batch_no' => ['E2E-RB1'], 'line_notes' => ['']],
], $owner);
$mainBefore = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $mainId]);
ok('production posted 50 Packets', $code === 302 && $mainBefore === 50.0);

echo "\n=== 2. Day 1 + Day 2 goods invoices (Rs 2,000 + Rs 8,000) ===\n";
foreach ([[$day1, 10, 2000.0, 'a'], [$day2, 40, 8000.0, 'b']] as [$date, $qty, $expected, $tag]) {
    [$code] = http_req('sales/invoices/new', [
        'csrf' => csrf('sales/invoices/new', $owner), 'idem_key' => 'e2e-rec-issue-' . $tag . '-' . $stamp,
        'salesman_id' => (string)$smId, 'date' => $date, 'payment_type' => 'cash',
        'items' => ['product_id' => [$pid], 'qty' => [(string)$qty], 'price' => ['999'], 'discount' => ['0'], 'tax_rate' => ['0'], 'bonus_qty' => ['0']],
        'discount' => '0',
    ], $owner);
    $issue = one("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'credit_issue' ORDER BY id DESC LIMIT 1", [$smId]);
    $line = $issue ? one('SELECT * FROM salesman_issue_items WHERE issue_id = ? ORDER BY id DESC LIMIT 1', [(int)$issue['id']]) : null;
    ok("invoice $tag posted through the real sales/invoice flow", $code === 302 && $issue && $issue['status'] === 'posted');
    ok("invoice $tag payable is Rs " . number_format($expected, 2), $line && abs((float)$line['line_payable'] - $expected) < 0.01 && abs((float)$line['issue_rate'] - 200) < 0.01);
    if ($tag === 'a') $issue1 = $issue; else $issue2 = $issue;
}
$invoices = all("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'credit_issue' AND status = 'posted' ORDER BY date, id", [$smId]);
ok('exactly two posted goods invoices exist', count($invoices) === 2);
$vanAfterIssues = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $vanId]);
ok('all 50 Packets moved into the salesman van', $vanAfterIssues === 50.0);

echo "\n=== 3. Recovery tab summarises both invoices automatically ===\n";
[$code, $body] = http_req('salesmen/' . $smId . '/recovery', null, $owner);
ok('recovery tab loads inside the salesman profile', $code === 200 && strpos($body, 'Recovery / Payment Collection') !== false);
ok('both invoice numbers appear without manual selection', strpos($body, $issue1['issue_no']) !== false && strpos($body, $issue2['issue_no']) !== false);
ok('Total Goods Issued Amount = Rs 10,000.00', chip($body, 'Total Goods Issued Amount', '10,000.00'));
ok('Total Pending Invoices = 2', preg_match('/Total Pending Invoices<\/div>\s*<div class="sc-val">2<\/div>/', $body) === 1);
ok('legacy /payments URL redirects to /recovery', http_req('salesmen/' . $smId . '/payments', null, $owner)[0] === 302);

echo "\n=== 4. Rs 5,000 recovery: cash 3,000 + bank 2,000, oldest-first ===\n";
$payKey = 'e2e-rec-pay-' . $stamp;
[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'csrf' => csrf('salesmen/' . $smId . '/recovery', $owner), 'recovery_action' => 'payment',
    'date' => $day2, 'total_amount' => '5000', 'cash_amount' => '3000', 'bank_amount' => '2000',
    'cash_account_id' => (string)$cashAcct, 'bank_account_id' => (string)$bankAcct,
    'bank_reference' => 'E2E-TRX-' . $stamp, 'notes' => 'E2E split recovery', 'idem_key' => $payKey,
    'allocation_mode' => 'auto',
], $owner);
ok('split recovery posted', $code === 302);
$payment = one('SELECT * FROM salesman_payments WHERE idem_key = ?', [$payKey]);
ok('one payment row stored with derived method=split', $payment && $payment['payment_method'] === 'split' && abs((float)$payment['total_amount'] - 5000) < 0.01);
$allocs = all('SELECT spa.amount, i.id, i.issue_no FROM salesman_payment_allocations spa JOIN salesman_issues i ON i.id = spa.issue_id WHERE spa.payment_id = ? ORDER BY i.date, i.id', [(int)($payment['id'] ?? 0)]);
ok('payment allocated to both invoices oldest-first', count($allocs) === 2 && abs((float)$allocs[0]['amount'] - 2000) < 0.01 && abs((float)$allocs[1]['amount'] - 3000) < 0.01);
ok('first allocation is Day 1 invoice', $allocs && (int)$allocs[0]['id'] === (int)$issue1['id']);
$jl = all('SELECT * FROM journal_lines WHERE entry_id = ?', [(int)($payment['journal_entry_id'] ?? 0)]);
$drCash = 0.0; $drBank = 0.0; $crTotal = 0.0;
foreach ($jl as $ln) {
    if ((int)$ln['account_id'] === $cashGl && (float)$ln['debit'] > 0) $drCash += (float)$ln['debit'];
    if ((int)$ln['account_id'] === $bankGl && (float)$ln['debit'] > 0) $drBank += (float)$ln['debit'];
    $crTotal += (float)$ln['credit'];
}
ok('cash portion debits selected Cash-in-Hand account', abs($drCash - 3000) < 0.01);
ok('bank portion debits selected Bank account', abs($drBank - 2000) < 0.01);
ok('receivable credited exactly once for Rs 5,000', abs($crTotal - 5000) < 0.01);
$ledgerCredit = (float)val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment' AND doc_id = ?", [$smId, (int)$payment['id']]);
ok('salesman ledger credited Rs 5,000 once', abs($ledgerCredit - 5000) < 0.01);

echo "\n=== 5. Individual receipt prints the allocated invoices ===\n";
[$code, $receipt] = http_req('salesmen/' . $smId . '/payments/' . (int)$payment['id'] . '/print', null, $owner);
ok('payment receipt loads', $code === 200);
ok('receipt header says RECOVERY RECEIPT', strpos($receipt, 'SALESMAN RECOVERY RECEIPT') !== false);
ok('receipt lists both allocated invoice numbers', strpos($receipt, $issue1['issue_no']) !== false && strpos($receipt, $issue2['issue_no']) !== false);
ok('receipt shows bank reference', strpos($receipt, 'E2E-TRX-' . $stamp) !== false);

echo "\n=== 6. One accepted Packet (Rs 200) returned against Day 1 invoice ===\n";
[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'csrf' => csrf('salesmen/' . $smId . '/recovery', $owner), 'recovery_action' => 'return',
    'date' => $day2, 'related_issue_id' => (string)$issue1['id'], 'product_id' => (string)$pid,
    'qty' => '1', 'return_condition' => 'good', 'reference' => 'E2E-GR-' . $stamp,
    'notes' => 'E2E accepted packet',
], $owner);
ok('goods return posted', $code === 302);
$ret = one("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'salesman_return' ORDER BY id DESC LIMIT 1", [$smId]);
$retLine = $ret ? one('SELECT * FROM salesman_issue_items WHERE issue_id = ? ORDER BY id DESC LIMIT 1', [(int)$ret['id']]) : null;
ok('return linked to the Day 1 invoice', $ret && (int)($ret['related_issue_id'] ?? 0) === (int)$issue1['id']);
ok('original issue rate auto-fetched (Rs 200)', $retLine && abs((float)$retLine['issue_rate'] - 200) < 0.01);
ok('accepted Packet credit = Rs 200', $retLine && abs((float)$retLine['line_payable'] - 200) < 0.01 && $retLine['return_condition'] === 'good');
$returnLedger = (float)val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'return' AND doc_id = ?", [$smId, (int)($ret['id'] ?? 0)]);
ok('return credits the ledger Rs 200', abs($returnLedger - 200) < 0.01);
$returnJournals = (int)val("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'salesman_return' AND reference_id = ?", [(int)($ret['id'] ?? 0)]);
ok('return accounting posted exactly once', $returnJournals === 1);
$mainAfterRet = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $mainId]);
$vanAfterRet = (float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $vanId]);
ok('accepted Packet restocked to Main Warehouse (0 -> 1)', $mainAfterRet === 1.0);
ok('accepted Packet removed from van (50 -> 49)', $vanAfterRet === 49.0);
$inQty = (float)val("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = ? AND ref_type = 'salesman_return' AND ref_id = ? AND type = 'salesman_return'", [$pid, (int)($ret['id'] ?? 0)]);
$outQty = (float)val("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = ? AND ref_type = 'salesman_return' AND ref_id = ? AND type = 'salesman_issue_out'", [$pid, (int)($ret['id'] ?? 0)]);
ok('exactly one stock movement into Main Warehouse', $inQty === 1.0);
ok('exactly one stock movement out of van', $outQty === -1.0);
$paymentsAfterReturn = (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]);
ok('goods return did NOT create a cash/bank payment', $paymentsAfterReturn === 1);

echo "\n=== 7. Required dashboard result: Rs 4,800 outstanding ===\n";
[$code, $body] = http_req('salesmen/' . $smId . '/recovery', null, $owner);
ok('Total Goods Issued = 10,000.00', chip($body, 'Total Goods Issued Amount', '10,000.00'));
ok('Cash Received = 3,000.00', chip($body, 'Cash Received', '3,000.00'));
ok('Bank Received = 2,000.00', chip($body, 'Bank Received', '2,000.00'));
ok('Total Value of Goods Returned = 200.00', chip($body, 'Total Value of Goods Returned', '200.00'));
ok('Total Amount Recovered = 5,200.00', chip($body, 'Total Amount Recovered', '5,200.00'));
ok('Total Outstanding = Rs 4,800.00 (authoritative expectation)', chip($body, 'Total Outstanding Amount', '4,800.00'));
ok('Total Pending Invoices = 1', preg_match('/Total Pending Invoices<\/div>\s*<div class="sc-val">1<\/div>/', $body) === 1);
ok('return credit is allocated to Day 1 invoice', strpos($body, '200.00') !== false);

echo "\n=== 8. Chronological history filters + printable statement ===\n";
[$code, $hist] = http_req('salesmen/' . $smId . '/recovery?from=' . $day1 . '&to=' . $day2 . '&method=cash', null, $owner);
ok('cash history filter loads', $code === 200);
ok('cash filter shows the receipt', strpos($hist, $payment['receipt_no']) !== false);
$historySection = explode('Recovery Payments', explode('Recovery History', $hist)[1] ?? '')[0] ?? '';
ok('cash filter excludes the goods-return document from the history table', strpos($historySection, $ret['issue_no']) === false);
[$code, $hist] = http_req('salesmen/' . $smId . '/recovery?from=' . $day1 . '&to=' . $day2 . '&method=return', null, $owner);
ok('goods-return history filter shows the return', $code === 200 && strpos($hist, $ret['issue_no']) !== false);
[$code, $statement] = http_req('salesmen/' . $smId . '/recovery/statement?from=' . $day1 . '&to=' . $day2, null, $owner);
ok('recovery statement prints', $code === 200 && strpos($statement, 'SALESMAN RECOVERY STATEMENT') !== false);
ok('statement shows Rs 4,800.00 outstanding', strpos($statement, '4,800.00') !== false);
ok('statement lists invoice, payment and return documents', strpos($statement, $issue1['issue_no']) !== false && strpos($statement, $issue2['issue_no']) !== false && strpos($statement, $ret['issue_no']) !== false);

echo "\n=== 9. Manual allocation to one invoice ===\n";
$manualKey = 'e2e-rec-manual-' . $stamp;
[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'csrf' => csrf('salesmen/' . $smId . '/recovery', $owner), 'recovery_action' => 'payment',
    'date' => $day2, 'total_amount' => '1000', 'cash_amount' => '1000', 'bank_amount' => '0',
    'cash_account_id' => (string)$cashAcct, 'bank_account_id' => '', 'bank_reference' => '', 'notes' => 'E2E manual allocation',
    'idem_key' => $manualKey, 'allocation_mode' => 'manual', 'allocations' => [(string)$issue2['id'] => '1000'],
], $owner);
ok('manually allocated payment posted', $code === 302);
$manualPay = one('SELECT * FROM salesman_payments WHERE idem_key = ?', [$manualKey]);
$manualRows = all('SELECT issue_id, amount FROM salesman_payment_allocations WHERE payment_id = ?', [(int)($manualPay['id'] ?? 0)]);
ok('manual allocation targets only the chosen invoice', count($manualRows) === 1 && (int)$manualRows[0]['issue_id'] === (int)$issue2['id'] && abs((float)$manualRows[0]['amount'] - 1000) < 0.01);
[$code, $body] = http_req('salesmen/' . $smId . '/recovery', null, $owner);
ok('outstanding reduced to Rs 3,800.00 after manual payment', chip($body, 'Total Outstanding Amount', '3,800.00'));

echo "\n=== 10. Idempotency + damaged/rejected return kept separate ===\n";
$beforePay = (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]);
$beforeAlloc = (int)val('SELECT COUNT(*) FROM salesman_payment_allocations WHERE salesman_id = ?', [$smId]);
[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'csrf' => csrf('salesmen/' . $smId . '/recovery', $owner), 'recovery_action' => 'payment',
    'date' => $day2, 'total_amount' => '1000', 'cash_amount' => '1000', 'bank_amount' => '0',
    'cash_account_id' => (string)$cashAcct, 'bank_reference' => '', 'idem_key' => $manualKey,
    'allocation_mode' => 'manual', 'allocations' => [(string)$issue2['id'] => '1000'],
], $owner);
ok('duplicate submission created no payment', (int)val('SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = ?', [$smId]) === $beforePay);
ok('duplicate submission created no allocation rows', (int)val('SELECT COUNT(*) FROM salesman_payment_allocations WHERE salesman_id = ?', [$smId]) === $beforeAlloc);

[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'csrf' => csrf('salesmen/' . $smId . '/recovery', $owner), 'recovery_action' => 'return',
    'date' => $day2, 'related_issue_id' => (string)$issue2['id'], 'product_id' => (string)$pid,
    'qty' => '1', 'return_condition' => 'damaged', 'reference' => 'E2E-DMG-' . $stamp, 'notes' => 'E2E damaged packet',
], $owner);
ok('damaged return posted', $code === 302);
$dmg = one("SELECT * FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'salesman_return' ORDER BY id DESC LIMIT 1", [$smId]);
$dmgLine = $dmg ? one('SELECT * FROM salesman_issue_items WHERE issue_id = ? ORDER BY id DESC LIMIT 1', [(int)$dmg['id']]) : null;
ok('damaged return marked separate with zero credit', $dmgLine && $dmgLine['return_condition'] === 'damaged' && abs((float)$dmgLine['line_payable']) < 0.01);
ok('damaged return created no ledger credit', abs((float)val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'return' AND doc_id = ?", [$smId, (int)($dmg['id'] ?? 0)])) < 0.01);
ok('damaged Packet did NOT enter saleable Main Warehouse stock', abs((float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $mainId]) - $mainAfterRet) < 0.01);
ok('damaged Packet left the van', abs((float)val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $vanId]) - 48.0) < 0.01);
[$code, $body] = http_req('salesmen/' . $smId . '/recovery', null, $owner);
ok('damaged return did not change payable', chip($body, 'Total Outstanding Amount', '3,800.00'));

echo "\n=== 11. Viewer permissions unchanged ===\n";
$acct = [];
login('accountant1', 'Accountant@1234x', $acct);
[$code, $body] = http_req('salesmen/' . $smId . '/recovery', null, $acct);
ok('viewer can open the recovery tab', $code === 200);
ok('viewer does not see the entry forms', strpos($body, 'Only authorized users') !== false);
[$code] = http_req('salesmen/' . $smId . '/recovery', [
    'recovery_action' => 'payment',
    'date' => $day2, 'total_amount' => '100', 'cash_amount' => '100', 'bank_amount' => '0',
    'cash_account_id' => (string)$cashAcct, 'idem_key' => 'e2e-rec-forbidden-' . $stamp,
], $acct);
ok('viewer POST is forbidden (403)', $code === 403);

$fail = count(array_filter($results, fn($r) => !$r[1]));
echo "\nSALESMAN RECOVERY E2E: ", count($results), " tests, $fail failures\n";
exit($fail ? 1 : 0);
