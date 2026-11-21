<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/StockController.php';
require_once __DIR__ . '/../app/controllers/PurchaseController.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

$pass = 0; $fail = 0;
function ok($l) { global $pass; echo "✅ $l\n"; $pass++; }
function no($l, $d='') { global $fail; echo "❌ $l" . ($d ? " — $d" : '') . "\n"; $fail++; }

echo "=== E2E Test: Warehouse Stock Tab & Supplier Product Mapping ===\n\n";

// 1. Check supplier_products table
$cols = db_all("PRAGMA table_info(supplier_products)");
if (!empty($cols)) {
    ok("supplier_products table exists with " . count($cols) . " columns");
} else {
    no("supplier_products table missing");
}

// 2. Prepare test products & supplier
$p1 = db_one("SELECT id, name FROM products WHERE kind = 'raw' AND is_active = 1 LIMIT 1");
$p2 = db_one("SELECT id, name FROM products WHERE kind = 'raw' AND is_active = 1 AND id != ? LIMIT 1", [$p1['id']]);

$supId = (int)db_val("SELECT id FROM suppliers WHERE is_active = 1 LIMIT 1");
db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$supId, $p1['id']]);

$mapped = db_all("SELECT product_id FROM supplier_products WHERE supplier_id = ?", [$supId]);
$mappedIds = array_map('intval', array_column($mapped, 'product_id'));
if (in_array((int)$p1['id'], $mappedIds)) {
    ok("Supplier #$supId product mapping verified in DB (includes Product #{$p1['id']})");
} else {
    no("Supplier product mapping failed");
}

// 3. Test View: Warehouse Tab
$_GET['tab'] = 'warehouse';
$tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));
$rawStockRows = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level,
                               COALESCE(x.qty,0) AS qty,
                               COALESCE(x.value,0) AS value,
                               (SELECT COALESCE(SUM(COALESCE(ii.qty_kg, ii.qty)),0) FROM purchase_items ii JOIN purchase_invoices i ON i.id = ii.invoice_id WHERE ii.product_id = p.id AND i.status = 'posted') AS purchased,
                               ((SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fii.product_id = p.id AND fi.status = 'posted') + (SELECT COALESCE(SUM(rdu.qty_kg),0) FROM rm_daily_usage rdu WHERE rdu.product_id = p.id AND rdu.status = 'posted')) AS used
                        FROM products p
                        LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value
                                   FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type != 'salesman'
                                   GROUP BY sb.product_id) x ON x.product_id = p.id
                        WHERE p.kind IN ('raw','packaging') AND p.is_active = 1
                        ORDER BY p.name");
$totStockQtyKg = 0.0; $totStockValue = 0.0;
foreach ($rawStockRows as &$sr) {
    $sr['qty'] = (float)$sr['qty'];
    $sr['value'] = (float)$sr['value'];
    $sr['purchased'] = (float)$sr['purchased'];
    $sr['used'] = (float)$sr['used'];
    $totStockQtyKg += $sr['qty'];
    $totStockValue += $sr['value'];
}
unset($sr);

ob_start();
view('purchase/raw', [
    'p'             => ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1],
    'allRows'       => [],
    'rawStockRows'  => $rawStockRows,
    'totStockQtyKg' => $totStockQtyKg,
    'totStockValue' => $totStockValue,
    'suppliersList' => [],
    'rawProductsAll'=> [],
    'q'             => '',
    'from'          => date('Y-m-01'),
    'to'            => today(),
    'tab'           => 'warehouse',
    'totQtyKg'      => 0,
    'totValue'      => 0,
    'totPaid'       => 0,
    'tonUnit'       => $tonUnit,
    'title'         => 'Raw Material Purchases',
]);
$htmlWarehouse = ob_get_clean();

strpos($htmlWarehouse, 'Current Warehouse Stock') !== false ? ok("Warehouse tab renders 'Current Warehouse Stock'") : no("Missing Current Warehouse Stock");
strpos($htmlWarehouse, 'On Hand Stock (KG)') !== false ? ok("Warehouse tab has 'On Hand Stock (KG)' column") : no("Missing On Hand Stock (KG)");
strpos($htmlWarehouse, 'On Hand Stock (TON)') !== false ? ok("Warehouse tab has 'On Hand Stock (TON)' column") : no("Missing On Hand Stock (TON)");
strpos($htmlWarehouse, 'Current Stock Value') !== false ? ok("Warehouse tab has 'Current Stock Value' column") : no("Missing Current Stock Value");

// 4. Test View: Suppliers Tab
$suppliersList = db_all("SELECT s.*,
                               COUNT(DISTINCT sp.product_id) AS prod_count,
                               GROUP_CONCAT(p.name, '||') AS product_names,
                               GROUP_CONCAT(p.id, ',') AS product_ids
                        FROM suppliers s
                        LEFT JOIN supplier_products sp ON sp.supplier_id = s.id
                        LEFT JOIN products p ON p.id = sp.product_id AND p.is_active = 1
                        WHERE s.is_active = 1
                        GROUP BY s.id
                        ORDER BY s.name");
$rawProductsAll = db_all("SELECT id, sku, name, kind FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 ORDER BY name");

ob_start();
view('purchase/raw', [
    'p'             => ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1],
    'allRows'       => [],
    'rawStockRows'  => [],
    'totStockQtyKg' => 0,
    'totStockValue' => 0,
    'suppliersList' => $suppliersList,
    'rawProductsAll'=> $rawProductsAll,
    'q'             => '',
    'from'          => date('Y-m-01'),
    'to'            => today(),
    'tab'           => 'suppliers',
    'totQtyKg'      => 0,
    'totValue'      => 0,
    'totPaid'       => 0,
    'tonUnit'       => $tonUnit,
    'title'         => 'Raw Material Purchases',
]);
$htmlSuppliers = ob_get_clean();

