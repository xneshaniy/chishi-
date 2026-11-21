<?php
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite');
echo "SETTINGS\n";
foreach ($pdo->query("SELECT setting_key, setting_value FROM company_settings WHERE setting_key LIKE 'packaging%' OR setting_key LIKE 'rm_unit%'") as $r) {
    echo $r[0] . '=' . $r[1] . PHP_EOL;
}
echo "UNITS\n";
foreach ($pdo->query('SELECT id,name,short FROM units') as $r) {
    echo $r[0] . ' ' . $r[1] . ' ' . $r[2] . PHP_EOL;
}
echo "PRODUCTS\n";
foreach ($pdo->query("SELECT id,sku,name,kind,unit_id,pack_size,issue_rate,sale_price FROM products ORDER BY id LIMIT 30") as $r) {
    echo implode(' | ', array_map(fn($x) => $x === null ? 'NULL' : $x, array_slice($r, 0, 8))) . PHP_EOL;
}
echo "PACKAGING OVERRIDES\n";
foreach ($pdo->query('SELECT * FROM product_packaging') as $r) {
    echo json_encode($r) . PHP_EOL;
}
echo "STOCK\n";
foreach ($pdo->query("SELECT b.product_id,p.sku,b.warehouse_id,w.code,b.qty FROM stock_balances b JOIN products p ON p.id=b.product_id JOIN warehouses w ON w.id=b.warehouse_id WHERE p.kind='finished' ORDER BY b.product_id") as $r) {
    echo implode(' | ', $r) . PHP_EOL;
}
echo "SALESMEN\n";
foreach ($pdo->query('SELECT id,code,name,warehouse_id,is_active FROM salesmen ORDER BY id') as $r) {
    echo implode(' | ', array_map(fn($x) => $x === null ? 'NULL' : $x, array_slice($r, 0, 5))) . PHP_EOL;
}
