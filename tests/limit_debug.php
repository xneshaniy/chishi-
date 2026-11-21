<?php
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
    return $jar;
}
$owner = login('owner', 'owner1234');
// check owner got in
echo "owner dashboard: ", http('GET', '/index.php?r=dashboard', $owner)['status'], "\n";
// set limit 25000
$pageO = http('GET', '/index.php?r=settings/company', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $pageO['body'], $m);
$set = http('POST', '/index.php?r=settings/company', $owner, ['csrf' => $m[1], 'advance_max_amount' => '25000']);
echo "limit set: {$set['status']}\n";
$mgr = login('manager1', 'Manager@1234x');
// find main bank id via manager page
$p = http('GET', '/index.php?r=employees/1/advance', $mgr);
preg_match('/<option value="(\d+)"[^>]*>[^<]*Main Bank/i', $p['body'], $mm);
$bankId = $mm[1] ?? '2';
preg_match('/name="csrf" value="([a-f0-9]+)"/', $p['body'], $m);
$post = http('POST', '/index.php?r=employees/1/advance', $mgr, ['csrf' => $m[1], 'action' => 'advance', 'date' => date('Y-m-d'), 'amount' => '60000', 'account_id' => $bankId, 'monthly_deduction' => '', 'reason' => 'exceed check']);
echo "big POST: {$post['status']}\n";
$after = http('GET', '/index.php?r=employees/1/advance', $mgr);
echo "flash error present: ", (strpos($after['body'], 'exceeds the maximum allowed') !== false ? 'YES' : 'NO'), "\n";
preg_match('/<div class="(alert[^"]*)"[^>]*>([^<]+)</', $after['body'], $fm);
if ($fm) echo "flash: [{$fm[1]}] {$fm[2]}\n";
// reset limit
$pageO2 = http('GET', '/index.php?r=settings/company', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $pageO2['body'], $m);
http('POST', '/index.php?r=settings/company', $owner, ['csrf' => $m[1], 'advance_max_amount' => '0']);
echo "limit reset\n";
unlink($owner); unlink($mgr);
