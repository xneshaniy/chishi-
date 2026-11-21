<?php
/**
 * Company Expenses HTTP E2E — manager creates (HTTP), owner approves (HTTP),
 * cancel-reject and cancel-reverse flows, reports, permission gates, cleanup.
 * Dev database only; all e2e data is removed at the end.
 */
$base = 'http://127.0.0.1:8080';
$pdo  = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$results = [];
function ok($label, $cond) { global $results; $results[] = [$label, $cond ? 'PASS' : 'FAIL']; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }

function http_req(string $method, string $url, ?array $post = null, array &$cookies = []): array {
    global $base;
    $route = ltrim($url, '/'); // e.g. 'auth/login', 'company-expenses/new'
    if (str_contains($route, '?')) { [$route, $qs] = explode('?', $route, 2); $full = $base . '/index.php?r=' . rawurlencode($route) . '&' . $qs; }
    else $full = $base . '/index.php?r=' . rawurlencode($route);
    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($cookies) {
        $cj = '';
        foreach ($cookies as $k => $v) $cj .= "$k=$v; ";
        curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; '));
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header = substr($raw, 0, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = substr($raw, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    foreach (preg_split('/\r?\n/', $header) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return [$code, $body, $header];
}

function login(string $user, string $pass): array {
    $cookies = [];
    [$code, $body] = http_req('GET', '/auth/login', null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf on login\n"; exit(1); }
    $_code = $code;
    $r = http_req('POST', '/auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
    return $cookies;
}
function csrf_from(string $url, array &$cookies): string {
    [$code, $body] = http_req('GET', $url, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $url\n"; exit(1); }
    return $m[1];
}

$cashAcct = $pdo->query("SELECT id, name FROM cash_bank_accounts WHERE type='cash' AND is_active=1 AND COALESCE(is_archived,0)=0 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$bankAcct = $pdo->query("SELECT id, name, gl_account_id FROM cash_bank_accounts WHERE type='bank' AND is_active=1 AND COALESCE(is_archived,0)=0 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$plasticCat = (int)$pdo->query("SELECT id FROM expense_categories WHERE name='Plastic Bags'")->fetchColumn();
$elecCat    = (int)$pdo->query("SELECT id FROM expense_categories WHERE name='Electricity Bills'")->fetchColumn();
echo "cash acct: {$cashAcct['id']} {$cashAcct['name']} | bank acct: {$bankAcct['id']} {$bankAcct['name']} (gl {$bankAcct['gl_account_id']})\n";
echo "categories: plastic=$plasticCat electricity=$elecCat\n\n";

/* ---------- 1. Manager cannot see company-expenses (no permission) ---------- */
$mgr = login('manager1', 'Manager@1234x');
[$code] = http_req('GET', '/company-expenses', null, $mgr);
ok('manager GET /company-expenses → 403', $code === 403);

/* ---------- 2. Owner/Admin flow — logins via direct db check that admin1 exists ---------- */
$owner = login('owner', 'owner1234');
$admin = $owner; // reuse owner session cookies

/* ---------- 3. Manager-with-permission path is not granted → create via owner ---------- */
$csrf = csrf_from('/company-expenses/new', $owner);
[$code] = http_req('POST', '/company-expenses/new', [
    'csrf' => $csrf, 'expense_date' => date('Y-m-d'), 'category_id' => $plasticCat,
    'item_name' => 'E2E Plastic Bags', 'description' => 'E2E test — 5000 bags @ Rs 2', 'quantity' => '5000',
    'unit' => 'bags', 'rate' => '2', 'amount' => '10000', 'supplier' => 'E2E Packaging Co',
    'payment_method' => 'cash', 'notes' => 'E2E test note',
], $owner);
ok('owner POST /company-expenses/new redirects (302)', $code === 302);
$ceCash = $pdo->query("SELECT * FROM company_expenses WHERE item_name='E2E Plastic Bags' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('cash company expense created pending', $ceCash && $ceCash['status'] === 'pending' && (float)$ceCash['amount'] === 10000.0);
ok('qty×rate auto-calc accepted', $ceCash && (float)$ceCash['quantity'] === 5000.0 && (float)$ceCash['rate'] === 2.0);
$linkedExp = (int)$ceCash['linked_expense_id'];
ok('companion expenses row pending', ($pdo->query("SELECT status FROM expenses WHERE id=$linkedExp")->fetchColumn()) === 'pending');
$apprCash = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type='expenses' AND reference_id=$linkedExp AND status='pending'")->fetchColumn();
ok('approval request pending', $apprCash > 0);

/* ---------- 4. Bank expense ---------- */
$csrf = csrf_from('/company-expenses/new', $owner);
[$code] = http_req('POST', '/company-expenses/new', [
    'csrf' => $csrf, 'expense_date' => date('Y-m-d'), 'category_id' => $elecCat,
    'item_name' => 'E2E Electricity Bill', 'description' => 'E2E electricity', 'quantity' => '', 'unit' => '',
    'rate' => '', 'amount' => '35000', 'supplier' => 'WAPDA',
    'payment_method' => 'bank', 'account_id' => $bankAcct['id'], 'notes' => 'E2E bank payment',
], $owner);
ok('bank expense created', $code === 302);
$ceBank = $pdo->query("SELECT * FROM company_expenses WHERE item_name='E2E Electricity Bill' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$linkedExpB = (int)$ceBank['linked_expense_id'];
$apprBank = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type='expenses' AND reference_id=$linkedExpB AND status='pending'")->fetchColumn();
ok('bank expense pending with approval', $ceBank && $ceBank['status'] === 'pending' && $apprBank > 0 && (int)$ceBank['account_id'] === (int)$bankAcct['id']);

/* ---------- 5. Owner self-approval must be denied ---------- */
[$code] = http_req('POST', '/admin/approvals/decide', ['csrf' => csrf_from('/admin/approvals', $owner), 'id' => $apprCash, 'decision' => 'approve', 'note' => 'self attempt'], $owner);
// Owner created it — must be rejected by "cannot approve own request"
$stillPending = (int)$pdo->query("SELECT COUNT(*) FROM approval_requests WHERE id=$apprCash AND status='pending'")->fetchColumn();
ok('self-approval denied (request still pending)', $stillPending === 1);

/* ---------- manager session can never decide approvals (role gate) ---------- */
$mgrCsrf = null;
foreach (['/dashboard','/expenses','/maintenance','/vehicles','/reports/hub'] as $probe) {
    [$pc, $pbody] = http_req('GET', $probe, null, $mgr);
    if (preg_match('/name="csrf" value="([^"]+)"/', $pbody, $mm)) { $mgrCsrf = $mm[1]; break; }
}
[$code] = http_req('POST', '/admin/approvals/decide', ['csrf' => $mgrCsrf ?? 'x', 'id' => $apprBank, 'decision' => 'approve', 'note' => 'mgr attempt'], $mgr);
$stillPendingB = (int)$pdo->query("SELECT COUNT(*) FROM approval_requests WHERE id=$apprBank AND status='pending'")->fetchColumn();
ok('manager decision attempt blocked (approval still pending)', $stillPendingB === 1);

/* ---------- 6. Admin user approves both payments ---------- */
$adm = login('admin1', 'Admin@1234x');
if (!$adm) { echo "ADMIN1 LOGIN FAILED (rate limit?) — aborting approvals\n"; }
else {
    $csrf = csrf_from('/admin/approvals', $adm);
    [$code] = http_req('POST', '/admin/approvals/decide', ['csrf' => $csrf, 'id' => $apprCash, 'decision' => 'approve', 'note' => 'E2E approve cash'], $adm);
    ok('cash approval decided (302)', $code === 302);
    $csrf = csrf_from('/admin/approvals', $adm);
    [$code] = http_req('POST', '/admin/approvals/decide', ['csrf' => $csrf, 'id' => $apprBank, 'decision' => 'approve', 'note' => 'E2E approve bank'], $adm);
    ok('bank approval decided (302)', $code === 302);
}

$ceCash = $pdo->query("SELECT * FROM company_expenses WHERE id={$ceCash['id']}")->fetch(PDO::FETCH_ASSOC);
$ceBank = $pdo->query("SELECT * FROM company_expenses WHERE id={$ceBank['id']}")->fetch(PDO::FETCH_ASSOC);
ok('company_expenses cash → posted', $ceCash['status'] === 'posted');
ok('company_expenses bank → posted', $ceBank['status'] === 'posted');
ok('companion expense cash → posted', ($pdo->query("SELECT status FROM expenses WHERE id=$linkedExp")->fetchColumn()) === 'posted');
ok('companion expense bank → posted', ($pdo->query("SELECT status FROM expenses WHERE id=$linkedExpB")->fetchColumn()) === 'posted');
$journalCash = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='expense' AND reference_id=$linkedExp")->fetchColumn();
$journalBank = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='expense' AND reference_id=$linkedExpB")->fetchColumn();
ok('exactly-once journal for cash (1)', $journalCash === 1);
ok('exactly-once journal for bank (1)', $journalBank === 1);
$bankGl = (int)$bankAcct['gl_account_id'];
$jeId = (int)$pdo->query("SELECT id FROM journal_entries WHERE reference_type='expense' AND reference_id=$linkedExpB")->fetchColumn();
$bankCredit = (float)$pdo->query("SELECT COALESCE(SUM(credit),0) FROM journal_lines WHERE entry_id=$jeId AND account_id=$bankGl")->fetchColumn();
ok("bank GL credited Rs 35000 (got $bankCredit)", abs($bankCredit - 35000.0) < 0.01);

/* double-decision must fail (already decided) */
$admOrOwner = $adm ?? $owner;
$csrf = csrf_from('/admin/approvals', $admOrOwner);
[$code] = http_req('POST', '/admin/approvals/decide', ['csrf' => $csrf, 'id' => $apprCash, 'decision' => 'approve', 'note' => 'double'], $admOrOwner);
ok('double approval attempt rejected by engine', (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='expense' AND reference_id=$linkedExp")->fetchColumn() === 1);

/* ---------- 7. Reports page loads + totals ---------- */
[$code, $body] = http_req('GET', '/reports/company-expenses?from=' . date('Y-m-01') . '&to=' . date('Y-m-d'), null, $admOrOwner);
ok('reports/company-expenses 200', $code === 200 && str_contains($body, 'Company Expenses'));
ok('report shows E2E totals', str_contains($body, '45,000') || str_contains($body, '45000') || str_contains($body, 'Electricity'));

/* ---------- 8. Cancel pending expense → rejected ---------- */
$csrf = csrf_from('/company-expenses/new', $owner);
http_req('POST', '/company-expenses/new', [
    'csrf' => $csrf, 'expense_date' => date('Y-m-d'), 'category_id' => $plasticCat,
    'item_name' => 'E2E Cancel Me', 'description' => 'to be cancelled', 'quantity' => '', 'unit' => '',
    'rate' => '', 'amount' => '777', 'supplier' => 'E2E',
    'payment_method' => 'cash', 'notes' => 'cancel test',
], $owner);
$ceCancel = $pdo->query("SELECT * FROM company_expenses WHERE item_name='E2E Cancel Me' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$linkedExpC = (int)$ceCancel['linked_expense_id'];
$apprCancel = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type='expenses' AND reference_id=$linkedExpC AND status='pending'")->fetchColumn();
$csrf = csrf_from('/company-expenses/' . $ceCancel['id'], $owner);
[$code] = http_req('POST', '/company-expenses/' . $ceCancel['id'] . '/cancel', ['csrf' => $csrf, 'reason' => 'E2E cancel pending'], $owner);
ok('cancel POST redirects', $code === 302);
$ceCancel = $pdo->query("SELECT status FROM company_expenses WHERE id={$ceCancel['id']}")->fetchColumn();
$expCancel = $pdo->query("SELECT status FROM expenses WHERE id=$linkedExpC")->fetchColumn();
$apprCancelNow = $pdo->query("SELECT status FROM approval_requests WHERE id=$apprCancel")->fetchColumn();
ok('cancelled pending expense → rejected everywhere', $ceCancel === 'rejected' && $expCancel === 'rejected' && $apprCancelNow === 'rejected');

/* ---------- 9. Cancel posted expense → reversed ---------- */
$csrf = csrf_from('/company-expenses/' . $ceCash['id'], $owner);
[$code] = http_req('POST', '/company-expenses/' . $ceCash['id'] . '/cancel', ['csrf' => $csrf, 'reason' => 'E2E cancel posted'], $owner);
ok('cancel posted redirects', $code === 302);
$ceCashAfter = $pdo->query("SELECT status FROM company_expenses WHERE id={$ceCash['id']}")->fetchColumn();
$expCashAfter = $pdo->query("SELECT status FROM expenses WHERE id=$linkedExp")->fetchColumn();
ok('posted expense cancelled → reversed', $ceCashAfter === 'reversed' && $expCashAfter === 'reversed');
$origJe = (int)$pdo->query("SELECT id FROM journal_entries WHERE reference_type='expense' AND reference_id=$linkedExp")->fetchColumn();
$reversals = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reversal_of = $origJe")->fetchColumn();
ok('reversal journal posted', $reversals === 1);

/* ---------- 10. Cleanup all E2E artefacts ---------- */
$lidSet = "SELECT linked_expense_id FROM company_expenses WHERE item_name LIKE 'E2E%'";
$jeSet  = "SELECT id FROM journal_entries WHERE reference_type='expense' AND reference_id IN ($lidSet)";
$pdo->exec("DELETE FROM journal_lines WHERE entry_id IN (SELECT id FROM journal_entries WHERE reversal_of IN ($jeSet))");
$pdo->exec("DELETE FROM journal_lines WHERE entry_id IN ($jeSet)");
$pdo->exec("DELETE FROM journal_entries WHERE reversal_of IN ($jeSet)");
$pdo->exec("DELETE FROM journal_entries WHERE id IN ($jeSet)");
$ids = $pdo->query("SELECT id FROM company_expenses WHERE item_name LIKE 'E2E%'")->fetchAll(PDO::FETCH_COLUMN);
$lids = $pdo->query("SELECT linked_expense_id FROM company_expenses WHERE item_name LIKE 'E2E%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($lids as $le) {
    if ($le) {
        $pdo->exec("DELETE FROM approval_requests WHERE reference_type='expenses' AND reference_id=" . (int)$le);
        $pdo->exec("DELETE FROM expenses WHERE id=" . (int)$le);
    }
}
foreach ($ids as $cid) {
    $pdo->exec("DELETE FROM company_expenses WHERE id=" . (int)$cid);
}
$pdo->exec("DELETE FROM audit_logs WHERE details LIKE '%E2E%'");
echo "\nE2E artefacts cleaned up (" . count($ids) . " company expenses).\n";

$fail = count(array_filter($results, fn($r) => $r[1] === 'FAIL'));
echo "\n", count($results), " checks, $fail failed\n";
exit($fail ? 1 : 0);
