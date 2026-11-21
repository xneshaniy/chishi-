<?php
function req($method, $url, $jar, $body = null): array {
    $ch = curl_init('http://127.0.0.1:8080' . $url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false];
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($body ?? []); }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, (string)$body];
}
foreach ([['manager1','Manager@1234x'],['accountant1','Accountant@1234x'],['owner','owner1234'],['admin1','Admin@1234x']] as [$u,$p]) {
    $jar = tempnam(sys_get_temp_dir(), 'jar'); @unlink($jar);
    [$c, $body] = req('GET', '/index.php?r=auth/login', $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
    req('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1] ?? '', 'username' => $u, 'password' => $p]);
    [$code, $b] = req('GET', '/index.php?r=gifts', $jar);
    echo str_pad($u, 12), " /gifts: HTTP $code\n";
    [$code, $b] = req('GET', '/index.php?r=gifts/new-money', $jar);
    echo "             /gifts/new-money HTTP $code\n";
    [$code, $b] = req('GET', '/index.php?r=gifts/new-goods', $jar);
    echo "             /gifts/new-goods HTTP $code\n";
}
