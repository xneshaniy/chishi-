<?php
/**
 * Company Stock Daily — numeric closure test using the same service-level helpers
 * the ERP itself uses (db_insert, Posting::postSalesInvoice, Inventory).
 * Factory produces 1000 → issue 300 to A, 200 to B → A sells 100 (van), B sells 50 (van).
 * Expect: factory 500, A van 200, B van 150, combined 850.
 * Restores dev.sqlite afterwards — leaves no test data behind.
 */
$bakPath = 'storage/backups/dev.sqlite.before-stock-e2e-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.bak';
@copy('storage/dev.sqlite', __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $bakPath));
register_shutdown_function(function () use ($bakPath) {
    $src = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $bakPath);
    $dst = __DIR__ . '/../storage/dev.sqlite';
    if (file_exists($src)) { copy($src, $dst); echo "[cleanup] dev.sqlite restored from this run's snapshot\n"; }
});

$root = dirname(__DIR__);
require $root . '/app/core/bootstrap.php';
$today = date('Y-m-d');
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$smA = $pdo->query("SELECT id, code, name, warehouse_id FROM salesmen WHERE code = 'SM-001'")->fetch(PDO::FETCH_ASSOC);
$smB = $pdo->query("SELECT id, code, name, warehouse_id FROM salesmen WHERE code = 'DEMO-SM-001'")->fetch(PDO::FETCH_ASSOC);
$cust = (int)$pdo->query("SELECT MIN(id) FROM customers WHERE is_active=1")->fetchColumn();
$whMain = (int)$pdo->query("SELECT id FROM warehouses WHERE type = 'central' ORDER BY id LIMIT 1")->fetchColumn();
$prodName = 'STDEMO-1';
$prodId = (int)$pdo->query("SELECT id FROM products WHERE sku = '$prodName'")->fetchColumn();
if ($prodId) { $pdo->exec("DELETE FROM products WHERE id = $prodId"); }
$product = db_insert('products', ['sku' => $prodName, 'name' => 'Stock Test Widget', 'kind' => 'finished', 'min_price' => 0, 'is_active' => 1]);
echo "product id=$product\n";
echo "A={$smA['code']}_id={$smA['id']} wh={$smA['warehouse_id']}; B={$smB['code']}_id={$smB['id']} wh={$smB['warehouse_id']}; cust=$cust; whMain=$whMain\n\n";

// 1. Factory opens with 1,000 at cost 1.50
Inventory::receive($product, $whMain, 1000, 1.50, 'opening', 'test_stock_close', null, 'BATCH-1', null, 'E2E stock close test — opening');
// 2. Issue 300 to A, 200 to B (transfers)
Inventory::transfer($product, $whMain, (int)$smA['warehouse_id'], 300, 'salesman_issue_out', 'salesman_issue_in', null, null, 'BATCH-1', null, 'Issue to A — E2E');
Inventory::transfer($product, $whMain, (int)$smB['warehouse_id'], 200, 'salesman_issue_out', 'salesman_issue_in', null, null, 'BATCH-1', null, 'Issue to B — E2E');
// 3. A sells 100, B sells 50 — pending → posting function
$invA = db_insert('sales_invoices', ['invoice_no' => 'INV-TMP-E2E-A', 'customer_id' => $cust, 'salesman_id' => (int)$smA['id'], 'warehouse_id' => (int)$smA['warehouse_id'], 'date' => $today, 'payment_type' => 'cash', 'cash_account_id' => null, 'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'total' => 1000, 'status' => 'pending', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invA, 'product_id' => $product, 'qty' => 100, 'unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 1000, 'net_before_tax' => 1000, 'cost_price' => 1.5, 'cost_total' => 150, 'is_bonus' => 0]);
Posting::postSalesInvoice($invA);
$invB = db_insert('sales_invoices', ['invoice_no' => 'INV-TMP-E2E-B', 'customer_id' => $cust, 'salesman_id' => (int)$smB['id'], 'warehouse_id' => (int)$smB['warehouse_id'], 'date' => $today, 'payment_type' => 'cash', 'cash_account_id' => null, 'subtotal' => 500, 'discount' => 0, 'tax' => 0, 'total' => 500, 'status' => 'pending', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invB, 'product_id' => $product, 'qty' => 50, 'unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 500, 'net_before_tax' => 500, 'cost_price' => 1.5, 'cost_total' => 75, 'is_bonus' => 0]);
Posting::postSalesInvoice($invB);

// 4. Test the same assertions
$results = [];
function ok($label, $cond) { global $results; $results[] = [$label, $cond ? 'PASS' : 'FAIL']; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }
$balFact = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id=$whMain")->fetchColumn();
$balA    = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id={$smA['warehouse_id']}")->fetchColumn();
$balB    = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id={$smB['warehouse_id']}")->fetchColumn();
$company = $balFact + (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id=$product AND warehouse_id IN (SELECT id FROM warehouses WHERE type='salesman')")->fetchColumn();
ok('factory stock = 500', abs($balFact - 500) < 0.0001);
ok('van A = 200', abs($balA - 200) < 0.0001);
ok('van B = 150', abs($balB - 150) < 0.0001);
ok('combined = 850', abs($company - 850) < 0.0001);

$jeA = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'sales_invoice' AND reference_id = $invA")->fetchColumn();
$jeB = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'sales_invoice' AND reference_id = $invB")->fetchColumn();
echo "invoice A id=$invA posted journal count=$jeA ; invoice B id=$invB posted journal count=$jeB\n";
ok('invoice A posted exactly once', $jeA === 1);
ok('invoice B posted exactly once', $jeB === 1);
$statusA = $pdo->query("SELECT status FROM sales_invoices WHERE id = $invA")->fetchColumn();
$statusB = $pdo->query("SELECT status FROM sales_invoices WHERE id = $invB")->fetchColumn();
ok('A is posted', $statusA === 'posted');
ok('B is posted', $statusB === 'posted');

// production_in movements exactly once per ref
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE type = 'opening' AND product_id = $product")->fetchColumn();
ok('opening movement written exactly once', $prodCount === 1);

// van movements exactly once per side
$issueA_a = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type IS NULL AND type='salesman_issue_out' AND warehouse_id=$whMain AND product_id=$product")->fetchColumn();
ok('factory issued out exactly once', $issueA_a === 2); // 300 + 200 = 2 movements
$issueA_b = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE type='salesman_issue_in' AND product_id=$product")->fetchColumn();
ok('van receipts exactly once', $issueA_b === 2);

$fail = count(array_filter($results, fn($r) => $r[1] === 'FAIL'));
echo "\n", count($results), " checks, $fail failed\n";
exit($fail ? 1 : 0);
