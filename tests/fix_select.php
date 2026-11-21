<?php
foreach (['app/views/purchase/order_new.php', 'app/views/sales/order_new.php'] as $f) {
    $s = file_get_contents($f);
    $n = 0;
    $s2 = str_replace('- select -) ?>', '- select -', $s, $n);
    file_put_contents($f, $s2);
    echo $f, ' fixed ', $n, "\n";
}
