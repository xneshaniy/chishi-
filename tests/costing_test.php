<?php
/** FIFO vs weighted-average costing on mixed purchase layers. */
foreach (['fifo', 'weighted_average'] as $method) {
    $GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => $method, 'allow_negative_stock' => false];
    $_SESSION = [];
    require_once __DIR__ . '/../app/core/helpers.php';
    require_once __DIR__ . '/../app/core/Database.php';
    require_once __DIR__ . '/../app/services/Accounting.php';
    require_once __DIR__ . '/../app/services/Inventory.php';
    require_once __DIR__ . '/../app/services/Posting.php';
    require_once __DIR__ . '/../app/services/UnitConverter.php';
    require_once __DIR__ . '/../database/seed.php';
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    Database::setPdo($pdo);
    $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
    seed_base($pdo);

    $wh = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
    $pid = db_insert('products', ['sku' => 'FIFO-TEST', 'name' => 'FIFO Widget', 'kind' => 'finished', 'purchase_price' => 100, 'sale_price' => 150, 'tax_rate' => 0, 'is_active' => 1, 'created_at' => now_local()]);
    // Batch 1: 10 @ 100; Batch 2: 10 @ 120
    Inventory::receive($pid, $wh, 10, 100, 'purchase', 'test', 1);
    Inventory::receive($pid, $wh, 10, 120, 'purchase', 'test', 2);
    $cost = Inventory::issue($pid, $wh, 15, 'sale', 'test', 3);
    echo strtoupper(str_replace('_', ' ', $method)), ": unit cost issued = ", number_format($cost, 2), "\n";
    if ($method === 'fifo') {
        echo (abs($cost - 106.6667) < 0.01 ? "  PASS FIFO consumed oldest layers: (1000+600)/15" : "  FAIL expected 106.67"), "\n";
    } else {
        echo (abs($cost - 110) < 0.01 ? "  PASS WA average cost 110" : "  FAIL expected 110"), "\n";
    }
    echo "\n";
}
