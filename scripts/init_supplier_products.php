<?php
require_once __DIR__ . '/../app/core/bootstrap.php';

// 1. Create supplier_products table
db_exec("CREATE TABLE IF NOT EXISTS supplier_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    default_rate DECIMAL(18,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(supplier_id, product_id)
)");
echo "supplier_products table ready.\n";

// 2. Pre-populate some realistic mappings if empty so supplier filtering works immediately
$count = (int)db_val("SELECT COUNT(*) FROM supplier_products");
if ($count === 0) {
    $suppliers = db_all("SELECT id FROM suppliers WHERE is_active = 1 LIMIT 2");
    $rawProds = db_all("SELECT id FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 LIMIT 4");
    if (!empty($suppliers[0]) && !empty($rawProds)) {
        // Map first 2 products to supplier 1
        db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$suppliers[0]['id'], $rawProds[0]['id']]);
        if (isset($rawProds[1])) {
            db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$suppliers[0]['id'], $rawProds[1]['id']]);
        }
    }
    if (isset($suppliers[1]) && isset($rawProds[2])) {
        // Map 3rd and 4th products to supplier 2
        db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$suppliers[1]['id'], $rawProds[2]['id']]);
        if (isset($rawProds[3])) {
            db_exec("INSERT OR IGNORE INTO supplier_products (supplier_id, product_id) VALUES (?, ?)", [$suppliers[1]['id'], $rawProds[3]['id']]);
        }
    }
    echo "Initial sample mappings populated.\n";
}
