<?php
/** E2E as OWNER: production order with batch E2E-BATCH-1 completes against posted consumption. */
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
$pdo = new PDO('sqlite:storage/dev.sqlite');
$fg10 = $pdo->query("SELECT id FROM products WHERE sku = 'DEMO-FG-10'")->fetchColumn();
$whFactory = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-FACTORY'")->fetchColumn();
$before = ['rm' => (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = 3 AND warehouse_id = $whFactory")->fetchColumn(),
           'fg' => (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $fg10 AND warehouse_id = $whFactory")->fetchColumn()];
echo "before: RM={$before['rm']} FG={$before['fg']}\n";
// find an FG with an existing BOM
$bomId = $pdo->query("SELECT id FROM bills_of_materials WHERE product_id = $fg10 AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$form = http('GET', '/index.php?r=production/new', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$csrf = $m[1];
$create = http('POST', '/index.php?r=production/new', $jar, [
    'csrf' => $csrf, 'product_id' => $fg10, 'warehouse_id' => $whFactory, 'qty_planned' => '10',
    'bom_id' => $bomId ?: '', 'batch_no' => 'E2E-BATCH-1', 'expiry_date' => '',
]);
echo "create: {$create['status']} → {$create['location']}\n";
$orderId = (int)$pdo->query("SELECT MAX(id) FROM production_orders")->fetchColumn();
$row = $pdo->query("SELECT order_no, status, batch_no FROM production_orders WHERE id = $orderId")->fetch(PDO::FETCH_ASSOC);
echo "order {$row['order_no']} status={$row['status']} batch={$row['batch_no']}\n";
// release
$detail = http('GET', '/index.php?r=production/orders', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $detail['body'], $m);
$csrf = $m[1];
$release = http('POST', '/index.php?r=production/' . $orderId . '/release', $jar, ['csrf' => $csrf]);
echo "release: {$release['status']} → {$release['location']}\n";
echo "state: ", $pdo->query("SELECT status FROM production_orders WHERE id = $orderId")->fetchColumn(), "\n";
// complete
$detail = http('GET', '/index.php?r=production/' . $orderId . '/complete', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $detail['body'], $m);
$csrf = $m[1];
$complete = http('POST', '/index.php?r=production/' . $orderId . '/complete', $jar, ['csrf' => $csrf, 'qty_produced' => '10', 'waste_qty' => '0', 'labor_cost' => '500', 'overhead_cost' => '300']);
echo "complete: {$complete['status']} → {$complete['location']}\n";
$after = ['rm' => (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = 3 AND warehouse_id = $whFactory")->fetchColumn(),
          'fg' => (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $fg10 AND warehouse_id = $whFactory")->fetchColumn()];
echo "after:  RM={$after['rm']} FG={$after['fg']}\n";
$o = $pdo->query("SELECT materials_cost, labor_cost, overhead_cost, total_cost FROM production_orders WHERE id = $orderId")->fetch(PDO::FETCH_ASSOC);
echo "materials={$o['materials_cost']} labor={$o['labor_cost']} overhead={$o['overhead_cost']} total={$o['total_cost']}\n";
$inp = $pdo->query("SELECT COUNT(*) cnt, COALESCE(SUM(qty),0) kg, COALESCE(SUM(total_cost),0) cost FROM production_inputs WHERE production_order_id = $orderId")->fetch(PDO::FETCH_ASSOC);
echo "inputs now: {$inp['cnt']} rows | {$inp['kg']} kg | value {$inp['cost']}\n";
unlink($jar);
