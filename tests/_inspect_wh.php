<?php
$p = new PDO('sqlite:storage/dev.sqlite');
echo "=== warehouses ===\n";
foreach ($p->query("SELECT id, code, name, type, is_active, is_default FROM warehouses ORDER BY id") as $r) {
    echo str_pad($r['id'], 4), str_pad($r['code'], 16), ' | type=', str_pad($r['type'], 10), ' | act=', $r['is_active'], ' | def=', $r['is_default'], ' | ', $r['name'], "\n";
}
echo "\n=== stock qty by warehouse (non-zero) ===\n";
foreach ($p->query("SELECT w.code, w.name, SUM(b.qty) AS total_units, COUNT(*) AS rows_, SUM(CASE WHEN b.qty <> 0 THEN 1 ELSE 0 END) AS nonzero FROM stock_balances b JOIN warehouses w ON w.id = b.warehouse_id GROUP BY w.id ORDER BY nonzero DESC") as $r) {
    echo str_pad($r['code'], 16), ' | units=', $r['total_units'], ' | rows=', $r['rows_'], ' | nonzero=', $r['nonzero'], "\n";
}
echo "\n=== salesman vans & their stock ===\n";
foreach ($p->query("SELECT w.id, w.code, w.name, w.is_active FROM warehouses w WHERE w.type = 'salesman'") as $r) echo '  #', $r['id'], ' ', $r['code'], ' ', $r['name'], ' act=', $r['is_active'], "\n";
echo "\n=== salesmen van links ===\n";
foreach ($p->query("SELECT s.id, s.code, s.name, s.warehouse_id, s.is_active FROM salesmen s ORDER BY s.id") as $r) echo '  #', $r['id'], ' ', str_pad($r['code'], 12), str_pad($r['name'], 40), ' van_wh=', ($r['warehouse_id'] ?? '-'), ' act=', $r['is_active'], "\n";
echo "\n=== Jampur tables ===\n";
foreach ($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%jampur%' OR name LIKE '%grind%'") as $r) echo '  table: ', $r['name'], "\n";
echo "\n=== audit_logs for WH-MAIN (sample) ===\n";
foreach ($p->query("SELECT COUNT(*) FROM audit_logs WHERE entity='warehouses'") as $r) echo '  warehouse audit rows: ', $r[0], "\n";
echo "\n=== which products have stock & where ===\n";
foreach ($p->query("SELECT p.sku, w.code, b.qty FROM stock_balances b JOIN warehouses w ON w.id = b.warehouse_id JOIN products p ON p.id = b.product_id WHERE b.qty <> 0 ORDER BY p.sku, w.code") as $r) {
    echo '  ', str_pad($r['sku'], 14), str_pad($r['code'], 14), ' qty=', $r['qty'], "\n";
}
