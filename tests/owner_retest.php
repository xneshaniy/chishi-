<?php
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    $headers = substr($out, 0, $pos);
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', $headers, $m);
    preg_match('/Location: ([^\r\n]+)/', $headers, $loc);
    return ['status' => (int)($m[1] ?? 0), 'body' => substr($out, $pos + 2), 'location' => $loc[1] ?? null];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
echo "login page: {$login['status']}\n";
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "POST login: {$lr['status']} -> {$lr['location']}\n";
$d = http('GET', '/index.php?r=dashboard', $jar);
echo "dashboard: {$d['status']}\n";
$s = http('GET', '/index.php?r=settings/company', $jar);
echo "settings/company: {$s['status']}\n";
$s2 = http('GET', '/index.php?r=employees/1/edit', $jar);
echo "employees/1/edit: {$s2['status']}\n";
$pdo = new PDO('sqlite:storage/dev.sqlite');
echo "\nfailed attempts in last hour for owner: ", (int)$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE username='owner' AND success=0 AND attempted_at > datetime('now','-1 hour')")->fetchColumn(), "\n";
unlink($jar);
