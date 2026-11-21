<?php
/** E2E: advance limit enforcement for non-owner/admin requesters. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', substr($out, 0, $pos), $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => substr($out, $pos + 2)];
}
function login($user, $pass): string {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $login = http('GET', '/index.php?r=auth/login', $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
    $lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => $user, 'password' => $pass]);
    if ($lr['status'] !== 302) { echo "LOGIN FAILED for $user\n"; exit(1); }
    return $jar;
}
function csrf(string $jar, string $url): string {
    $page = http('GET', $url, $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m);
    return $m[1];
}
// 1. owner sets advance_max_amount = 25000
$owner = login('owner', 'owner1234');
$save = http('POST', '/index.php?r=settings/company', $owner, ['csrf' => csrf($owner, '/index.php?r=settings/company'), 'advance_max_amount' => '25000']);
echo "owner set limit: {$save['status']}\n";
// 2. manager1 opens the standalone request page (no employees.edit needed)
$mgr = login('manager1', 'Manager@1234x');
$page = http('GET', '/index.php?r=employees/1/advance', $mgr);
echo "manager advance page: {$page['status']}\n";
preg_match('/name="account_id"[^>]*>.*?<option value="(\d+)"[^>]*>([^<]*Main Bank[^<]*)</is', $page['body'], $mm);
$bankId = $mm[1] ?? null;
// 3. manager1 requests 60,000 → must be rejected with the limit message
$big = http('POST', '/index.php?r=employees/1/advance', $mgr, ['csrf' => csrf($mgr, '/index.php?r=employees/1/advance'), 'action' => 'advance', 'date' => date('Y-m-d'), 'amount' => '60000', 'account_id' => $bankId, 'monthly_deduction' => '', 'reason' => 'limit test']);
echo "manager big request (60k > 25k cap): {$big['status']} — flash: ";
preg_match('/class="alert alert-error"[^>]*>([^<]+)/', $big['body'], $fm);
echo ($fm[1] ?? 'none'), "\n";
// 4. manager1 requests 10,000 → must be accepted as pending
$small = http('POST', '/index.php?r=employees/1/advance', $mgr, ['csrf' => csrf($mgr, '/index.php?r=employees/1/advance'), 'action' => 'advance', 'date' => date('Y-m-d'), 'amount' => '10000', 'account_id' => $bankId, 'monthly_deduction' => '', 'reason' => 'limit test ok']);
echo "manager small request (10k): {$small['status']}\n";
$pdo = new PDO('sqlite:storage/dev.sqlite');
$a = $pdo->query("SELECT id, amount, status, requested_by FROM employee_advances ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "DB latest advance: id={$a['id']} amount={$a['amount']} status={$a['status']} requested_by={$a['requested_by']}\n";
// 5. owner resets the cap to 0
$reset = http('POST', '/index.php?r=settings/company', $owner, ['csrf' => csrf($owner, '/index.php?r=settings/company'), 'advance_max_amount' => '0']);
echo "owner reset limit: {$reset['status']}\n";
unlink($owner); unlink($mgr);
