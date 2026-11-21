<?php
$base = 'http://127.0.0.1:8080';
$c = curl_init($base . '/index.php?r=auth%2Flogin');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true]);
$raw = curl_exec($c);
$len = (int)curl_getinfo($c, CURLINFO_HEADER_SIZE);
$body = substr($raw, $len);
preg_match_all('/^Set-Cookie:\s*([^;]+)/im', substr($raw, 0, $len), $cm);
$cookie = $cm[1][0] ?? '';
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
$ch = curl_init($base . '/index.php?r=auth%2Flogin');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => $cookie, CURLOPT_POSTFIELDS => http_build_query(['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234'])]);
$raw2 = curl_exec($ch);
preg_match_all('/^Set-Cookie:\s*([^;]+)/im', substr($raw2, 0, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE)), $cm2);
$cookie = $cm2[1][0] ?? $cookie;
echo "logged in; cookie=$cookie\n";

// Direct query with date as separate param
$c2 = curl_init($base . '/index.php?r=' . rawurlencode('reports/company-stock') . '&date=2026-11-17');
curl_setopt_array($c2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => $cookie]);
$raw3 = curl_exec($c2);
$len3 = (int)curl_getinfo($c2, CURLINFO_HEADER_SIZE);
$body3 = substr($raw3, $len3);
echo "http=", curl_getinfo($c2, CURLINFO_HTTP_CODE), " len=", strlen($body3), "\n";
if (preg_match('/<title>([^<]+)/', $body3, $t)) echo "title: ", trim($t[1]), "\n";
// Print the row for our test product
$startRow = strpos($body3, 'STDEMO-1');
if ($startRow !== false) {
    // Print a slice around it
    echo "row ctx:\n", substr($body3, $startRow - 30, 500), "\n";
}
