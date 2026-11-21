<?php
$f = 'app/views/purchase/invoice_view.php';
$s = file_get_contents($f);
$bad = "<?= (int)\$it['is_bonus'] ? ' <span class=\"badge green\"><?= __('BONUS') ?></span>' : '' ?>";
$good = "<?php if ((int)\$it['is_bonus']): ?> <span class=\"badge green\"><?= __('BONUS') ?></span><?php endif; ?>";
$s2 = str_replace($bad, $good, $s, $n);
file_put_contents($f, $s2);
echo 'replaced: ', $n, "\n";
