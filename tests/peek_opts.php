<?php
$s = file_get_contents('app/views/purchase/order_new.php');
$p = strpos($s, 'opts');
echo bin2hex(substr($s, $p, 60)), "\n";
echo substr($s, $p, 60), "\n";
