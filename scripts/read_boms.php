<?php
require __DIR__ . '/../app/core/bootstrap.php';
$boms = db_all("SELECT b.*, p.name AS product_name FROM bills_of_materials b JOIN products p ON p.id = b.product_id");
echo "BOMS:\n";
print_r($boms);
$lines = db_all("SELECT bl.*, p.name AS material_name, p.sku FROM bom_lines bl JOIN products p ON p.id = bl.material_id");
echo "BOM LINES:\n";
print_r($lines);
