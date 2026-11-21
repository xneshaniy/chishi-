<?php
require __DIR__ . '/../app/core/bootstrap.php';
$cols = db_all("PRAGMA table_info(rm_daily_usage)");
$colNames = array_column($cols, 'name');
if (!in_array('production_run_id', $colNames, true)) {
    db_exec("ALTER TABLE rm_daily_usage ADD COLUMN production_run_id INT NULL");
    echo "Added production_run_id to rm_daily_usage\n";
} else {
    echo "production_run_id already exists in rm_daily_usage\n";
}
