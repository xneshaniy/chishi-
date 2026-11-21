<?php
$s = file_get_contents('app/views/stock/adjustment_new.php');
foreach (explode("\n", $s) as $i => $line) {
    if (str_contains($line, 'opt') || str_contains($line, 'IN (+)') || str_contains($line, 'warehouse_id')) {
        echo "== ", $i + 1, " ==\n", bin2hex($line), "\n", $line, "\n\n";
    }
}
