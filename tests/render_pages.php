<?php
/** Render smoke test: fetch every route over direct HTTP and flag PHP errors in bodies. */
$base = 'http://127.0.0.1:8080';
$routes = explode(',', file_get_contents(__DIR__ . '/routes_body_check.txt'));
$routes = array_map('trim', $routes);
$cookies = [];
$http = curl_init();
curl_setopt_array($http, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_USERAGENT => 'render-check',
    CURLOPT_TIMEOUT => 20,
]);
function req($http, $url, &$cookies) {
    $jar = tempnam(sys_get_temp_dir(), 'ck');
    file_put_contents($jar, $cookies);
    curl_setopt($http, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($http, CURLOPT_COOKIEJAR, $jar);
    curl_setopt($http, CURLOPT_URL, $url);
    $resp = curl_exec($http);
    return [$resp, $jar];
}
// login as admin1
[$resp, $jar] = req($http, $base . '/index.php?r=auth/login', $cookies);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $resp, $m);
$csrf = $m[1] ?? '';
$post = http_build_query(['username' => 'admin1', 'password' => 'Admin@1234x', 'csrf' => $csrf]);
curl_setopt($http, CURLOPT_COOKIEFILE, $jar); curl_setopt($http, CURLOPT_COOKIEJAR, $jar);
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=auth/login');
curl_setopt($http, CURLOPT_POST, true); curl_setopt($http, CURLOPT_POSTFIELDS, $post);
curl_exec($http);
curl_setopt($http, CURLOPT_POST, false);
$bad = 0; $ok = 0;
foreach ($routes as $r) {
    $url = $base . '/index.php?r=' . urlencode($r);
    curl_setopt($http, CURLOPT_URL, $url);
    curl_setopt($http, CURLOPT_COOKIEFILE, $jar); curl_setopt($http, CURLOPT_COOKIEJAR, $jar);
    $resp = curl_exec($http);
    $code = curl_getinfo($http, CURLINFO_HTTP_CODE);
    $markers = ['PHP Warning', 'PHP Parse error', 'Fatal error', 'Uncaught', 'Warning:', 'Notice:'];
    $found = [];
    foreach ($markers as $mk) { if (stripos((string)$resp, $mk) !== false) $found[] = $mk; }
    if ($code !== 200 || strlen((string)$resp) < 2500 || $found) { $bad++; echo 'BAD[', $code, ' bytes=', strlen((string)$resp), '] ', $r, '  -> ', implode(',', $found), "\n"; }
    else { $ok++; }
}
echo "\n$ok routes clean, $bad problem routes\n";
