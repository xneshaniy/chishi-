<?php
/** Scan for literal ?? sequences in HTML (outside PHP tags) — lost emoji/glyphs. */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $parts = preg_split('/(<\?php.*?\?>|<\?=.*?\?>)/s', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) continue;
        if (preg_match_all('/.{30}\?\?.{30}/s', $part, $m)) {
            foreach ($m[0] as $ctx) { $total++; echo "== {$file->getPathname()}\n   ...", str_replace(["\r", "\n"], ' ', $ctx), "...\n"; }
        }
    }
}
echo "TOTAL ?? contexts: $total\n";
