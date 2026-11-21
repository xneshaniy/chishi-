<?php
/** Re-run stock closure but keep the test DB forward (no shutdown-restore) so we can verify the page. */
$root = dirname(__DIR__);
require $root . '/app/core/bootstrap.php';
$today = date('Y-m-d');
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$smA = $pdo->query("SELECT id, code, name, warehouse_id FROM salesmen WHERE code = 'SM-001'")->fetch(PDO::FETCH_ASSOC);
$smB = $pdo->query("SELECT id, code, name, warehouse_id FROM salesmen WHERE code = 'DEMO-SM-001'")->fetch(PDO::FETCH_ASSOC);
$cust = (int)$pdo->query("SELECT MIN(id) FROM customers WHERE is_active=1")->fetchColumn();
$whMain = (int)$pdo->query("SELECT id FROM warehouses WHERE type = 'central' ORDER BY id LIMIT 1")->fetchColumn();
$pdo->exec("DELETE FROM products WHERE sku = 'STDEMO-1'");
$product = db_insert('products', ['sku' => 'STDEMO-1', 'name' => 'Stock Test Widget', 'kind' => 'finished', 'min_price' => 0, 'is_active' => 1]);
echo "product id=$product\n";
echo "A={$smA['code']}_id={$smA['id']} wh={$smA['warehouse_id']}; B={$smB['code']}_id={$smB['id']} wh={$smB['warehouse_id']}\n";

Inventory::receive($product, $whMain, 1000, 1.50, 'opening', 'test_stock_close_pg', null, 'BATCH-1', null, 'E2E test — opening');
Inventory::transfer($product, $whMain, (int)$smA['warehouse_id'], 300, 'salesman_issue_out', 'salesman_issue_in', null, null, 'BATCH-1', null, 'Issue to A');
Inventory::transfer($product, $whMain, (int)$smB['warehouse_id'], 200, 'salesman_issue_out', 'salesman_issue_in', null, null, 'BATCH-1', null, 'Issue to B');
$invA = db_insert('sales_invoices', ['invoice_no' => 'INV-TMP-E2E-A', 'customer_id' => $cust, 'salesman_id' => (int)$smA['id'], 'warehouse_id' => (int)$smA['warehouse_id'], 'date' => $today, 'payment_type' => 'cash', 'cash_account_id' => null, 'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'total' => 1000, 'status' => 'pending', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invA, 'product_id' => $product, 'qty' => 100, 'unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 1000, 'net_before_tax' => 1000, 'cost_price' => 1.5, 'cost_total' => 150, 'is_bonus' => 0]);
Posting::postSalesInvoice($invA);
$invB = db_insert('sales_invoices', ['invoice_no' => 'INV-TMP-E2E-B', 'customer_id' => $cust, 'salesman_id' => (int)$smB['id'], 'warehouse_id' => (int)$smB['warehouse_id'], 'date' => $today, 'payment_type' => 'cash', 'cash_account_id' => null, 'subtotal' => 500, 'discount' => 0, 'tax' => 0, 'total' => 500, 'status' => 'pending', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invB, 'product_id' => $product, 'qty' => 50, 'unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 500, 'net_before_tax' => 500, 'cost_price' => 1.5, 'cost_total' => 75, 'is_bonus' => 0]);
Posting::postSalesInvoice($invB);

$balFact = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id=$whMain")->fetchColumn();
$balA    = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id={$smA['warehouse_id']}")->fetchColumn();
$balB    = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id={$smB['warehouse_id']}")->fetchColumn();
echo "factory=$balFact  A=$balA  B=$balB  combined=" . ($balFact + $balA + $balB) . "\n";
echo "DONE - leave DB in this state for the HTTP check\n";
