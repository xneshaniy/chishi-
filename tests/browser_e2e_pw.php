<?php
/** E2E via HTTP as OWNER: RM purchase 2 TON → consumption 150 kg → verify. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    $headers = substr($out, 0, $pos);
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', $headers ?? '', $m);
    preg_match('/Location: ([^\r\n]+)/', $headers ?? '', $loc);
    return ['status' => (int)($m[1] ?? 0), 'location' => $loc[1] ?? null, 'body' => substr($out ?: '', $pos + 2)];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "login: {$lr['status']}\n";
// 1. RM purchase: 2 TON of Virginia Leaf @ Rs 90,000/ton from DEMO supplier
$edit = http('GET', '/index.php?r=purchase/invoices/new&raw=1', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $edit['body'], $m);
$csrf = $m[1];
preg_match('/name="idem_key" value="([a-f0-9]+)"/', $edit['body'], $m2);
$idem = $m2[1] ?? bin2hex(random_bytes(16));
echo "idem: ", substr($idem, 0, 8), "\n";
// find supplier id
$pdo = new PDO('sqlite:storage/dev.sqlite');
$supId = $pdo->query("SELECT id FROM suppliers WHERE name LIKE '%Tobacco%' OR name LIKE '%Supplier%' ORDER BY id LIMIT 1")->fetchColumn();
$whFactory = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-FACTORY'")->fetchColumn();
$rmId = $pdo->query("SELECT id FROM products WHERE sku = 'DEMO-RAW-VIR'")->fetchColumn();
$resp = http('POST', '/index.php?r=purchase/invoices/new', $jar, [
    'csrf' => $csrf, 'idem_key' => $idem, 'supplier_id' => $supId, 'po_id' => '', 'supplier_invoice_no' => 'SI-E2E-1',
    'date' => date('Y-m-d'), 'warehouse_id' => $whFactory, 'payment_type' => 'credit', 'discount' => '0',
    'items[product_id][0]' => $rmId, 'items[qty][0]' => '2', 'items[unit][0]' => 'TON', 'items[cost][0]' => '90000', 'items[tax_rate][0]' => '0', 'items[is_bonus][0]' => '',
]);
echo "purchase POST: {$resp['status']} → {$resp['location']}\n";
$pi = $pdo->query("SELECT MAX(id) FROM purchase_invoices")->fetchColumn();
$piRow = $pdo->query("SELECT id, status, journal_entry_id, total FROM purchase_invoices WHERE id = $pi")->fetch(PDO::FETCH_ASSOC);
echo "invoice #{$pi} status={$piRow['status']} journal={$piRow['journal_entry_id']} total={$piRow['total']}\n";
echo "item unit: ", ($r = $pdo->query("SELECT unit_short, qty, qty_kg FROM purchase_items WHERE invoice_id = $pi")->fetch(PDO::FETCH_ASSOC)) ? "{$r['qty']} {$r['unit_short']} = {$r['qty_kg']} KG" : 'none', "\n";
echo "VIR balance now: ", $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $rmId AND warehouse_id = $whFactory")->fetchColumn(), " kg\n";
unlink($jar);
