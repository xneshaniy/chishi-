<?php
/** E2E via HTTP as OWNER: factory consumption of 150 kg. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $cmd .= ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    $out = shell_exec($cmd);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) $pos = strpos($out, "\n\n");
    $headers = substr($out, 0, $pos);
    preg_match('/HTTP\/\d(?:\.\d)? (\d+)/', $headers ?? '', $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => substr($out ?: '', $pos + 2)];
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = http('GET', '/index.php?r=auth/login', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
$lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => 'owner', 'password' => 'owner1234']);
echo "login: {$lr['status']}\n";
$pdo = new PDO('sqlite:storage/dev.sqlite');
$whFactory = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-FACTORY'")->fetchColumn();
$rmId = $pdo->query("SELECT id FROM products WHERE sku = 'DEMO-RAW-VIR'")->fetchColumn();
// Open the form, capture csrf
$form = http('GET', '/index.php?r=stock/consumption/new', $jar);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$csrf = $m[1];
// Post the consumption: 150 kg to batch E2E-BATCH-1
$resp = http('POST', '/index.php?r=stock/consumption/new', $jar, [
    'csrf' => $csrf, 'date' => date('Y-m-d'), 'batch_no' => 'E2E-BATCH-1', 'details' => 'E2E test consumption',
    'production_order_id' => '', 'warehouse_id' => $whFactory, 'approved_by' => '', 'notes' => 'http e2e',
    'items[product_id][0]' => $rmId, 'items[qty_kg][0]' => '150',
]);
echo "consumption POST: {$resp['status']}\n";
$issue = $pdo->query("SELECT MAX(id) FROM factory_issues")->fetchColumn();
$row = $pdo->query("SELECT issue_no, status, batch_no FROM factory_issues WHERE id = $issue")->fetch(PDO::FETCH_ASSOC);
echo "issue: {$row['issue_no']} status={$row['status']} batch={$row['batch_no']}\n";
$bal = $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $rmId AND warehouse_id = $whFactory")->fetchColumn();
echo "VIR balance now: $bal kg\n";
$line = $pdo->query("SELECT qty_kg, unit_cost, total_cost FROM factory_issue_items WHERE issue_id = $issue")->fetch(PDO::FETCH_ASSOC);
echo "line: {$line['qty_kg']} kg × cost {$line['unit_cost']} = {$line['total_cost']}\n";
unlink($jar);
