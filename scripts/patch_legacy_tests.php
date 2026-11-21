<?php
// Update test assertions in test_daily_rm_usage_tab.php and test_warehouse_dedicated_tab.php
// to reflect the clean standardized i18n labels

$f1 = __DIR__ . '/../tests/test_daily_rm_usage_tab.php';
$c1 = file_get_contents($f1);
$c1 = str_replace(
    "ok('Tab bar shows ڈیلی یوز label',      strpos(\$rawView, 'ڈیلی یوز') !== false);",
    "ok('Tab bar shows Daily Usage label',   (strpos(\$rawView, 'Daily Usage') !== false || strpos(\$rawView, 'ڈیلی یوز') !== false));",
    $c1
);
file_put_contents($f1, $c1);

$f2 = __DIR__ . '/../tests/test_warehouse_dedicated_tab.php';
$c2 = file_get_contents($f2);
$c2 = str_replace(
    [
        "strpos(\$output, 'Factory Products in Stock (تیار فیکٹری مال)') !== false",
        "strpos(\$output, 'Total Tracked Products (کل پروڈکٹس)') !== false",
        "strpos(\$output, '1. Raw Material Stock (ہمارے پاس را مٹیریل کتنا ہے)') !== false",
        "strpos(\$output, '2. Factory Finished Products (جو فیکٹری سے بن کر آئے ہیں)') !== false",
        "strpos(\$output, 'Product Name (فیکٹری پروڈکٹ کا نام)') !== false",
        "strpos(\$output, 'In Stock Quantity (موجودہ اسٹاک)') !== false",
        "strpos(\$output, 'Factory Produced (کل پیداوار)') !== false",
    ],
    [
        "strpos(\$output, 'Factory Products in Stock') !== false",
        "strpos(\$output, 'Total Tracked Products') !== false",
        "strpos(\$output, '1. Raw Material Stock') !== false",
        "strpos(\$output, '2. Factory Finished Products') !== false",
        "strpos(\$output, 'Product Name') !== false",
        "strpos(\$output, 'In Stock Quantity') !== false",
        "strpos(\$output, 'Factory Produced') !== false",
    ],
    $c2
);
file_put_contents($f2, $c2);

echo "Tests updated for clean i18n standards\n";
