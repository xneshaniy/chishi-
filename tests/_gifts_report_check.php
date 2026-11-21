<?php
function req($method, $url, $jar, $body = null): array {
    $ch = curl_init('http://127.0.0.1:8080' . $url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false];
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($body ?? []); }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, (string)$body];
}
$jar = tempnam(sys_get_temp_dir(), 'jar'); @unlink($jar);
[$c, $body] = req('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
req('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1] ?? '', 'username' => 'owner', 'password' => 'owner1234']);
[$code, $body] = req('GET', '/index.php?r=reports/gifts', $jar);
echo "status=$code bytes=" . strlen($body) . "\n";
echo "fatal=" . (stripos($body, 'Fatal error') !== false ? 'YES' : 'no') . "\n";
$i = strpos($body, '<main');
$end = strpos($body, '</main>');
echo substr(strip_tags(substr($body, max(0, $i), min(1500, $end - $i))), 0, 900), "\n";
