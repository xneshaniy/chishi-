<?php
// Update test assertions in test_production_autodeduct_cashpos_dashboard.php and test_warehouse_dedicated_tab.php
// to match the pure clean i18n keys!

$f1 = __DIR__ . '/../tests/test_production_autodeduct_cashpos_dashboard.php';
$c1 = file_get_contents($f1);
$c1 = str_replace(
    [
        "strpos(\$dashView, 'آج کی فیکٹری سمری') !== false",
        "strpos(\$dashView, 'را مٹیریل یوز (آج)') !== false",
        "strpos(\$dashView, 'تیار مال پروڈکشن (آج)') !== false",
        "strpos(\$dashView, 'آج کی سیل') !== false",
        "strpos(\$dashView, 'کیش پوزیشن (آج کا نیٹ)') !== false"
    ],
    [
        "strpos(\$dashView, 'Today\'s Live Factory Summary') !== false",
        "strpos(\$dashView, 'RM Used Today') !== false",
        "strpos(\$dashView, 'FG Produced Today') !== false",
        "strpos(\$dashView, 'Sales Today') !== false",
        "strpos(\$dashView, 'Net Cash Movement') !== false"
    ],
    $c1
);
file_put_contents($f1, $c1);

$f2 = __DIR__ . '/../tests/test_warehouse_dedicated_tab.php';
$c2 = file_get_contents($f2);
$c2 = str_replace(
    [
        "Warehouse (ویئر ہاؤس اسٹاک)",
        "Raw Materials Stock (خام مال اسٹاک)",
        "Raw Material Stock (گودام میں خام مال)"
    ],
    [
        "Warehouse Stock",
        "Raw Materials Stock",
        "Raw Material Stock"
    ],
    $c2
);
file_put_contents($f2, $c2);

echo "Tests updated to check pure keys\n";
