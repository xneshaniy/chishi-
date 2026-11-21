<?php
// Read live PHP error log - upload this, run once, then it auto-deletes
$logPaths = [
    dirname(__DIR__, 2) . '/logs/php_errors.log',
    dirname(__DIR__, 2) . '/error_log',
    '/var/log/php_errors.log',
    ini_get('error_log'),
];

foreach ($logPaths as $lp) {
    if ($lp && file_exists($lp)) {
        $lines = array_slice(file($lp), -60);
        echo "=== LOG: $lp ===\n";
        echo implode('', $lines);
        echo "\n";
    }
}

// Also run a quick diagnostic query for the suppliers tab
try {
    require_once __DIR__ . '/../app/bootstrap.php';
    $result = db_all("SELECT s.id, s.name, COUNT(DISTINCT sp.product_id) AS prod_count, GROUP_CONCAT(p.name SEPARATOR '||') AS product_names FROM suppliers s LEFT JOIN supplier_products sp ON sp.supplier_id = s.id LEFT JOIN products p ON p.id = sp.product_id AND p.is_active = 1 WHERE s.is_active = 1 GROUP BY s.id ORDER BY s.name LIMIT 5");
    echo "\n=== Suppliers Query Test ===\n";
    foreach ($result as $r) {
        echo "ID={$r['id']} Name={$r['name']} Products=" . ($r['product_names'] ?: 'none') . "\n";
    }
    echo "✅ Suppliers query OK\n";
} catch (Throwable $e) {
    echo "❌ Suppliers query error: " . $e->getMessage() . "\n";
}

// Test supplier_products insert ignore
try {
    db_exec("INSERT IGNORE INTO supplier_products (supplier_id, product_id) VALUES (99999, 99999)");
    echo "✅ INSERT IGNORE syntax OK\n";
} catch (Throwable $e) {
    echo "❌ INSERT IGNORE error: " . $e->getMessage() . "\n";
}

@unlink(__FILE__);
echo "\nScript self-deleted.\n";
