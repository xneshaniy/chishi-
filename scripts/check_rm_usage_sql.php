<?php
require __DIR__ . '/../app/core/bootstrap.php';
echo db_val("SELECT sql FROM sqlite_master WHERE type='table' AND name='rm_daily_usage'") . "\n";
$indexes = db_all("SELECT * FROM sqlite_master WHERE type='index' AND tbl_name='rm_daily_usage'");
print_r($indexes);
