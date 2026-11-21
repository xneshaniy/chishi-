<?php
/** Revert the flawed repair: ` ?></span>` -> `</span>` (back to post-fix2 state). */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $s = file_get_contents($file->getPathname());
    $n = substr_count($s, ' ?></span>');
    if ($n) {
        file_put_contents($file->getPathname(), str_replace(' ?></span>', '</span>', $s));
        echo str_pad((string)$n, 4), ' ', $file->getPathname(), "\n";
        $total += $n;
    }
}
echo "TOTAL reverted: $total\n";
