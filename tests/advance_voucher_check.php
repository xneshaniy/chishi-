<?php
/** Render the advance voucher print page as owner and check for key markers. */
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
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "login: {$lr['status']}\n";
$print = http('GET', '/index.php?r=employees/1/advances/1/print', $jar);
echo "voucher page: {$print['status']}\n";
foreach (['SALARY ADVANCE VOUCHER', 'Five Thousand Rupees', 'EMP-0001'] as $marker) {
    echo (strpos($print['body'], $marker) !== false ? 'FOUND  ' : 'MISSING '), $marker, "\n";
}
echo substr_count($print['body'], ') ?>') ? "artifact `) ?>` still present\n" : "no `) ?>` artifacts\n";
unlink($jar);
