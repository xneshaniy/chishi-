<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pids = array_map(fn($r) => (int)$r['id'], $pdo->query("SELECT id FROM products WHERE sku = 'DEMO-FG-10'")->fetchAll(PDO::FETCH_ASSOC));
echo "product(s): ", json_encode($pids), "\n";
$wh = $pdo->query("SELECT id, code, type FROM warehouses WHERE code='WH-MAIN'")->fetch(PDO::FETCH_ASSOC);
echo "WH-MAIN: ", json_encode($wh), "\n";
$sm = $pdo->query("SELECT id, warehouse_id FROM salesmen WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
echo "DEMO Salesman 2 van wh: ", json_encode($sm), "\n";
foreach ($pids as $pid) {
    $qty = $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE warehouse_id = {$wh['id']} AND product_id = $pid")->fetchColumn();
    echo "  main wh pid=$pid qty=$qty\n";
}
foreach ($pids as $pid) {
    $qty = $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE warehouse_id = {$sm['warehouse_id']} AND product_id = $pid")->fetchColumn();
    echo "  van pid=$pid qty=$qty\n";
}
echo "sm2 payable: ", $pdo->query("SELECT COALESCE(SUM(debit - credit),0) FROM salesman_ledger WHERE salesman_id = 2")->fetchColumn(), "\n";
