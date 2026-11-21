<?php
$files = ['app/controllers/SalesController.php','app/controllers/SalesmenController.php','app/controllers/PurchaseController.php','app/views/purchase/raw.php','app/views/layout.php'];
$want = ['Invoice posted','Invoice reversed','Sales return posted','Payment received','Settlement saved','Bonus recorded','Salesman return posted','All Purchases','Raw Material Daily','Finished Product Daily','Purchase request submitted','Purchase submitted','Purchase approved'];
foreach ($files as $f) {
    $s = file_get_contents($f);
    foreach ($want as $w) {
        $p = strpos($s, $w);
        if ($p === false) continue;
        $end = strpos($s, "'", strpos($s, '__(', $p));
        $seg = substr($s, $p, $end - $p);
        // find the full __() literal
        $start = strrpos(substr($s, 0, $p), "__('");
        if ($start === false) continue;
        $close = strpos($s, ")", $p);
        $full = substr($s, $start, $close - $start + 1);
        echo 'FILE ', $f, ' :: ';
        echo bin2hex($full), "\n";
        echo "    ", addslashes($full), "\n";
    }
}
