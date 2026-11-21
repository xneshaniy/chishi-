<?php
/**
 * JUSTI NUMBER ONE ERP — seed the four CTC cigarette products.
 * Idempotent: checks for existing products by name (and SKU) before inserting.
 * NEVER creates duplicate records. Does not touch stock (products start with zero stock).
 *
 * Usage (on the server):  php install/seed_production_products.php
 * Safe to run multiple times. Works on both SQLite (dev) and MySQL (production).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the installer first.\n"); exit(1); }
$config = require $configPath;
$db = $config['db'] ?? [];
$driver = $db['driver'] ?? 'mysql';

$products = [
    ['sku' => 'CHISHTI-01',  'name' => 'Chishti No 1'],
    ['sku' => 'ROHI-01',     'name' => 'Rohi No 1'],
    ['sku' => 'CHISHTI-01F', 'name' => 'Chishti No 1 - Filter Khaini'],
    ['sku' => 'ROHI-01F',    'name' => 'Rohi No 1 - Filter Khaini'],
];

if ($driver === 'sqlite') {
    $dsn = (string)($db['dsn'] ?? '');
    $pdo = new PDO(strpos($dsn, 'sqlite:') === 0 ? $dsn : 'sqlite:' . $dsn);
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Best-effort category: first "finished" category if one exists (cigarettes preferred), else NULL
$catId = $pdo->query("SELECT id FROM product_categories WHERE kind = 'finished' AND (name LIKE '%cigarette%' OR name LIKE '%Cigarette%') ORDER BY id LIMIT 1")->fetchColumn();
if (!$catId) { $catId = $pdo->query("SELECT id FROM product_categories WHERE kind = 'finished' ORDER BY id LIMIT 1")->fetchColumn(); }
$catId = $catId ? (int)$catId : null;

$fin = 0; $skip = 0;
foreach ($products as $p) {
    $exists = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE name = ' . $pdo->quote($p['name']))->fetchColumn();
    if ($exists > 0) {
        echo "SKIP (exists by name): {$p['name']}\n";
        $skip++;
        continue;
    }
    $skuExists = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE sku = ' . $pdo->quote($p['sku']))->fetchColumn();
    $sku = $p['sku'];
    if ($skuExists) {
        // SKU taken by another record — bump the suffix until it is free
        $n = 2;
        do { $sku = preg_replace('/-0*(\d+)(F)?$/', '-0' . $n . '$2', $p['sku']); $n++; } while ((int)$pdo->query('SELECT COUNT(*) FROM products WHERE sku = ' . $pdo->quote($sku))->fetchColumn() > 0 && $n < 10);
    }
    $pdo->prepare('INSERT INTO products (sku, name, category_id, unit_id, kind, pack_size, purchase_price, sale_price, issue_rate, min_price, tax_rate, reorder_level, has_expiry, is_active, created_at) VALUES (?, ?, ?, NULL, \'finished\', NULL, 0, 0, 0, 0, 0, 0, 0, 1, ?)')
        ->execute([$sku, $p['name'], $catId, date('Y-m-d H:i:s')]);
    echo "CREATED: {$p['name']}  (SKU {$sku})\n";
    $fin++;
}
echo "\nDone. Created: $fin, skipped: $skip.\n";
echo "All four products are 'finished' goods with zero opening stock (no stock rows were created).\n";
