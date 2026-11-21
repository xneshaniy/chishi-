<?php
/** Search every view for the mangling signature "—) ?>" and "-) ?>" / "— select —) ?>" remnants anywhere. */
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$needles = ["\xe2\x80\x94) " . chr(63) . chr(62), "-) " . chr(63) . chr(62), "\xe2\x80\x94 select \xe2\x80\x94) " . chr(63) . chr(62)];
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    // skip valid tags like <?= e($x) ?> which don't match these (no parenthesized quote directly before)
    foreach ($needles as $i => $needle) {
        $pos = 0;
        while (($p = strpos($s, $needle, $pos)) !== false) {
            echo str_replace(__DIR__ . '/../', '', $f->getPathname()), " needle#$i : ...", substr($s, max(0, $p - 60), 80), "...\n";
            $pos = $p + 1;
            break 1;
        }
    }
}
echo "sweep done\n";
