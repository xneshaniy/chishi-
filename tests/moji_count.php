<?php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app', FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    $count = substr_count($s, "\xc3\xa2");  // starts with â
    if ($count) { $total += $count; echo $f->getPathname(), ' : ', $count, "\n"; }
}
echo "TOTAL c3a2 markers: $total\n";
