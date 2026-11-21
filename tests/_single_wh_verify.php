<?php
$base = 'http://127.0.0.1:8080';
$cookies = [];
$req = function (string $method, string $route, ?array $post = null) use ($base, &$cookies) {
    $ch = curl_init($base . '/index.php?r=' . rawurlencode($route));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; if ($cj) curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; '));
    $raw = curl_exec($ch);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    foreach (preg_split('/\r?\n/', substr($raw, 0, $len)) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return substr($raw, $len);
};
$p = $req('GET', 'auth/login');
preg_match('/name="csrf" value="([^"]+)"/', $p, $m);
$req('POST', 'auth/login', ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);

$pages = [
    'production/new' => 'production/new (Destination Warehouse)',
    'purchase/invoices/new' => 'purchase (Receive Into)',
    'stock/adjustments/new' => 'stock/adjustments/new',
    'stock/transfer' => 'stock/transfer',
    'stock/opening' => 'stock/opening',
    'stock/consumption/new' => 'RM consumption',
    'claims/goods/new' => 'claim goods',
    'raw/usage' => 'raw usage',
    'salesmen/2/issue' => 'goods issue form',
];
foreach ($pages as $route => $label) {
    $html = $req('GET', $route);
    $opts = [];
    if (preg_match_all('/<option[^>]*value="(\d+)"[^>]*>([^<]{0,90})<\/option>/', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $o) {
            $t = trim($o[2]);
            if ($t === '' || str_contains($t, '— none —') || str_contains($t, 'pick')) continue;
            // warehouse-ish options only
            if (str_contains($t, 'Warehouse') || str_contains($t, 'WH-') || str_contains($t, 'Ahmedpur')) $opts[] = '#' . $o[1] . ' ' . $t;
        }
    }
    echo str_pad($label, 38), ' | ', $opts ? implode(' ; ', array_unique($opts)) : '(no warehouse options / page issue)', "\n";
}
echo "\n=== settings/warehouses ===\n";
$pg = $req('GET', 'settings/warehouses');
echo "len=", strlen($pg), "\n";
foreach (['Single Main Warehouse (Ahmedpur)', 'Advanced — Add Warehouse', 'Main Warehouse – Ahmedpur', 'Ahmadpur Warehouse', 'Factory Warehouse', 'WH-FACTORY', 'WH-SM-'] as $needle) {
    echo str_pad($needle, 42), strpos($pg, $needle) !== false ? 'present' : 'absent', "\n";
}
preg_match_all('/<strong>([A-Z0-9\-]+)<\/strong>/', $pg, $mm);
echo 'codes shown: ', implode(', ', array_unique($mm[1])), "\n";
