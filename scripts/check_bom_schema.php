<?php
require __DIR__ . '/../app/core/bootstrap.php';
echo "bills_of_materials schema:\n";
print_r(db_all("PRAGMA table_info(bills_of_materials)"));
echo "\nbom_lines schema:\n";
print_r(db_all("PRAGMA table_info(bom_lines)"));

$boms = db_all("SELECT * FROM bills_of_materials");
echo "\nBOMs count: " . count($boms) . "\n";
print_r($boms);

$bomLines = db_all("SELECT bl.*, p.name AS material_name FROM bom_lines bl JOIN products p ON p.id = bl.product_id");
echo "\nBOM Lines count: " . count($bomLines) . "\n";
print_r($bomLines);
