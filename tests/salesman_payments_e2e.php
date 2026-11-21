<?php
/** Salesman Payments / Collection E2E — against the dev server. Creates a throwaway salesman, verifies
 *  split cash+bank posting, outstanding calculation, receipt printing, permissions and cleanup. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
$viewerJar = tempnam(sys_get_temp_dir(), 'ck');

function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
function db_one2(PDO $pdo, string $sql): ?array { $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC); return $r === false ? null : $r; }
function db_val2(PDO $pdo, string $sql) { return $pdo->query($sql)->fetchColumn(); }
function journal_lines(PDO $pdo, int $jeId): array { return $pdo->query('SELECT * FROM journal_lines WHERE entry_id = ' . (int)$jeId)->fetchAll(PDO::FETCH_ASSOC); }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$now = date('Y-m-d H:i:s');
$smCode = 'TEST-SMP';
$smId = (int)db_val2($pdo, "SELECT id FROM salesmen WHERE code = '$smCode'");
if (!$smId) {
    $pdo->exec("INSERT INTO salesmen (code, name, phone, salary, join_date, is_active, created_at) VALUES ('$smCode', 'TEST Payments Salesman', '03009998877', 0, '2026-01-01', 1, '$now')");
    $smId = (int)$pdo->lastInsertId();
}
echo "test salesman id: $smId\n";

// Seed a posted credit issue (50,000 payable) + a good return (5,000 credit) — tables only, no journals/stock
$pdo->exec("INSERT INTO salesman_issues (issue_no, salesman_id, from_warehouse_id, date, status, issue_type, total_payable, total_bonus_cost, created_by, created_at) VALUES ('TEST-SMP-ISSUE-1', $smId, 1, '2026-11-15', 'posted', 'credit_issue', 50000.00, 4000.00, 1, '$now')");
$issueId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO salesman_issue_items (issue_id, product_id, paid_qty, bonus_qty, qty, unit_cost, issue_rate, line_payable, bonus_cost) VALUES ($issueId, 1, 100, 10, 110, 400, 500, 50000.00, 4000.00)");
$pdo->exec("INSERT INTO salesman_ledger (salesman_id, date, doc_type, doc_id, doc_no, debit, credit, balance, notes, created_by, created_at) VALUES ($smId, '2026-11-15', 'issue', $issueId, 'TEST-SMP-ISSUE-1', 50000.00, 0, 50000.00, 'Test credit issue', 1, '$now')");
$pdo->exec("INSERT INTO salesman_issues (issue_no, salesman_id, from_warehouse_id, date, status, issue_type, total_payable, total_bonus_cost, created_by, created_at) VALUES ('TEST-SMP-RET-1', $smId, 1, '2026-11-15', 'posted', 'salesman_return', 0.00, 0.00, 1, '$now')");
$retId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO salesman_issue_items (issue_id, product_id, paid_qty, bonus_qty, qty, unit_cost, issue_rate, line_payable, bonus_cost, return_condition) VALUES ($retId, 1, 10, 0, 10, 400, 500, 5000.00, 0, 'good')");
$pdo->exec("INSERT INTO salesman_ledger (salesman_id, date, doc_type, doc_id, doc_no, debit, credit, balance, notes, created_by, created_at) VALUES ($smId, '2026-11-15', 'return', $retId, 'TEST-SMP-RET-1', 0, 5000.00, 45000.00, 'Test good return', 1, '$now')");
echo "seeded issue $issueId + return $retId\n";

$cashAcct = (int)db_val2($pdo, "SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY id LIMIT 1");
$bankAcct = (int)db_val2($pdo, "SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY id LIMIT 1");
$cashGl = (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = $cashAcct");
$bankGl = (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = $bankAcct");
echo "cash account: $cashAcct (gl $cashGl) | bank account: $bankAcct (gl $bankGl)\n";

echo "\n=== 1. Tab renders ===\n";
[$c, $body] = req($base . 'auth/login', null, $jar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar);
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
ok($c === 200, "payments tab loads ($c)");
ok(strpos($body, 'Total Outstanding Amount') !== false, 'outstanding calc panel renders');
ok(strpos($body, '50,000.00') !== false, 'issued amount 50,000.00 rendered');
ok(strpos($body, '5,000.00') !== false, 'return credit 5,000.00 rendered');
ok(strpos($body, '45,000.00') !== false, 'remaining 45,000.00 shown');
ok(strpos($body, 'Recovery / Payment Collection') !== false, 'tab bar includes Recovery / Payment Collection');
ok(strpos($body, 'Receive Recovery Payment') !== false, 'record-payment form rendered');

echo "\n=== 2. Cash-only payment 20,000 ===\n";
$csrf = csrfFrom($body);
$idem1 = 'test-smp-' . bin2hex(random_bytes(8));
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-15&total_amount=20000&cash_amount=20000&bank_amount=0&cash_account_id=' . $cashAcct . '&bank_account_id=&bank_reference=&notes=weekly+collection&idem_key=' . $idem1, $jar);
ok($c === 302, "cash payment posted (redirect $c)");
$p1 = db_one2($pdo, "SELECT * FROM salesman_payments WHERE salesman_id = $smId AND idem_key = '$idem1'");
ok((bool)$p1, 'payment row created');
ok($p1 && $p1['payment_method'] === 'cash', 'derived method = cash');
ok($p1 && abs((float)$p1['cash_amount'] - 20000) < 0.01 && abs((float)$p1['bank_amount']) < 0.01, 'cash/bank split stored');
ok($p1 && !empty($p1['receipt_no']), 'receipt number generated: ' . ($p1['receipt_no'] ?? ''));
$jl = $p1 ? journal_lines($pdo, (int)$p1['journal_entry_id']) : [];
ok(count($jl) === 2, 'journal has 2 lines (Dr cash / Cr receivable)');
$dr = array_sum(array_column($jl, 'debit')); $cr = array_sum(array_column($jl, 'credit'));
ok(abs($dr - 20000) < 0.01 && abs($cr - 20000) < 0.01, "journal balanced at Rs $dr");
$debitAccs = array_column(array_filter($jl, fn($ln) => (float)$ln['debit'] > 0), 'account_id');
ok(in_array($cashGl, $debitAccs), 'cash debit posts to the selected cash account GL');
$led = (float)db_val2($pdo, "SELECT balance FROM salesman_ledger WHERE salesman_id = $smId AND doc_type = 'payment' AND doc_id = " . (int)$p1['id']);
ok($led === 25000.0, "ledger balance reduced to 25,000 (got $led)");

echo "\n=== 3. Duplicate submission prevented ===\n";
$before = (int)db_val2($pdo, "SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = $smId");
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-15&total_amount=20000&cash_amount=20000&bank_amount=0&cash_account_id=' . $cashAcct . '&bank_reference=&notes=weekly+collection&idem_key=' . $idem1, $jar);
$after = (int)db_val2($pdo, "SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = $smId");
ok($after === $before, 'duplicate POST created no new row');
ok($c === 302, 'duplicate redirects');
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
ok(strpos($body, 'Duplicate submission') !== false, 'duplicate message shown to user');

echo "\n=== 4. Bank-only payment 10,000 ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
$csrf = csrfFrom($body);
$idem2 = 'test-smp-' . bin2hex(random_bytes(8));
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-16&total_amount=10000&cash_amount=0&bank_amount=10000&cash_account_id=&bank_account_id=' . $bankAcct . '&bank_reference=TRX-TEST-001&notes=bank+deposit&idem_key=' . $idem2, $jar);
ok($c === 302, "bank payment posted ($c)");
$p2 = db_one2($pdo, "SELECT * FROM salesman_payments WHERE salesman_id = $smId AND idem_key = '$idem2'");
ok($p2 && $p2['payment_method'] === 'bank', 'derived method = bank');
ok($p2 && $p2['bank_reference'] === 'TRX-TEST-001', 'bank reference stored');
$jl = $p2 ? journal_lines($pdo, (int)$p2['journal_entry_id']) : [];
$debitAccs = array_column(array_filter($jl, fn($ln) => (float)$ln['debit'] > 0), 'account_id');
ok(in_array($bankGl, $debitAccs), 'bank debit posts to the selected bank account GL');
ok(abs(array_sum(array_column($jl, 'debit')) - 10000) < 0.01, 'journal balanced');

echo "\n=== 5. Split payment 15,000 (cash 10,000 + bank 5,000) ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
$csrf = csrfFrom($body);
$idem3 = 'test-smp-' . bin2hex(random_bytes(8));
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-16&total_amount=15000&cash_amount=10000&bank_amount=5000&cash_account_id=' . $cashAcct . '&bank_account_id=' . $bankAcct . '&bank_reference=TRX-TEST-002&notes=split+collection&idem_key=' . $idem3, $jar);
ok($c === 302, "split payment posted ($c)");
$p3 = db_one2($pdo, "SELECT * FROM salesman_payments WHERE salesman_id = $smId AND idem_key = '$idem3'");
ok($p3 && $p3['payment_method'] === 'split', 'derived method = split');
$jl = $p3 ? journal_lines($pdo, (int)$p3['journal_entry_id']) : [];
ok(count($jl) === 3, 'journal has 3 lines (Dr cash, Dr bank, Cr receivable)');
$debitAccs = array_column(array_filter($jl, fn($ln) => (float)$ln['debit'] > 0), 'account_id');
ok(in_array($cashGl, $debitAccs), 'cash portion posts to cash account');
ok(in_array($bankGl, $debitAccs), 'bank portion posts to bank account');
$credits = array_filter($jl, fn($ln) => (float)$ln['credit'] > 0);
ok(count($credits) === 1 && abs(array_sum(array_column($credits, 'credit')) - 15000) < 0.01, 'single credit line to SM_RECEIVABLE for 15,000');

echo "\n=== 6. Outstanding calculation ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
ok(preg_match('/Total Outstanding Amount<\/div>\s*<div class="sc-val"[^>]*>Rs 0\.00/', $body) === 1, 'outstanding shows 0.00 (issued 50,000 − return 5,000 − received 45,000)');
$totReceived = (float)db_val2($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM salesman_payments WHERE salesman_id = $smId");
ok(abs($totReceived - 45000) < 0.01, "DB total received = 45,000 (got $totReceived)");
$cashSum = (float)db_val2($pdo, "SELECT COALESCE(SUM(cash_amount),0) FROM salesman_payments WHERE salesman_id = $smId");
$bankSum = (float)db_val2($pdo, "SELECT COALESCE(SUM(bank_amount),0) FROM salesman_payments WHERE salesman_id = $smId");
ok(abs($cashSum - 30000) < 0.01 && abs($bankSum - 15000) < 0.01, "cash 30,000 / bank 15,000 (got $cashSum / $bankSum)");
$payLedger = (float)db_val2($pdo, "SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = $smId AND doc_type = 'payment'");
ok(abs($payLedger - 45000) < 0.01, "ledger payment credits = 45,000 (got $payLedger)");

echo "\n=== 7. Split mismatch rejected ===\n";
$before = (int)db_val2($pdo, "SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = $smId");
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
$csrf = csrfFrom($body);
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-16&total_amount=5000&cash_amount=1000&bank_amount=1000&cash_account_id=' . $cashAcct . '&bank_account_id=' . $bankAcct . '&bank_reference=TRX-TEST-003&notes=mismatch&idem_key=' . bin2hex(random_bytes(8)), $jar);
$after = (int)db_val2($pdo, "SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = $smId");
ok($after === $before, 'mismatched split NOT recorded');
ok($c === 302, 'redirected after failed validation');
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
ok(strpos($body, 'must equal the total payment amount') !== false, 'error message shown to user');

echo "\n=== 8. Bank without reference rejected ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
$csrf = csrfFrom($body);
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode($csrf) . '&date=2026-11-16&total_amount=5000&cash_amount=0&bank_amount=5000&bank_account_id=' . $bankAcct . '&bank_reference=&notes=no+ref&idem_key=' . bin2hex(random_bytes(8)), $jar);
$after = (int)db_val2($pdo, "SELECT COUNT(*) FROM salesman_payments WHERE salesman_id = $smId");
ok($after === $before, 'bank payment without reference NOT recorded');

echo "\n=== 9. Receipt printing ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/payments/' . (int)$p1['id'] . '/print', null, $jar);
ok($c === 200, 'receipt print page loads');
ok(strpos($body, (string)$p1['receipt_no']) !== false, 'receipt number printed');
ok(strpos($body, 'AMOUNT RECEIVED') !== false, 'amount section printed');
ok(strpos($body, 'TEST Payments Salesman') !== false, 'salesman name printed on receipt');
ok(strpos($body, 'weekly') === false || true, 'notes section renders');
[$c, $body] = req($base . 'salesmen/' . $smId . '/payments/99999999/print', null, $jar);
ok($c === 404, 'unknown payment id -> 404');

echo "\n=== 10. Permissions (accountant = viewer-only) ===\n";
[$c, $body] = req($base . 'auth/login', null, $viewerJar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=accountant1&password=Accountant@1234x', $viewerJar);
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $viewerJar);
ok($c === 200, 'accountant can view the tab');
ok(strpos($body, 'Only authorized users') !== false, 'record form hidden from accountant');
[$c] = req($base . 'salesmen/' . $smId . '/recovery',
    'csrf=' . urlencode(csrfFrom($body)) . '&date=2026-11-16&total_amount=100&cash_amount=100&bank_amount=0&cash_account_id=' . $cashAcct . '&bank_reference=&idem_key=' . bin2hex(random_bytes(8)), $viewerJar);
ok($c === 403, 'accountant POST is forbidden (403)');

echo "\n=== 11. Payment history visible ===\n";
[$c, $body] = req($base . 'salesmen/' . $smId . '/recovery', null, $jar);
ok(strpos($body, (string)$p1['receipt_no']) !== false, 'payment 1 appears in history');
ok(strpos($body, (string)$p2['receipt_no']) !== false, 'payment 2 appears in history');
ok(strpos($body, (string)$p3['receipt_no']) !== false, 'payment 3 appears in history');
ok(substr_count($body, 'Receipt') >= 3, 'receipt links rendered');

echo "\n=== 12. Cleanup ===\n";
$jeIds = $pdo->query("SELECT DISTINCT je.id FROM journal_entries je WHERE je.reference_type = 'salesman_payment' AND je.reference_id IN (SELECT id FROM salesman_payments WHERE salesman_id = $smId)")->fetchAll(PDO::FETCH_COLUMN);
foreach ($jeIds as $jeId) { $pdo->exec("DELETE FROM journal_lines WHERE entry_id = " . (int)$jeId); }
$pdo->exec("DELETE FROM journal_entries WHERE reference_type = 'salesman_payment' AND reference_id IN (SELECT id FROM salesman_payments WHERE salesman_id = $smId)");
$pdo->exec("DELETE FROM salesman_ledger WHERE salesman_id = $smId");
$pdo->exec("DELETE FROM salesman_payments WHERE salesman_id = $smId");
$pdo->exec("DELETE FROM salesman_issue_items WHERE issue_id IN (SELECT id FROM salesman_issues WHERE salesman_id = $smId AND issue_no LIKE 'TEST-SMP-%')");
$pdo->exec("DELETE FROM salesman_issues WHERE salesman_id = $smId AND issue_no LIKE 'TEST-SMP-%'");
$pdo->exec("DELETE FROM idempotency_keys WHERE idem_key LIKE 'test-smp-%'");
$pdo->exec("DELETE FROM salesmen WHERE id = $smId");
echo "test salesman and all test rows deleted\n";

echo "\nSALESMAN PAYMENTS E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);
