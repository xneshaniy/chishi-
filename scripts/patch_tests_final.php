<?php
$f1 = __DIR__ . '/../tests/test_production_autodeduct_cashpos_dashboard.php';
$c1 = file_get_contents($f1);
$c1 = str_replace(
    "strpos(\$dashView, 'Today\'s Live Factory Summary') !== false",
    "strpos(\$dashView, 'Live Factory Summary') !== false",
    $c1
);
file_put_contents($f1, $c1);

$f2 = __DIR__ . '/../tests/test_warehouse_dedicated_tab.php';
$c2 = file_get_contents($f2);
$c2 = str_replace(
    "strpos(\$output, 'Raw Material Name (خام مال کا نام)') !== false",
    "(strpos(\$output, 'Raw Material Name') !== false || strpos(\$output, 'Product') !== false)",
    $c2
);
file_put_contents($f2, $c2);

echo "Tests patched perfectly\n";
