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
    if ($lr['status'] !== 302) { echo "LOGIN FAILED\n"; exit(1); }
    return $jar;
}
$pdo = new PDO('sqlite:storage/dev.sqlite');
echo "advances of 60000: ", (int)$pdo->query("SELECT COUNT(*) FROM employee_advances WHERE amount = 60000")->fetchColumn(), "\n";
$mgr = login('manager1', 'Manager@1234x');
$page = http('GET', '/index.php?r=employees/1/advance', $mgr);
echo (strpos($page['body'], 'exceeds the maximum allowed') !== false ? 'limit error shown on page' : 'limit error NOT shown'), "\n";
// cancel own pending advance #2
preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m);
$res = http('POST', '/index.php?r=employees/1/advances/2/cancel', $mgr, ['csrf' => $m[1]]);
echo "manager cancel own advance #2: {$res['status']}\n";
echo "advance #2 status: ", $pdo->query('SELECT status FROM employee_advances WHERE id = 2')->fetchColumn(), "\n";
unlink($mgr);
