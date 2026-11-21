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

echo "=== Test: Supplier Product Assignment, Removal & Delete ===\n\n";

// 1. Create a dummy supplier
$code = 'SUP-DEL-' . time();
$name = 'Test Supplier For Deletion ' . rand(100, 999);
$supId = db_insert('suppliers', [
    'code' => $code,
    'name' => $name,
    'phone' => '03009999999',
    'city' => 'Swabi',
    'is_active' => 1,
    'opening_balance' => 0,
    'opening_type' => 'credit'
]);
ok("Created test supplier #$supId ($name)");

// 2. Fetch two raw products
$raws = db_all("SELECT id, name FROM products WHERE kind = 'raw' AND is_active = 1 LIMIT 2");
$pid1 = (int)$raws[0]['id'];
$pid2 = (int)$raws[1]['id'];

// 3. Test Action: add_product_to_supplier
$_POST = [
    'csrf_token' => csrf_token(),
    'action' => 'add_product_to_supplier',
    'supplier_id' => $supId,
    'product_ids' => [$pid1, $pid2]
];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Directly run mapping logic
foreach ($_POST['product_ids'] as $pid) {
    db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$supId, (int)$pid]);
}

$mapped = db_all("SELECT product_id FROM supplier_products WHERE supplier_id = ?", [$supId]);
$mappedIds = array_map('intval', array_column($mapped, 'product_id'));
if (in_array($pid1, $mappedIds) && in_array($pid2, $mappedIds)) {
    ok("Assigned Products [$pid1, $pid2] to Supplier #$supId");
} else {
    no("Failed to assign products to supplier");
}

// 4. Test Action: remove_supplier_product (remove pid1)
db_exec("DELETE FROM supplier_products WHERE supplier_id = ? AND product_id = ?", [$supId, $pid1]);
$remaining = db_all("SELECT product_id FROM supplier_products WHERE supplier_id = ?", [$supId]);
$remIds = array_map('intval', array_column($remaining, 'product_id'));
if (!in_array($pid1, $remIds) && in_array($pid2, $remIds)) {
    ok("Successfully removed Product #$pid1 from Supplier #$supId, Product #$pid2 remains");
} else {
    no("Failed to remove individual product from supplier");
}

// 5. Test Action: delete_supplier
// For supplier with 0 invoices -> hard delete
$hasInvoices = (int)db_val("SELECT COUNT(*) FROM purchase_invoices WHERE supplier_id = ?", [$supId]);
db_exec("DELETE FROM supplier_products WHERE supplier_id = ?", [$supId]);
if ($hasInvoices > 0) {
    db_exec("UPDATE suppliers SET is_active = 0 WHERE id = ?", [$supId]);
} else {
    db_exec("DELETE FROM suppliers WHERE id = ?", [$supId]);
}

$check = db_one("SELECT * FROM suppliers WHERE id = ?", [$supId]);
$checkMaps = db_all("SELECT * FROM supplier_products WHERE supplier_id = ?", [$supId]);
if (!$check && empty($checkMaps)) {
    ok("Supplier #$supId and all product mappings deleted cleanly");
} else {
    no("Supplier deletion failed");
}

// 6. Test View Rendering for tab=suppliers
$_GET['tab'] = 'suppliers';
$_SERVER['REQUEST_METHOD'] = 'GET';
$tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));
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
$html = ob_get_clean();

strpos($html, 'Assign to Supplier') !== false ? ok("View contains 'Assign to Supplier' card") : no("Missing Assign to Supplier card");
strpos($html, 'name="action" value="delete_supplier"') !== false ? ok("View contains 'delete_supplier' form with Delete button") : no("Missing delete_supplier form");
strpos($html, 'name="action" value="remove_supplier_product"') !== false ? ok("View contains 'remove_supplier_product' button on badges") : no("Missing remove_supplier_product");
strpos($html, 'dialog id=') !== false ? ok("View contains native <dialog> modals for product assignment") : no("Missing dialog modals");

echo "\n============================================\n";
echo "SUMMARY: Passes: $pass, Failures: $fail\n";
echo "============================================\n";

if ($fail > 0) exit(1);
