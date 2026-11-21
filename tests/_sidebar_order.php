<?php
function req($method, $url, $jar, $body = null) {
    $ch = curl_init('http://127.0.0.1:8080' . $url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 25, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false];
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($body ?? []); }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch); curl_close($ch);
    return (string)$body;
}
$jar = tempnam(sys_get_temp_dir(), 'jar'); @unlink($jar);
[$c, $body] = req('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
req('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1] ?? '', 'username' => 'owner', 'password' => 'owner1234']);
$body = req('GET', '/index.php?r=dashboard', $jar);
foreach (explode("\n", $body) as $ln) {
    if (strpos($ln, 'nav-title') !== false) {
        $clean = trim(strip_tags($ln));
        echo $clean, "\n";
    }
}
