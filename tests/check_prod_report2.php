<?php
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', substr($out, 0, $pos), $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => substr($out ?: '', ($pos === false ? 0 : $pos + 2))];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "login: {$lr['status']}\n";
$page = http('GET', '/index.php?r=reports/production', $jar);
echo "page: {$page['status']}\n";
echo "warnings present: ", (strpos($page['body'], 'Warning:') !== false ? 'YES' : 'no'), "\n";
echo "markers: Orders Completed present = ", (strpos($page['body'], 'Orders Completed') !== false ? 'yes' : 'no'), "\n";
echo "RM Consumed column present = ", (strpos($page['body'], 'RM Consumed (KG)') !== false ? 'yes' : 'no'), "\n";
echo "Cost / Unit present = ", (strpos($page['body'], 'Cost / Unit') !== false ? 'yes' : 'no'), "\n";
unlink($jar);