strpos($htmlSuppliers, 'Add New Supplier') !== false ? ok("Suppliers tab has 'Add New Supplier' form") : no("Missing Add New Supplier");
strpos($htmlSuppliers, 'Add New Raw Material Product') !== false ? ok("Suppliers tab has 'Add New Raw Material Product' form") : no("Missing Add New Raw Material Product");
strpos($htmlSuppliers, 'Suppliers & Assigned Raw Materials List') !== false ? ok("Suppliers tab renders supplier list with mapped items") : no("Missing Suppliers list");

// 5. Test View: New Purchase with Supplier-filtered dropdown
$suppMapRaw = db_all("SELECT supplier_id, product_id FROM supplier_products");
$suppMap = [];
foreach ($suppMapRaw as $smr) {
    $suppMap[(int)$smr['supplier_id']][] = (int)$smr['product_id'];
}

ob_start();
$_GET['raw'] = 1;
view('purchase/invoice_new', [
    'suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
    'warehouses' => StockController::warehouseOptions(),
    'products' => '[]',
    'rawProducts' => $rawProductsAll,
    'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"),
    'supplierProductsMap' => json_encode($suppMap),
    'pendingPos' => [],
    'po' => null, 'poItems' => [],
    'title' => 'Receive Goods (GRN) / Purchase Invoice'
]);
$htmlNewPurchase = ob_get_clean();

strpos($htmlNewPurchase, 'id="selSupplier"') !== false ? ok("New Purchase has id='selSupplier'") : no("Missing id='selSupplier'");
strpos($htmlNewPurchase, 'supplierProductsMap') !== false ? ok("New Purchase includes supplierProductsMap JavaScript") : no("Missing supplierProductsMap");
strpos($htmlNewPurchase, 'filterAllRows') !== false ? ok("New Purchase has dynamic filtering JavaScript function") : no("Missing filterAllRows");
strpos($htmlNewPurchase, 'suppProdCountHint') !== false ? ok("New Purchase has supplier product count badge/hint") : no("Missing suppProdCountHint");

// 6. Test actual Purchase posting & Warehouse stock reception
$wh = db_one("SELECT id FROM warehouses WHERE type <> 'salesman' ORDER BY id ASC LIMIT 1");
$acc = db_one("SELECT id FROM cash_bank_accounts WHERE is_active = 1 LIMIT 1");

$testQtyKg = 500.0;
$testRate = 220.0;
$testLineTotal = $testQtyKg * $testRate; // 110,000
$testPaid = 60000.0;

$stockBefore = (float)db_val("SELECT COALESCE(SUM(qty), 0) FROM stock_balances WHERE product_id = ?", [$p1['id']]);

$invId = 0;
Database::transaction(function() use (&$invId, $supId, $wh, $acc, $p1, $testQtyKg, $testRate, $testLineTotal, $testPaid) {
    $date = today();
    $invId = db_insert('purchase_invoices', [
        'invoice_no' => 'TMP-TEST-' . time(),
        'supplier_id' => $supId,
        'warehouse_id' => $wh['id'],
        'date' => $date,
        'subtotal' => $testLineTotal,
        'tax' => 0.0,
        'discount' => 0.0,
        'total' => $testLineTotal,
        'amount_paid' => $testPaid,
        'payment_type' => ($testPaid >= $testLineTotal) ? 'cash' : (($testPaid > 0) ? 'partial' : 'credit'),
        'payment_status' => ($testPaid >= $testLineTotal) ? 'paid' : (($testPaid > 0) ? 'partial' : 'unpaid'),
        'status' => 'posted',
        'cash_account_id' => $acc['id'],
        'created_by' => 1,
        'created_at' => now_local(),
    ]);
    
    $invNo = 'GRN-TEST-' . str_pad((string)$invId, 5, '0', STR_PAD_LEFT);
    db_exec("UPDATE purchase_invoices SET invoice_no = ? WHERE id = ?", [$invNo, $invId]);
    
    db_insert('purchase_items', [
        'invoice_id' => $invId,
        'product_id' => $p1['id'],
        'unit_short' => 'KG',
        'qty' => $testQtyKg,
        'qty_kg' => $testQtyKg,
        'unit_cost' => $testRate,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'discount' => 0,
        'line_total' => $testLineTotal,
        'is_bonus' => 0,
    ]);
    
    // Post inventory and journal
    Posting::postPurchaseInvoice($invId);
});

$stockAfter = (float)db_val("SELECT COALESCE(SUM(qty), 0) FROM stock_balances WHERE product_id = ?", [$p1['id']]);
$stockDiff = $stockAfter - $stockBefore;

if (abs($stockDiff - $testQtyKg) < 0.01) {
    ok("Warehouse stock received: $stockBefore KG -> $stockAfter KG (+$stockDiff KG matches purchase)");
} else {
    no("Warehouse stock update mismatch: expected +$testQtyKg KG, got +$stockDiff KG");
}

echo "\n============================================\n";
echo "SUMMARY: Passes: $pass, Failures: $fail\n";
echo "============================================\n";

if ($fail > 0) exit(1);
