<?php
/** Verify the rendered invoice form: no Receive Into, JS before inline grid, product+stock JSON, hint element. */
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
echo "page len: ", strlen($page), "\n";
echo "  'Receive Into' present:     ", (strpos($page, 'Receive Into') !== false ? 'YES (bad)' : 'NO (good)'), "\n";
echo "  'cash_account_id' present:  ", (strpos($page, 'name="cash_account_id"') !== false ? 'YES (bad)' : 'NO (good)'), "\n";
echo "  'cashAccountWrap' present:  ", (strpos($page, 'cashAccountWrap') !== false ? 'YES (bad)' : 'NO (good)'), "\n";
echo "  'cashAccountSelect' present:", (strpos($page, 'cashAccountSelect') !== false ? 'YES (bad)' : 'NO (good)'), "\n";
echo "  stockHint element:          ", (strpos($page, 'id="stockHint"') !== false ? 'YES' : 'NO (bad)'), "\n";
echo "  postBtn id:                 ", (strpos($page, 'id="postBtn"') !== false ? 'YES' : 'NO (bad)'), "\n";

$posErpJs = strpos($page, 'assets/js/erp.js');
$posGridCall = strpos($page, 'erpLineGrid({');
echo "erp.js @ byte $posErpJs  |  erpLineGrid call @ byte $posGridCall  |  erp.js loads BEFORE inline call: ", (($posErpJs !== false && $posGridCall !== false && $posErpJs < $posGridCall) ? 'YES ✓' : 'NO ✗'), "\n";

// embedded products JSON with stock
if (preg_match('/products:\s*(\[.*?\])\s*,\s*field/s', $page, $mm)) {
    $dec = json_decode($mm[1], true);
    echo "products JSON decodes:       ", is_array($dec) ? count($dec) . ' items' : 'ERROR ' . json_last_error_msg(), "\n";
    foreach ($dec ?? [] as $d) echo '   id=', $d['id'], ' sku=', $d['sku'], ' sale_price=', $d['sale_price'], ' stock=', var_export($d['stock'] ?? null, true), "\n";
} else { echo "products JSON NOT FOUND\n"; }

// stockList JSON for the hint
if (preg_match('/var list = (\[.*?\]);/s', $page, $mm)) {
    $dec = json_decode($mm[1], true);
    echo "stockList decodes:           ", is_array($dec) ? count($dec) . ' items' : 'ERROR', "\n";
}
// raw product select still server-rendered with the placeholder
echo "product select placeholder:   ", (strpos($page, '— select product —') !== false ? 'present' : 'MISSING'), "\n";
