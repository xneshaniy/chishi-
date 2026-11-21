<?php
/** Search every view for the mangling signature: any quote, then close-paren, space, close-tag. */
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$close = ") " . chr(63) . chr(62);
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    $pos = 0; $found = 0;
    while (($p = strpos($s, $close, $pos)) !== false) {
        $prev = $s[$p - 1] ?? '';
        // a quote directly before the close-paren indicates a mangled translation echo
        if ($prev === "'" || $prev === '"') {
            // not inside a valid escaped-quote sequence and not a legit e() fallback: report with context
            $found++;
            if ($found <= 2) echo str_replace(__DIR__ . '/../', '', $f->getPathname()), " : ...", substr($s, max(0, $p - 70), 90), "...\n";
        }
        $pos = $p + 1;
    }
}
echo "sweep done\n";
