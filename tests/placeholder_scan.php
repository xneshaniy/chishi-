<?php
/** Dump all placeholder attribute values containing letters — see which are instructions vs proper nouns. */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$vals = [];
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $src = file_get_contents($f->getPathname());
    preg_match_all('/placeholder="([^"]*)"/', $src, $m);
    foreach ($m[1] as $v) {
        if (preg_match('/[A-Za-z]{2,}/', $v) && strpos($v, '<') === false) $vals[$v] = true;
    }
}
ksort($vals);
echo count($vals), " unique placeholder values:\n";
foreach (array_keys($vals) as $v) echo "  ", $v, "\n";
