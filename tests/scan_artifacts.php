<?php
/** Scan view files for corrupt artifacts: literal `) ?>` remaining in HTML (outside PHP tags). */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    // strip both kinds of PHP sections
    $html = preg_replace('/<\?php.*?\?>/s', '', $src);
    $html = preg_replace('/<\?=.*?\?>/s', '', $html);
    if (preg_match_all('/.{40}\) \?>.{20}/s', $html, $m)) {
        echo "\n== ", $file->getPathname(), " ==\n";
        foreach ($m[0] as $ctx) echo "   ...", str_replace(["\r", "\n"], ' ', $ctx), "...\n";
    }
}
