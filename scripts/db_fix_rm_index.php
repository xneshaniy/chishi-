<?php
require __DIR__ . '/../app/core/bootstrap.php';
try {
    db_exec("DROP INDEX IF EXISTS uq_rm_usage_active");
    db_exec("CREATE INDEX IF NOT EXISTS idx_rm_usage_date ON rm_daily_usage (usage_date, product_id, warehouse_id)");
    echo "Index updated: multiple production batches per day are now supported!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
