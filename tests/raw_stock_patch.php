<?php
$f = 'app/views/inventory/raw_stock.php';
$s = file_get_contents($f);
$old = '<td class="num"><?= qty($r[\'purchased\']) ?></td><td class="num"><strong><?= qty($r[\'qty\']) ?></strong></td><td class="num"><?= qty($r[\'used\']) ?></td><td class="num"><?= qty($r[\'used_today\']) ?></td>';
$new = '<td class="num"><?= number_format($r[\'purchased\'], 3) ?> KG</td><td class="num"><strong><?= number_format($r[\'qty\'], 3) ?></strong> KG<div class="muted" style="font-size:11px"><?= number_format(UnitConverter::fromKg($r[\'qty\'], \'TON\'), 3) ?> TON · <?= number_format(UnitConverter::fromKg($r[\'qty\'], \'MAN\'), 3) ?> MAN</div></td><td class="num"><?= number_format($r[\'used\'], 3) ?> KG</td><td class="num"><?= number_format($r[\'used_today\'], 3) ?> KG</td>';
$n = substr_count($s, $old);
$s = str_replace($old, $new, $s);
file_put_contents($f, $s);
echo "replaced: $n\n";
// also update card labels to KG totals
$s = file_get_contents($f);
$s = str_replace("<div class=\"lbl\"><?= __('Total Raw Material Qty') ?></div><div class=\"val\"><?= qty($totQty) ?></div>", "<div class=\"lbl\"><?= __('Total Raw Material Qty') ?></div><div class=\"val\"><?= number_format($totQty, 0) ?><span class=\"muted\" style=\"font-size:14px\"> KG</span></div><div class=\"sub\"><?= number_format(UnitConverter::fromKg($totQty, 'TON'), 3) ?> TON · <?= number_format(UnitConverter::fromKg($totQty, 'MAN'), 3) ?> MAN</div>", $s, $n2);
echo "card: $n2\n";
file_put_contents($f, $s);
