<?php
$base = 'http://127.0.0.1:8080';
$cookies = [];
$req = function (string $m, string $route, ?array $post = null) use ($base, &$cookies) {
    $ch = curl_init($base . '/index.php?r=' . rawurlencode($route));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; if ($cj) curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; '));
    $raw = curl_exec($ch);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    foreach (preg_split('/\r?\n/', substr($raw, 0, $len)) as $line) { if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m2)) $cookies[trim($m2[1])] = trim($m2[2]); }
    return substr($raw, $len);
};
[$p] = [$req('GET', 'auth/login')];
preg_match('/name="csrf" value="([^"]+)"/', $p, $m);
$req('POST', 'auth/login', ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
$page = $req('GET', 'sales/invoices/new');
echo "page len=", strlen($page), "\n";

// embedded products JSON
if (preg_match('/products:\s*(\[.*?\])\s*,\s*field/s', $page, $mm)) {
    $json = $mm[1];
    $dec = json_decode($json, true);
    echo "products JSON bytes: ", strlen($json), " | decoded items: ", is_array($dec) ? count($dec) : 'JSON ERROR: ' . json_last_error_msg(), "\n";
    if (is_array($dec)) {
        foreach ($dec as $d) { echo '  id=', $d['id'], ' sku=', $d['sku'], ' name=', $d['name'], "\n"; }
    }
} else {
    echo "products JSON not found in page\n";
    $i = strpos($page, 'erpLineGrid');
    echo substr($page, $i === false ? 0 : $i - 50, 700), "\n";
}
echo "\n=== product <select> as rendered ===\n";
$i = strpos($page, 'name="items[product_id][0]"');
echo substr($page, $i === false ? 0 : $i - 20, 220), "\n";
// erp.js tag
foreach (['assets/js/erp.js', 'erp.js'] as $n) { $p2 = str_contains($page, $n); echo "contains '$n': ", ($p2 ? 'YES' : 'no'), "\n"; }
// erpLineGrid calls in page
echo "erpLineGrid present: ", (strpos($page, 'erpLineGrid') !== false ? 'YES' : 'no'), "\n";
