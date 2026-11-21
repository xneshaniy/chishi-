<?php
/** HTTP smoke test: login, dashboard, key pages (uses curl via shell or file_get_contents is session-unaware, so use curl-like stream with cookie jar) */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
function req(string $url, ?string $post = null, ?string $jar = null, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $raw, $err];
}
[$c, $body] = req($base . 'auth/login', null, $jar);
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
echo "login page: $c\n";
$csrf = $m[1] ?? '';
echo "csrf: ", substr($csrf, 0, 16), "…\n";
[$c, $body] = req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar, false);
echo "login post: $c -> ", (preg_match('/Location: ([^\r\n]+)/i', $body, $lm) ? trim($lm[1]) : '(no location)'), "\n";
foreach (['dashboard','products','customers','accounting/accounts','accounting/trial-balance','settings/company','reports'] as $page) {
    [$c, $body] = req($base . $page, null, $jar);
    $ok = $c === 200 ? 'OK' : 'FAIL';
    $contains = (strpos($body, 'JUSTI') !== false || strpos($body, 'CTC') !== false || strpos($body, 'Dashboard') !== false) ? 'content-ok' : 'content-?';
    echo str_pad($page, 32), " $c $ok $contains\n";
}
unlink($jar);
