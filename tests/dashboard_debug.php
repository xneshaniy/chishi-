<?php
$base = 'http://127.0.0.1:8080';
$http = curl_init();
curl_setopt_array($http, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'render-check', CURLOPT_TIMEOUT => 20]);
$jar = tempnam(sys_get_temp_dir(), 'ck'); file_put_contents($jar, '');
curl_setopt($http, CURLOPT_COOKIEFILE, $jar); curl_setopt($http, CURLOPT_COOKIEJAR, $jar);
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=auth/login'); $resp = curl_exec($http);
preg_match('/name="csrf" value="([a-f0-9]+)"/', (string)$resp, $m);
echo "csrf: ", ($m[1] ?? 'MISS'), "\n";
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=auth/login');
curl_setopt($http, CURLOPT_POST, true);
curl_setopt($http, CURLOPT_POSTFIELDS, http_build_query(['username' => 'admin1', 'password' => 'Admin@1234x', 'csrf' => $m[1] ?? '']));
$resp = curl_exec($http);
echo "POST code: ", curl_getinfo($http, CURLINFO_HTTP_CODE), "\n";
echo "POST location: ", (function() use ($resp) { $h = explode("\r\n\r\n", (string)$resp)[0]; return preg_match('/location: (.+)/i', $h, $mm) ? trim($mm[1]) : 'none'; })(), "\n";
curl_setopt($http, CURLOPT_POST, false);
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=dashboard'); $resp = curl_exec($http);
echo "dashboard code: ", curl_getinfo($http, CURLINFO_HTTP_CODE), " bytes: ", strlen((string)$resp), "\n";
echo "body head:\n", substr((string)$resp, 0, 600), "\n";
