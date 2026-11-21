<?php
/** Bonus approval flow E2E: owner requests a bonus for EMP-0019 (Dev Manager One), admin approves, journal must post. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
$jarAdmin = tempnam(sys_get_temp_dir(), 'ck');
$jarOwner = tempnam(sys_get_temp_dir(), 'ck');

function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$empId = (int)$pdo->query("SELECT id FROM employees WHERE code = 'EMP-0019'")->fetchColumn();
echo "employee id: $empId\n";

/* ---- 1. Owner logs in and opens the bonus tab ---- */
echo "\n=== 1. Owner: request bonus ===\n";
[$c, $body] = req($base . 'auth/login', null, $jarOwner);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jarOwner);
[$c, $body] = req($base . 'employees/' . $empId . '&tab=bonus', null, $jarOwner);
ok($c === 200, 'bonus tab loads for owner');
ok(strpos($body, 'Bonus History') !== false, 'bonus tab shows history panel');
$csrf = csrfFrom($body);
// find a payout account id
$acct = $pdo->query("SELECT id FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name LIMIT 1")->fetchColumn();
$amount = 1500.00;
[$c, $body] = req($base . 'employees/' . $empId . '/bonus/add',
    'csrf=' . urlencode($csrf) . '&employee_id=' . $empId . '&amount=' . $amount . '&reason=' . urlencode('E2E test bonus') . '&date=' . today_() . '&payment_method=cash&account_id=' . (int)$acct, $jarOwner);
echo "bonus/add -> $c (redirect: " . ($c === 302 ? 'yes' : 'no') . ")\n";
ok($c === 302, 'bonus request submitted');
$bonusId = (int)$pdo->query("SELECT id FROM employee_bonuses WHERE employee_id = $empId AND reason = 'E2E test bonus' ORDER BY id DESC LIMIT 1")->fetchColumn();
ok($bonusId > 0, 'employee_bonuses row created');
$status = $bonusId ? $pdo->query("SELECT status FROM employee_bonuses WHERE id = $bonusId")->fetchColumn() : '';
ok($status === 'pending', "bonus status is pending (got: $status)");
$apId = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type = 'employee_bonuses' AND reference_id = $bonusId ORDER BY id DESC LIMIT 1")->fetchColumn();
ok($apId > 0, 'approval request created');
$jr = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'employee_bonus' AND reference_id = $bonusId")->fetchColumn();
ok($jr === 0, 'no journal posted before approval');

/* ---- 2. Admin approves ---- */
echo "\n=== 2. Admin: approve bonus in /admin/approvals ===\n";
[$c, $body] = req($base . 'auth/login', null, $jarAdmin);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=admin1&password=Admin@1234x', $jarAdmin);
[$c, $body] = req($base . 'admin/approvals', null, $jarAdmin);
ok($c === 200, 'approvals page loads');
// grab csrf from approvals page
$csrf = csrfFrom($body);
[$c, $body] = req($base . 'admin/approvals/decide',
    'csrf=' . urlencode($csrf) . '&id=' . $apId . '&note=' . urlencode('E2E approval') . '&decision=approve', $jarAdmin);
echo "decide -> $c\n";
$status = $pdo->query("SELECT status FROM employee_bonuses WHERE id = $bonusId")->fetchColumn();
ok($status === 'posted', "bonus status is posted after approval (got: $status)");
$jr = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'employee_bonus' AND reference_id = $bonusId")->fetchColumn();
ok($jr === 1, "exactly one journal posted (got: $jr)");
$lines = (int)$pdo->query("SELECT COUNT(*) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id WHERE je.reference_type = 'employee_bonus' AND je.reference_id = $bonusId")->fetchColumn();
ok($lines === 2, "journal has 2 lines (Dr expense, Cr cash/bank) (got: $lines)");

/* ---- 3. Owner sees posted bonus in the tab + ledger ---- */
echo "\n=== 3. Verify ledger ===\n";
[$c, $body] = req($base . 'employees/' . $empId . '&tab=bonus', null, $jarOwner);
ok($c === 200 && strpos($body, 'POSTED') !== false, 'bonus tab shows POSTED status');
ok($c === 200 && strpos($body, 'E2E test bonus') !== false, 'bonus row visible in history');

/* ---- 4. Cleanup test data ---- */
echo "\n=== 4. Cleanup ===\n";
$pdo->exec("DELETE FROM journal_lines WHERE entry_id IN (SELECT id FROM journal_entries WHERE reference_type = 'employee_bonus' AND reference_id = $bonusId)");
$pdo->exec("DELETE FROM journal_entries WHERE reference_type = 'employee_bonus' AND reference_id = $bonusId");
$pdo->exec("DELETE FROM approval_requests WHERE reference_type = 'employee_bonuses' AND reference_id = $bonusId");
$pdo->exec("DELETE FROM employee_bonuses WHERE id = $bonusId");
echo "test bonus rows deleted\n";

echo "\nBONUS APPROVAL E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);

function today_(): string { return date('Y-m-d'); }
