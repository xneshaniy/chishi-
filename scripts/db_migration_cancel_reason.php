<?php
require __DIR__ . '/../app/core/bootstrap.php';
$cols = array_column(db_all("PRAGMA table_info(rm_daily_usage)"), 'name');
if (!in_array('cancel_reason', $cols, true)) {
    db_exec("ALTER TABLE rm_daily_usage ADD COLUMN cancel_reason VARCHAR(255) NULL");
    echo "Added cancel_reason\n";
}
if (!in_array('cancelled_by', $cols, true)) {
    db_exec("ALTER TABLE rm_daily_usage ADD COLUMN cancelled_by INT NULL");
    echo "Added cancelled_by\n";
}
echo "Columns ready\n";
