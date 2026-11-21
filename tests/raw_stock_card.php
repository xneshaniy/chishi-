<?php
$f = 'app/views/inventory/raw_stock.php';
$s = file_get_contents($f);
$old = '<div class="lbl"><?= __(\'Total Raw Material Qty\') ?></div><div class="val"><?= qty($totQty) ?></div>';
$new = '<div class="lbl"><?= __(\'Total Raw Material Qty\') ?></div><div class="val"><?= number_format($totQty, 0) ?><span class="muted" style="font-size:14px"> KG</span></div><div class="sub"><?= number_format(UnitConverter::fromKg($totQty, \'TON\'), 3) ?> TON · <?= number_format(UnitConverter::fromKg($totQty, \'MAN\'), 3) ?> MAN</div>';
$n = substr_count($s, $old);
$s = str_replace($old, $new, $s);
file_put_contents($f, $s);
echo "cards: $n\n";
