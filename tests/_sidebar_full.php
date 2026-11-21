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
[$c, $body] = req('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
req('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1] ?? '', 'username' => 'owner', 'password' => 'owner1234']);
[$code, $body] = req('GET', '/index.php?r=dashboard', $jar);
echo "dashboard status=$code bytes=" . strlen($body) . "\n";
// Find each nav-title section in order
$needle = 'class="nav-title"';
$i = 0;
while (true) {
    $p = strpos($body, $needle, $i);
    if ($p === false) break;
    $start = strpos($body, '>', $p) + 1;
    $end = strpos($body, '</div>', $start);
    $txt = trim(strip_tags(substr($body, $start, $end - $start)));
    echo str_repeat('-', $i + 1) . " " . $txt, "\n";
    $i = $end + 2;
}
