<?php
/** Find suspicious ") ?>" occurrences inside <script> regions of every view. */
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    $pos = 0;
    while (($i = stripos($s, '<script', $pos)) !== false) {
        $end = stripos($s, '</script>', $i);
        if ($end === false) break;
        $chunk = substr($s, $i, $end - $i);
        $off = 0;
        while (($p = strpos($chunk, ") " . chr(63) . chr(62), $off)) !== false) {
            echo str_replace(__DIR__ . '/../', '', $f->getPathname()), ' : ...', substr($chunk, max(0, $p - 60), 80), "...\n";
            $off = $p + 2;
        }
        $pos = $end;
    }
}
echo "script scan done\n";
