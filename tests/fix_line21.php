<?php
$f = __DIR__ . '/../app/views/claims/goods_view.php';
$s = file_get_contents($f);
$bad = "' · <strong>Approved by:?></strong> '";
$good = "' · <strong>' . __('Approved by:') . '</strong> '";
if (strpos($s, $bad) === false) { fwrite(STDERR, "anchor miss\n"); exit(1); }
$s = str_replace($bad, $good, $s);
file_put_contents($f, $s);
echo "fixed\n";
