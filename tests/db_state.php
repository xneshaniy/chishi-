<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "products: ", $db->query('SELECT COUNT(*) FROM products')->fetchColumn(), "\n";
echo "DEMO products: ", $db->query("SELECT COUNT(*) FROM products WHERE sku LIKE 'DEMO-%'")->fetchColumn(), "\n";
echo "movements: ", $db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn(), "\n";
echo "posted purchase_invoices: ", $db->query("SELECT COUNT(*) FROM purchase_invoices WHERE status='posted'")->fetchColumn(), "\n";
echo "movements today: ";
foreach ($db->query("SELECT type, COUNT(*) n FROM stock_movements GROUP BY type") as $r) echo $r['type'], '=', $r['n'], '  ';
echo "\nfile mtime: ", date('Y-m-d H:i:s', filemtime(__DIR__ . '/../storage/dev.sqlite')), "\n";
