<?php
require __DIR__ . '/../app/core/bootstrap.php';
db_exec("CREATE TABLE IF NOT EXISTS daily_production_rm_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id INT NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    qty_kg DECIMAL(18,3) NOT NULL,
    note VARCHAR(255) NULL
)");
echo "Table daily_production_rm_items ready\n";
