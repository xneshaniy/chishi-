<?php
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    return ['body' => substr($out, $pos + 2)];
}
function login($user, $pass): string {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $login = http('GET', '/index.php?r=auth/login', $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
    $lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => $user, 'password' => $pass]);
    return $jar;
}
$pdo = new PDO('sqlite:storage/dev.sqlite');
$count = (int)$pdo->query("SELECT COUNT(*) FROM employee_advances WHERE amount = 60000 AND notes = 'exceed check'")->fetchColumn();
echo "stray 60k advances: $count\n";
$mgr = login('manager1', 'Manager@1234x');
$page = http('GET', '/index.php?r=employees/1/advance', $mgr);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m);
$res = http('POST', '/index.php?r=employees/1/advances/3/cancel', $mgr, ['csrf' => $m[1]]);
echo "manager1 cancel advance #3: HTTP got response\n";
// also mark the associated approval request cancelled (the route does it)
echo "advance #3: ", $pdo->query("SELECT status FROM employee_advances WHERE id = 3")->fetchColumn(), "\n";
echo "its approval: ", $pdo->query("SELECT status FROM approval_requests WHERE reference_type='employee_advances' AND reference_id = 3")->fetchColumn(), "\n";
unlink($mgr);
