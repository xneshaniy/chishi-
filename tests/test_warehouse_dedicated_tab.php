<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/StockController.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

$pass = 0; $fail = 0;
function ok($l) { global $pass; echo "✅ $l\n"; $pass++; }
function no($l, $d='') { global $fail; echo "❌ $l" . ($d ? " — $d" : '') . "\n"; $fail++; }

echo "=== Test: Dedicated Warehouse Tab (Pure Physical Stock - No Money/Payments) ===\n\n";

// 1. Verify StockController::warehouseStock method runs without errors
ob_start();
$_GET = ['r' => 'warehouse', 'q' => ''];
StockController::warehouseStock();
$output = ob_get_clean();

strpos($output, 'Warehouse Stock') !== false ? ok("Page header contains 'Warehouse Stock'") : no("Missing header");
strpos($output, 'Raw Materials Stock') !== false ? ok("Stat chip 1: Raw Materials Stock present") : no("Missing raw stat chip");
strpos($output, 'Factory Products In Stock (موجودہ فیکٹری اسٹاک)') !== false ? ok("Stat chip 2: Factory Products In Stock present") : no("Missing factory products stat chip");
strpos($output, 'Total Tracked Products (کل اقسام)') !== false ? ok("Stat chip 3: Total Tracked Products present") : no("Missing stat chip 3");

// Verify that all prices, payments, and valuations are REMOVED
strpos($output, '1,067,195') === false ? ok("Total valuation '1,067,195' is completely removed") : no("1,067,195 still present");
strpos($output, 'Stock Value (مالیت)') === false ? ok("'Stock Value (مالیت)' columns completely removed") : no("Stock Value column still present");
strpos($output, 'Total Warehouse Value') === false ? ok("'Total Warehouse Value' chip completely removed") : no("Total Warehouse Value still present");

// Section 1: Raw Materials
strpos($output, '1. Raw Material Stock') !== false ? ok("Section 1: Raw Material Stock present") : no("Missing Section 1");
(strpos($output, 'Raw Material Name') !== false || strpos($output, 'Product') !== false) ? ok("Raw materials table header present") : no("Missing raw table header");
strpos($output, 'Quantity (KG)') !== false ? ok("Column Quantity (KG) present") : no("Missing Quantity (KG)");
strpos($output, 'Quantity (TON)') !== false ? ok("Column Quantity (TON) present") : no("Missing Quantity (TON)");

// Section 2: Factory Finished Products
strpos($output, '2. Factory Finished Products') !== false ? ok("Section 2: Factory Finished Products present") : no("Missing Section 2");
strpos($output, 'Product Name') !== false ? ok("Factory products table header present") : no("Missing factory products table header");
strpos($output, 'In Stock Quantity') !== false ? ok("Column In Stock Quantity present") : no("Missing In Stock Quantity");
strpos($output, 'Factory Produced') !== false ? ok("Column Factory Produced present") : no("Missing Factory Produced");

// 2. Check sidebar contains Warehouse
$routes = require __DIR__ . '/../app/routes.php';
$hasWarehouseRoute = isset($routes['warehouse']);
if ($hasWarehouseRoute) {
    ok("Route 'warehouse' registered in app/routes.php");
} else {
    no("Route 'warehouse' missing in app/routes.php");
}

echo "\n============================================\n";
echo "SUMMARY: Passes: $pass, Failures: $fail\n";
echo "============================================\n";

if ($fail > 0) exit(1);
