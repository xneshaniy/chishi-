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
echo "logged in; cookie=$cookie\n\n";

$date = date('Y-m-d');
foreach (['reports/company-stock?date=' . $date, 'reports/company-stock?date=' . $date . '&salesman_id=1', 'reports/fg-daily?date=' . $date] as $r) {
    $c2 = curl_init($base . '/index.php?r=' . rawurlencode($r));
    curl_setopt_array($c2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => $cookie]);
    $raw3 = curl_exec($c2);
    $len3 = (int)curl_getinfo($c2, CURLINFO_HEADER_SIZE);
    $body3 = substr($raw3, $len3);
    $title = '';
    if (preg_match('/<title>([^<]+)/', $body3, $t)) $title = trim($t[1]);
    echo "=== r=", $r, " === http=", curl_getinfo($c2, CURLINFO_HTTP_CODE), " len=", strlen($body3), " title=\"", $title, "\"\n";
    foreach (['STDEMO-1', 'Stock Test Widget'] as $needle) {
        if (str_contains($body3, $needle)) echo "  contains: $needle\n";
    }
    // Find the row for our test product
    if (preg_match_all('/Stock Test Widget[^<]*<\/td>(.*?)<\/tr>/is', $body3, $mm)) {
        echo "  product row: ", json_encode(trim(strip_tags($mm[0][0]))), "\n";
    }
}
