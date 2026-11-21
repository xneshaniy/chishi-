<?php
function req($method, $url, $jar, $body = null) {
    $ch = curl_init('http://127.0.0.1:8080' . $url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 25, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false];
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($body ?? []); }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, (string)$body];
}
$jar = tempnam(sys_get_temp_dir(), 'jar'); @unlink($jar);
[$c1, $body] = req('GET', '/index.php?r=auth/login', $jar);
echo "GET login: $c1 bytes=" . strlen($body) . "\n";
preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
echo "csrf: ", substr($csrf, 0, 8), "\n";
[$c2, $body] = req('POST', '/index.php?r=auth/login', $jar, ['csrf' => $csrf, 'username' => 'owner', 'password' => 'owner1234']);
echo "POST login: $c2 bytes=" . strlen($body) . "\n";
echo "body:\n", substr(strip_tags($body), 0, 400), "\n";
[$c3, $body3] = req('GET', '/index.php?r=dashboard', $jar);
echo "dash: $c3 bytes=" . strlen($body3) . "\n";
echo "contains fatal: " . (stripos($body3, 'Fatal error') !== false ? 'YES' : 'no') . "\n";
