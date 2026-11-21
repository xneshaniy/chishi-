<?php
require __DIR__ . '/../app/core/bootstrap.php';
$boms = db_all("SELECT * FROM boms");
echo "BOMS (" . count($boms) . "):\n";
print_r($boms);
$bomItems = db_all("SELECT bi.*, p.name AS item_name FROM bom_items bi JOIN products p ON p.id = bi.product_id");
echo "BOM ITEMS (" . count($bomItems) . "):\n";
print_r($bomItems);
$rmProducts = db_all("SELECT id, sku, name, kind FROM products WHERE kind IN ('raw','packaging')");
echo "RM PRODUCTS (" . count($rmProducts) . "):\n";
print_r($rmProducts);
$fgProducts = db_all("SELECT id, sku, name, kind FROM products WHERE kind = 'finished'");
echo "FG PRODUCTS (" . count($fgProducts) . "):\n";
print_r($fgProducts);
