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
foreach (['reports/company-stock?date=2026-11-17', 'reports/company-stock', 'reports/fg-daily'] as $r) {
    $direct = $base . '/index.php?r=' . $r;
    $c2 = curl_init($direct);
    curl_setopt_array($c2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => $cookie]);
    $raw3 = curl_exec($c2);
    $len3 = (int)curl_getinfo($c2, CURLINFO_HEADER_SIZE);
    $body3 = substr($raw3, $len3);
    echo "=== r=", $r, " === http=", curl_getinfo($c2, CURLINFO_HTTP_CODE), " len=", strlen($body3), "\n";
    if (preg_match('/<title>([^<]+)/', $body3, $match)) echo "  title: ", trim($match[1]), "\n";
    if (str_contains($body3, 'Stock Test Widget')) echo "  FOUND STDEMO-1\n";
    $startRow = strpos($body3, 'Stock Test Widget');
    if ($startRow !== false) {
        // Print context around the row
        echo "  context: ", strip_tags(substr($body3, max(0, $startRow - 120), 400)), "\n";
    }
}
