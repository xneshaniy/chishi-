<?php
$z = new ZipArchive();
$z->open(__DIR__ . '/../justi_erp_production.zip');
foreach (['.htaccess','storage/.htaccess','app/.htaccess','config/.htaccess','database/.htaccess','bin/.htaccess','docs/.htaccess'] as $n) {
    echo "===== $n =====\n", $z->getFromName($n), "\n";
}
// double-scan for any lingering password/dev markers, whole archive, verbose
$needles = ['owner1234','salesman1234','dev.sqlite','demo_seed','OB-DEMO','Demo Salesman','Dev Owner','DEMO-'];
$hits = 0;
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    $c = $z->getFromName($n);
    foreach ($needles as $n2) {
        if ($c !== false && stripos($c, $n2) !== false) { echo "HIT [$n2] in $n\n"; $hits++; }
    }
}
echo $hits === 0 ? "\nCLEAN: zero forbidden markers in all " . $z->numFiles . " files\n" : "\n$hits HIT(S)\n";
$z->close();
