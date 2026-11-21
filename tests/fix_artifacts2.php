<?php
/** Remove corrupt remnant `) ?></span>` left inside badge strings (PHP or HTML). */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $out = str_replace(') ?></span>', '</span>', $src, $n);
    if ($n) {
        file_put_contents($file->getPathname(), $out);
        echo "FIXED ($n) {$file->getPathname()}\n";
        $total += $n;
    }
}
echo "TOTAL: $total\n";
