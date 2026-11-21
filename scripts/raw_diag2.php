<?php
// Minimal diagnostic - upload and run directly
header('Content-Type: text/plain');

// Bootstrap
define('APP_ROOT', __DIR__);
$cfgPath = __DIR__ . '/config/config.php';
if (!file_exists($cfgPath)) { die("config not found"); }
$GLOBALS['CONFIG'] = require $cfgPath;

// Load Database class
require __DIR__ . '/app/core/Database.php';

// Connect
try {
    $pdo = Database::connect();
    echo "✅ DB Connected\n";
} catch (Throwable $e) {
    die("❌ DB Error: " . $e->getMessage() . "\n");
}

// Test 1: GROUP_CONCAT SEPARATOR syntax
try {
    $rows = db_all("SELECT s.id, s.name, GROUP_CONCAT(p.name SEPARATOR '||') AS product_names FROM suppliers s LEFT JOIN supplier_products sp ON sp.supplier_id = s.id LEFT JOIN products p ON p.id = sp.product_id AND p.is_active = 1 WHERE s.is_active = 1 GROUP BY s.id ORDER BY s.name LIMIT 5");
    echo "✅ GROUP_CONCAT SEPARATOR query: " . count($rows) . " suppliers found\n";
    foreach ($rows as $r) {
        echo "   - {$r['name']}: " . ($r['product_names'] ?: 'no products') . "\n";
    }
} catch (Throwable $e) {
    echo "❌ GROUP_CONCAT error: " . $e->getMessage() . "\n";
}

// Test 2: INSERT IGNORE syntax
try {
    // Insert with invalid IDs (will just fail constraint check silently)
    db_exec("INSERT IGNORE INTO supplier_products (supplier_id, product_id) VALUES (999999, 999999)");
    echo "✅ INSERT IGNORE syntax works\n";
} catch (Throwable $e) {
    echo "❌ INSERT IGNORE error: " . $e->getMessage() . "\n";
}

// Test 3: Purchases query
try {
    $r = db_all("SELECT pi.id, pi.date, pi.payment_type, pi.payment_status, pi.amount_paid, pi.status, s.name AS supplier, p.name AS product, p.kind, ii.qty, ii.unit_short, ii.qty_kg, ii.unit_cost, ii.line_total FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id JOIN purchase_items ii ON ii.invoice_id = pi.id JOIN products p ON p.id = ii.product_id WHERE pi.date >= ? AND pi.date <= ? AND p.kind IN ('raw','packaging') AND pi.status NOT IN ('rejected','reversed') ORDER BY pi.date DESC, p.name LIMIT 5", [date('Y-m-01'), date('Y-m-d')]);
    echo "✅ Purchases query: " . count($r) . " rows\n";
} catch (Throwable $e) {
    echo "❌ Purchases query error: " . $e->getMessage() . "\n";
}

// Test 4: Warehouse stock query
try {
    $r = db_all("SELECT p.id, p.sku, p.name, p.kind, p.reorder_level, COALESCE(x.qty,0) AS qty, COALESCE(x.value,0) AS value FROM products p LEFT JOIN (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type != 'salesman' GROUP BY sb.product_id) x ON x.product_id = p.id WHERE p.kind IN ('raw','packaging') AND p.is_active = 1 ORDER BY p.name");
    echo "✅ Warehouse stock query: " . count($r) . " rows\n";
} catch (Throwable $e) {
    echo "❌ Warehouse stock error: " . $e->getMessage() . "\n";
}

// Test 5: Daily usage query  
try {
    $today = date('Y-m-d');
    $r = db_all("SELECT rdu.*, p.name AS product_name, p.sku, w.name AS warehouse_name, u.username AS recorded_by_username FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id LEFT JOIN warehouses w ON w.id = rdu.warehouse_id LEFT JOIN users u ON u.id = rdu.recorded_by WHERE rdu.usage_date = ? ORDER BY rdu.id DESC", [$today]);
    echo "✅ Daily usage query: " . count($r) . " entries today\n";
} catch (Throwable $e) {
    echo "❌ Daily usage error: " . $e->getMessage() . "\n";
}

// Test 6: Month total query (the strftime fix)
try {
    $r = db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE status='posted' AND usage_date >= ? AND usage_date <= ?", [date('Y-m-01'), date('Y-m-t')]);
    echo "✅ Month total query: " . number_format((float)$r, 2) . " KG\n";
} catch (Throwable $e) {
    echo "❌ Month total error: " . $e->getMessage() . "\n";
}

@unlink(__FILE__);
echo "\n--- Diagnostic complete. File self-deleted. ---\n";
