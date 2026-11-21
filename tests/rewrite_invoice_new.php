<?php
/** Rewrite the Items section of the sales invoice form to the simplified layout. */
$f = __DIR__ . '/../app/views/sales/invoice_new.php';
$lines = file($f);
$out = [];
foreach ($lines as $i => $line) {
    if (strpos($line, '&lt;?= __(\'Items') !== false || strpos($line, "__('Items") !== false) {
        $out[] = '<h3 style="font-size:13px;margin:14px 0 6px"><?= __(\'Items\') ?></h3>' . "\n";
        continue;
    }
    if (strpos($line, 'id="itemsBody"') !== false) {
        $out[] = '<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __(\'Product\') ?></th><th><?= __(\'Qty\') ?></th><th><?= __(\'Price\') ?></th><th class="num"><?= __(\'Line Total\') ?></th><th></th></tr></thead><tbody id="itemsBody">' . "\n";
        continue;
    }
    if (strpos($line, 'name="items[product_id][0]"') !== false) {
        $out[] = '<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __(\'— select product —\') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][0]" value="1" required></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="items[price][0]" value="0.00" required></td><td class="line-total num">0.00</td><td></td></tr>' . "\n";
        continue;
    }
    $out[] = $line;
}
file_put_contents($f, implode('', $out));
echo "done\n";
