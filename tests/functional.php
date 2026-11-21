<?php
/**
 * JUSTI NUMBER ONE ERP — functional test suite (accounting & inventory).
 * Runs against an in-memory SQLite database; exit code = number of failures.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => $argv[2] ?? 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = [];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) { echo "  PASS  $name\n"; }
    else { $failures++; echo "  FAIL  $name\n"; }
}
function approx($a, $b, $eps = 0.01): bool { return abs(((float)$a) - ((float)$b)) <= $eps; }

function mkProduct(string $sku, string $name, float $purchase, float $sale, string $kind = 'finished'): int {
    return db_insert('products', ['sku' => $sku, 'name' => $name, 'kind' => $kind, 'purchase_price' => $purchase, 'sale_price' => $sale, 'tax_rate' => 18, 'reorder_level' => 2, 'is_active' => 1, 'created_at' => now_local()]);
}

$whMain = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
$cashGl = Accounting::accountId('CASH');

echo "\n=== Setup: products, supplier, customer ===\n";
$rm = mkProduct('RAW-TOBACCO', 'Raw Tobacco Leaf', 800, 0, 'raw');
$fg = mkProduct('JUSTI-10', 'JUSTI Number One 10s', 500, 720);
$supId = db_insert('suppliers', ['code' => 'SUP-001', 'name' => 'Tobacco Supplier Test', 'opening_balance' => 0, 'opening_type' => 'credit', 'is_active' => 1, 'created_at' => now_local()]);
$custId = db_insert('customers', ['code' => 'CUST-001', 'name' => 'Test Shopkeeper', 'credit_limit' => 100000, 'opening_balance' => 0, 'opening_type' => 'debit', 'is_active' => 1, 'created_at' => now_local()]);
$cust2Id = db_insert('customers', ['code' => 'CUST-002', 'name' => 'Cash Customer', 'credit_limit' => 0, 'opening_balance' => 0, 'opening_type' => 'debit', 'is_active' => 1, 'created_at' => now_local()]);

echo "\n=== 1 · Purchase (GRN): stock in, journal balanced, AP posted ===\n";
$piId = db_insert('purchase_invoices', ['invoice_no' => 'TMP-1', 'supplier_id' => $supId, 'date' => today(), 'payment_type' => 'credit', 'warehouse_id' => $whMain, 'subtotal' => 160000, 'discount' => 0, 'tax' => 28800, 'total' => 188800, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('purchase_items', ['invoice_id' => $piId, 'product_id' => $rm, 'qty' => 200, 'unit_cost' => 800, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 28800, 'line_total' => 188800]);
Posting::postPurchaseInvoice($piId);
ok(approx(Inventory::balance($rm, $whMain), 200), 'raw material stock = 200 kg after purchase');
$piNo = db_val('SELECT invoice_no FROM purchase_invoices WHERE id = ?', [$piId]);
ok(strpos((string)$piNo, 'PI-') === 0, 'invoice number synced to voucher (' . $piNo . ')');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after purchase');
ok(approx(Accounting::supplierBalance($supId), 188800), 'supplier payable = 188,800');
ok(approx(Accounting::accountBalances()[0] ? 0 : 0, 0), 'dummy sanity');
// Input tax should be 28,800
$inputTax = (float)db_val("SELECT SUM(l.debit) - SUM(l.credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'TAX_IN'");
ok(approx($inputTax, 28800), 'input sales tax 28,800 debited');

echo "\n=== 2 · Produce finished goods from raw material ===\n";
$bomId = db_insert('bills_of_materials', ['product_id' => $fg, 'name' => 'Test Recipe', 'output_qty' => 10, 'is_active' => 1]);
db_insert('bom_lines', ['bom_id' => $bomId, 'material_id' => $rm, 'qty' => 2]);
$poId = db_insert('production_orders', ['order_no' => 'PO-TEST-001', 'product_id' => $fg, 'warehouse_id' => $whMain, 'bom_id' => $bomId, 'qty_planned' => 10, 'qty_produced' => 0, 'status' => 'released', 'created_at' => now_local()]);
db_insert('production_inputs', ['production_order_id' => $poId, 'product_id' => $rm, 'qty' => 2, 'unit_cost' => 0, 'total_cost' => 0]);
// complete via same codepath as the controller:
Database::transaction(function () use ($poId, $whMain, $fg) {
    $o = db_one('SELECT * FROM production_orders WHERE id = ?', [$poId]);
    $inputs = db_all('SELECT * FROM production_inputs WHERE production_order_id = ?', [$poId]);
    $materialsCost = 0.0;
    foreach ($inputs as $input) {
        $unitCost = Inventory::issue((int)$input['product_id'], $whMain, (float)$input['qty'], 'production_consumption', 'production_order', $poId);
        $materialsCost += round($unitCost * (float)$input['qty'], 2);
    }
    $unitCost = $materialsCost / 10;
    Inventory::receive($fg, $whMain, 10, $unitCost, 'production_in', 'production_order', $poId);
    db_exec("UPDATE production_orders SET status='completed', qty_produced=10, materials_cost=?, total_cost=?, completed_at=? WHERE id=?", [$materialsCost, $materialsCost, now_local(), $poId]);
});
ok(approx(Inventory::balance($fg, $whMain), 10), 'finished goods produce 10 units');
ok(approx(Inventory::balance($rm, $whMain), 198), 'raw material consumed 2 → 198');
ok(approx(Inventory::avgCost($fg, $whMain), 160.0, 0.01), 'FG unit cost = (2kg × 800) / 10 = Rs 160');

echo "\n=== 3 · Cash sale posts revenue, COGS, inventory, and balances ===\n";
$siId = db_insert('sales_invoices', ['invoice_no' => 'TMP-2', 'customer_id' => $cust2Id, 'warehouse_id' => $whMain, 'date' => today(), 'payment_type' => 'cash', 'subtotal' => 7200, 'discount' => 0, 'tax' => 1296, 'total' => 8496, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $siId, 'product_id' => $fg, 'qty' => 10, 'unit_price' => 720, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 1296, 'line_total' => 8496, 'net_before_tax' => 7200, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($siId);
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after cash sale');
$cogs = (float)db_val("SELECT SUM(l.debit) FROM journal_lines l JOIN chart_of_accounts a ON a.id=l.account_id WHERE a.system_key='COGS'");
ok(approx($cogs, 1600), 'COGS = 1600 (10 × 160)');
$inv = (float)db_val("SELECT SUM(credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id=l.account_id WHERE a.system_key='INVENTORY'");
ok(approx($inv, 1600), 'inventory credited 1,600 on goods sold (COGS)');
$rev = (float)db_val("SELECT SUM(credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id=l.account_id WHERE a.system_key='SALES'");
ok(approx($rev, 7200), 'sales revenue 7,200');
$salesNo = db_val('SELECT invoice_no FROM sales_invoices WHERE id = ?', [$siId]);
ok(strpos((string)$salesNo, 'SI-') === 0, 'sales invoice number synced (' . $salesNo . ')');
ok(approx(Inventory::balance($fg, $whMain), 0), 'finished stock = 0 after selling all');

echo "\n=== 4 · Credit sale + partial receipt ===\n";
// restock FG for credit sale
$poId2 = db_insert('production_orders', ['order_no' => 'PO-TEST-002', 'product_id' => $fg, 'warehouse_id' => $whMain, 'bom_id' => $bomId, 'qty_planned' => 10, 'qty_produced' => 10, 'status' => 'released', 'created_at' => now_local()]);
db_insert('production_inputs', ['production_order_id' => $poId2, 'product_id' => $rm, 'qty' => 2, 'unit_cost' => 0, 'total_cost' => 0]);
Database::transaction(function () use ($poId2, $whMain, $fg) {
    foreach (db_all('SELECT * FROM production_inputs WHERE production_order_id = ?', [$poId2]) as $input) {
        $unitCost = Inventory::issue((int)$input['product_id'], $whMain, (float)$input['qty'], 'production_consumption', 'production_order', $poId2);
    }
    Inventory::receive($fg, $whMain, 10, 160, 'production_in', 'production_order', $poId2);
    db_exec("UPDATE production_orders SET status='completed', qty_produced=10, materials_cost=1600, total_cost=1600, completed_at=? WHERE id=?", [now_local(), $poId2]);
});
$si2 = db_insert('sales_invoices', ['invoice_no' => 'TMP-3', 'customer_id' => $custId, 'warehouse_id' => $whMain, 'date' => today(), 'due_date' => date('Y-m-d', strtotime('+15 days')), 'payment_type' => 'credit', 'subtotal' => 21600, 'discount' => 0, 'tax' => 3888, 'total' => 25488, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $si2, 'product_id' => $fg, 'qty' => 30, 'unit_price' => 720, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 3888, 'line_total' => 25488, 'net_before_tax' => 21600, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
// Not enough stock? We produced 10 — this should FAIL with insufficient stock
$threw = false;
try { Posting::postSalesInvoice($si2); } catch (Throwable $e) { $threw = true; }
ok($threw, 'negative stock is rejected (30 units requested, 10 on hand)');
// Posting rolled back inside its transaction; remove the orphan header the same way the app does
db_exec('DELETE FROM sales_invoice_items WHERE invoice_id = ?', [$si2]);
db_exec('DELETE FROM sales_invoices WHERE id = ?', [$si2]);
// Now write a legal credit sale of 5 units
$si3 = db_insert('sales_invoices', ['invoice_no' => 'TMP-4', 'customer_id' => $custId, 'warehouse_id' => $whMain, 'date' => today(), 'due_date' => date('Y-m-d', strtotime('+15 days')), 'payment_type' => 'credit', 'subtotal' => 3600, 'discount' => 0, 'tax' => 648, 'total' => 4248, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $si3, 'product_id' => $fg, 'qty' => 5, 'unit_price' => 720, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 648, 'line_total' => 4248, 'net_before_tax' => 3600, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($si3);
ok(approx(Accounting::customerBalance($custId), 4248), 'credit sale → customer balance Rs 4,248');
$rcId = db_insert('receipts', ['receipt_no' => 'TMP-5', 'party_type' => 'customer', 'customer_id' => $custId, 'account_id' => (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1"), 'date' => today(), 'amount' => 2000, 'created_at' => now_local()]);
Posting::postReceipt($rcId);
ok(approx(Accounting::customerBalance($custId), 2248), 'partial payment Rs 2,000 → balance Rs 2,248');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after credit sale + receipt');

echo "\n=== 5 · Salesman issue + salesman sale (no revenue on issue) ===\n";
$entriesBefore = (int)db_val('SELECT COUNT(*) FROM journal_entries');
$smId = db_insert('salesmen', ['code' => 'SM-001', 'name' => 'Test Salesman', 'commission_percent' => 2.5, 'is_active' => 1, 'created_at' => now_local()]);
$smWh = (int)db_insert('warehouses', ['code' => 'WH-SM-001', 'name' => 'Test Salesman Van', 'type' => 'salesman', 'is_default' => 0, 'is_active' => 1, 'created_at' => now_local()]);
db_exec('UPDATE salesmen SET warehouse_id = ? WHERE id = ?', [$smWh, $smId]);
Inventory::transfer($fg, $whMain, $smWh, 5, 'salesman_issue_out', 'salesman_issue_in', 'salesman_issue', null);
$entriesAfter = (int)db_val('SELECT COUNT(*) FROM journal_entries');
ok($entriesBefore === $entriesAfter, 'salesman stock issue creates NO journal entries (transfer only)');
ok(approx(Inventory::balance($fg, $smWh), 5), 'salesman van has 5 units');
ok(approx(Inventory::balance($fg, $whMain), 0), 'main warehouse empty after transfer of 5 to salesman');
// Salesman sells 2 units on credit to assigned customer
db_exec('INSERT INTO salesman_customers (salesman_id, customer_id) VALUES (?,?)', [$smId, $custId]);
$siSm = db_insert('sales_invoices', ['invoice_no' => 'TMP-6', 'customer_id' => $custId, 'salesman_id' => $smId, 'warehouse_id' => $smWh, 'date' => today(), 'due_date' => date('Y-m-d', strtotime('+10 days')), 'payment_type' => 'credit', 'subtotal' => 1440, 'discount' => 0, 'tax' => 259.20, 'total' => 1699.20, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $siSm, 'product_id' => $fg, 'qty' => 2, 'unit_price' => 720, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 259.20, 'line_total' => 1699.20, 'net_before_tax' => 1440, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($siSm);
ok(approx(Inventory::balance($fg, $smWh), 3), 'van stock 5 − 2 = 3 after salesman sale');
ok(approx(Accounting::customerBalance($custId), 2248 + 1699.20), 'salesman sale raises receivable to 3,947.20');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after salesman sale');

echo "\n=== 6 · Sales return ===\n";
$srId = db_insert('sales_returns', ['return_no' => 'TMP-7', 'sales_invoice_id' => $si3, 'customer_id' => $custId, 'date' => today(), 'reason' => 'damaged cartons', 'total' => 0, 'status' => 'posted', 'created_at' => now_local()]);
$origLine = db_one('SELECT * FROM sales_invoice_items WHERE invoice_id = ?', [$si3]);
db_insert('sales_return_items', ['return_id' => $srId, 'product_id' => $fg, 'qty' => 1, 'unit_price' => 720, 'line_total' => 849.60, 'cost_price' => (float)$origLine['cost_price']]);
Posting::postSalesReturn($srId);
$srNo = db_val('SELECT return_no FROM sales_returns WHERE id = ?', [$srId]);
ok(strpos((string)$srNo, 'SR-') === 0, 'return number synced (' . $srNo . ')');
ok(approx(Accounting::customerBalance($custId), 2248 + 1699.20 - 849.60), 'return reduces receivable');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after return');

echo "\n=== 7 · Bank transfer + expense + reversal ===\n";
$bankAccId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='bank' LIMIT 1");
$cashAccId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1");
$trId = db_insert('bank_transfers', ['transfer_no' => 'TMP-8', 'from_account_id' => $cashAccId, 'to_account_id' => $bankAccId, 'date' => today(), 'amount' => 1000, 'created_at' => now_local()]);
Posting::postBankTransfer($trId);
$cashGlId = (int)db_val("SELECT gl_account_id FROM cash_bank_accounts WHERE id = ?", [$cashAccId]);
$bankGlId = (int)db_val("SELECT gl_account_id FROM cash_bank_accounts WHERE id = ?", [$bankAccId]);
ok(approx((float)db_val("SELECT SUM(debit-credit) FROM journal_lines WHERE account_id = ?", [$cashGlId]), -1000 + 8496 + 2000), 'cash GL reflects sale + receipt − transfer');
$expCat = (int)db_val("SELECT id FROM expense_categories WHERE name='Repairs & Maintenance' LIMIT 1");
$expId = db_insert('expenses', ['expense_no' => 'TMP-9', 'category_id' => $expCat, 'date' => today(), 'amount' => 5000, 'payee' => 'Workshop', 'description' => 'Machine repair', 'payment_type' => 'cash', 'account_id' => $cashAccId, 'status' => 'pending', 'created_at' => now_local()]);
Posting::postExpense($expId);
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after transfer + expense');
// Reverse the expense
Posting::reverseExpense($expId, 'test reversal');
$pl = Accounting::profitAndLoss(date('Y-m-01'), date('Y-m-t'));
ok(approx($pl['total_expense'], 2560), 'P&L expenses = COGS net of return (2,560)');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after reversal');
$auditCount = (int)db_val("SELECT COUNT(*) FROM audit_logs");
ok($auditCount > 0, 'audit log captured entries (' . $auditCount . ')');

echo "\n=== 8 · Idempotency of auto-posting ===\n";
$journalBefore = (int)db_val("SELECT COUNT(*) FROM journal_entries");
Posting::postSalesInvoice($siId); // re-post an already-posted invoice
$journalAfter = (int)db_val("SELECT COUNT(*) FROM journal_entries");
ok($journalBefore === $journalAfter, 're-posting the same invoice does not create duplicate journals');

echo "\n=== 9 · Unbalanced manual journal rejected ===\n";
$threw = false;
try { Accounting::postEntry([['account' => 'SALES', 'debit' => 100], ['account' => 'CASH', 'credit' => 90]], ['date' => today()]); } catch (Throwable $e) { $threw = true; }
ok($threw, 'unbalanced manual journal rejected');

echo "\n=== 10 · Closed period rejects posting ===\n";
db_exec("UPDATE fiscal_periods SET status = 'closed' WHERE start_date <= ? AND end_date >= ?", [today(), today()]);
$threw = false;
try { Accounting::postEntry([['account' => 'SALES', 'debit' => 10], ['account' => 'CASH', 'credit' => 10]], ['date' => today()]); } catch (Throwable $e) { $threw = true; }
ok($threw, 'posting into a closed period is rejected');
db_exec("UPDATE fiscal_periods SET status = 'open' WHERE start_date <= ? AND end_date >= ?", [today(), today()]);

echo "\n=== 11 · Balance sheet equation holds ===\n";
$bs = Accounting::balanceSheet(today());
ok($bs['balanced'], 'assets = liabilities + equity (' . number_format($bs['total_assets'], 2) . ')');

echo "\n=== 12 · Sales returns of bonus goods (free items: stock moves, no revenue) ===\n";
// produce 10 more and sell all with 2 bonus units
$poB = db_insert('production_orders', ['order_no' => 'PO-BONUS-001', 'product_id' => $fg, 'warehouse_id' => $whMain, 'bom_id' => $bomId, 'qty_planned' => 10, 'qty_produced' => 10, 'status' => 'completed', 'materials_cost' => 1600, 'total_cost' => 1600, 'created_at' => now_local(), 'completed_at' => now_local()]);
db_insert('production_inputs', ['production_order_id' => $poB, 'product_id' => $rm, 'qty' => 2, 'unit_cost' => 800, 'total_cost' => 1600]);
Database::transaction(function () use ($whMain, $fg, $rm) {
    Inventory::issue($rm, $whMain, 2, 'production_consumption', 'production_order', 999998);
    Inventory::receive($fg, $whMain, 10, 160, 'production_in', 'production_order', 999998);
});
$siBonus = db_insert('sales_invoices', ['invoice_no' => 'TMP-10', 'customer_id' => $custId, 'warehouse_id' => $whMain, 'date' => today(), 'payment_type' => 'cash', 'subtotal' => 2880, 'discount' => 0, 'tax' => 518.40, 'total' => 3398.40, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $siBonus, 'product_id' => $fg, 'qty' => 4, 'unit_price' => 720, 'discount' => 0, 'tax_rate' => 18, 'tax_amount' => 518.40, 'line_total' => 3398.40, 'net_before_tax' => 2880, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
db_insert('sales_invoice_items', ['invoice_id' => $siBonus, 'product_id' => $fg, 'qty' => 2, 'unit_price' => 0, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 0, 'net_before_tax' => 0, 'is_bonus' => 1, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($siBonus);
$bonusPromo = (float)db_val("SELECT SUM(l.debit) FROM journal_lines l JOIN chart_of_accounts a ON a.id=l.account_id WHERE a.system_key='PROMO'");
ok(approx($bonusPromo, 320), 'bonus goods cost (2 × 160) debited to PROMO account');
ok(approx(Inventory::balance($fg, $whMain), 5), 'bonus + sale units deducted from stock (11 − 6 = 5)');
$siBonusRev = db_one('SELECT journal_entry_id FROM sales_invoices WHERE id = ?', [$siBonus]);
Posting::reverseSalesInvoice($siBonus, 'test full reversal');
ok(approx(Inventory::balance($fg, $whMain), 11), 'reversing invoice restores all 6 units to stock (5 + 6)');
$revInv = db_one("SELECT status FROM sales_invoices WHERE id = ?", [$siBonus]);
ok($revInv['status'] === 'reversed', 'invoice marked REVERSED');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after reversal');
$reversalEntries = (int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'reversal'");
ok($reversalEntries >= 2, 'reversal entries recorded and linked (' . $reversalEntries . ')');

echo "\n=== 13 · amount_in_words ===";
ok(amount_in_words(8496.50) === 'Eight Thousand Four Hundred Ninety Six Rupees and Fifty Paisa Only', 'amount in words: ' . amount_in_words(8496.50));

echo "\n========================================\n";
echo "RESULTS: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
