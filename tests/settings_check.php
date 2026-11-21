<?php
/** Verify the Payroll Policy (advance_max_amount) setting saves via the Company Profile form. */
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
$page = http('GET', '/index.php?r=settings/company', $jar);
echo "settings page: {$page['status']}\n";
echo (strpos($page['body'], 'advance_max_amount') !== false ? 'advance_max_amount input PRESENT' : 'advance_max_amount input MISSING'), "\n";
preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m);
$save = http('POST', '/index.php?r=settings/company', $jar, ['csrf' => $m[1], 'advance_max_amount' => '25000']);
echo "save: {$save['status']}\n";
$pdo = new PDO('sqlite:storage/dev.sqlite');
echo "setting advance_max_amount = ", $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'advance_max_amount'")->fetchColumn(), "\n";
// also restore it to 0 (dev convenience) — leave 25000, harmless? set to 0 to avoid limiting later dev logins
$pdo->exec("UPDATE company_settings SET setting_value = '0' WHERE setting_key = 'advance_max_amount'");
echo "reset to 0\n";
unlink($jar);
