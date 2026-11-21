<?php
/** Remove the corrupt remnant `) ?>` that a bad encoding pass left inside HTML (never inside PHP tags). */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $parts = preg_split('/(<\?php.*?\?>|<\?=.*?\?>)/s', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    $changed = 0;
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) continue; // PHP section — untouched
        $parts[$i] = str_replace(') ?>', '', $part, $n);
        $changed += $n;
    }
    if ($changed) {
        file_put_contents($file->getPathname(), implode('', $parts));
        echo "FIXED ({$changed}) {$file->getPathname()}\n";
        $total += $changed;
    }
}
echo "TOTAL removed: $total\n";
