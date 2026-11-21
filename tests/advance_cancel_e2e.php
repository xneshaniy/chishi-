<?php
/** E2E via HTTP: owner requests advance → requester cancels it. Dev DB only. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
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
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
assert($lr['status'] === 302);

// 1. request advance
$edit = http('GET', '/index.php?r=employees/1/edit', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $edit['body'], $m);
$csrf1 = $m[1];
preg_match('/name="account_id"[^>]*>.*?<option value="(\d+)"[^>]*>([^<]*Main Bank[^<]*)</is', $edit['body'], $mm);
$bankId = $mm[1] ?? null;
echo "bank account id: $bankId\n";
$adv = http('POST', '/index.php?r=employees/1/edit', $jar, [
    'csrf' => $csrf1, 'action' => 'advance', 'date' => date('Y-m-d'),
    'amount' => '5000', 'account_id' => $bankId, 'monthly_deduction' => '', 'reason' => 'e2e cancel check',
]);
echo "advance request status: ", $adv['status'], "\n";

// 2. read DB for the new advance
$pdo = new PDO('sqlite:storage/dev.sqlite');
$a = $pdo->query("SELECT * FROM employee_advances ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "advance id={$a['id']} amount={$a['amount']} status={$a['status']}\n";
$req = $pdo->query("SELECT * FROM approval_requests WHERE reference_type = 'employee_advances' AND reference_id = {$a['id']}")->fetch(PDO::FETCH_ASSOC);
echo "approval id={$req['id']} status={$req['status']} amount={$req['amount']}\n";

// 3. cancel it via the controller route
$edit2 = http('GET', '/index.php?r=employees/1/edit', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $edit2['body'], $m);
$cancelUrl = "/index.php?r=employees/1/advances/{$a['id']}/cancel";
$res = http('POST', $cancelUrl, $jar, ['csrf' => $m[1]]);
echo "cancel status: ", $res['status'], "\n";
$a2 = $pdo->query("SELECT status FROM employee_advances WHERE id = {$a['id']}")->fetchColumn();
$req2 = $pdo->query("SELECT status, decision_note FROM approval_requests WHERE reference_type = 'employee_advances' AND reference_id = {$a['id']}")->fetch(PDO::FETCH_ASSOC);
echo "after cancel: advance.status=$a2 | approval.status={$req2['status']} | note={$req2['decision_note']}\n";
unlink($jar);
