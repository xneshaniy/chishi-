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
    return ['status' => (int)($m[1] ?? 0), 'body' => substr($out, $pos + 2)];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
echo "login page: {$login['status']}\n";
$csrf = null;
if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m)) $csrf = $m[1];
echo "csrf: ", $csrf ? substr($csrf, 0, 8) : 'NOT FOUND', "\n";
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $csrf, 'username' => 'owner', 'password' => 'owner1234']);
echo "POST: {$lr['status']}\n";
preg_match('/<div class="alert alert-error">([^<]+)</', $lr['body'], $fm) && print("error: " . $fm[1] . "\n");
// any message in body?
if (preg_match('/alert[^>]*>([^<]{5,200})/', $lr['body'], $mm)) echo "body msg: ", trim($mm[1]), "\n";
unlink($jar);
