<?php
/** E2E as OWNER: set FG product packaging config and verify it persists + reports display. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', substr($out, 0, $pos), $m);
    preg_match('/Location: ([^\r\n]+)/', substr($out, 0, $pos), $loc);
    return ['status' => (int)($m[1] ?? 0), 'location' => $loc[1] ?? null, 'body' => substr($out ?: '', $pos + 2)];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "login: {$lr['status']}\n";
// find FG-10 product id and current packaging
$pdo = new PDO('sqlite:storage/dev.sqlite');
$fid = $pdo->query("SELECT id FROM products WHERE sku = 'DEMO-FG-10'")->fetchColumn();
echo "DEMO-FG-10 id: $fid\n";
// GET edit form, capture csrf and current values
$form = http('GET', '/index.php?r=products/' . $fid . '/edit', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$csrf = $m[1];
echo "has packaging section: ", (strpos($form['body'], 'packagingSection') !== false ? 'YES' : 'NO'), "\n";
echo "page title includes Edit Product: ", (strpos($form['body'], 'Edit Product') !== false ? 'YES' : 'NO'), "\n";
// capture the other fields so we don't wipe them — re-read the product row
$p = $pdo->query("SELECT * FROM products WHERE id = $fid")->fetch(PDO::FETCH_ASSOC);
$body = [
    'csrf' => $csrf,
    'sku' => $p['sku'], 'name' => $p['name'], 'category_id' => (string)($p['category_id'] ?? ''),
    'unit_id' => (string)($p['unit_id'] ?? ''), 'kind' => 'finished', 'pack_size' => $p['pack_size'] ?? '',
    'purchase_price' => (string)$p['purchase_price'], 'sale_price' => (string)$p['sale_price'],
    'issue_rate' => (string)$p['issue_rate'], 'min_price' => (string)$p['min_price'], 'tax_rate' => (string)$p['tax_rate'],
    'reorder_level' => (string)$p['reorder_level'], 'is_active' => '1',
    'pack_pieces' => '12', 'bundle_packs' => '10', 'packet_bundles' => '8',
];
$save = http('POST', '/index.php?r=products/' . $fid . '/edit', $jar, $body);
echo "save: {$save['status']} → {$save['location']}\n";
$pkg = $pdo->query("SELECT * FROM product_packaging WHERE product_id = $fid")->fetch(PDO::FETCH_ASSOC);
echo "packaging saved: pieces={$pkg['pack_pieces']} packs={$pkg['bundle_packs']} bundles={$pkg['packet_bundles']}\n";
unlink($jar);
