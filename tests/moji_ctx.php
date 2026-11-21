<?php
foreach (['app/controllers/SalesController.php', 'app/controllers/SalesmenController.php'] as $f) {
    $s = file_get_contents($f);
    $pos = 0;
    while (($pos = strpos($s, "\xc3\xa2", $pos)) !== false) {
        echo '--- ', basename($f), ' @', $pos, "\n";
        echo substr($s, max(0, $pos - 60), 110), "\n";
        $pos += 2;
    }
}
