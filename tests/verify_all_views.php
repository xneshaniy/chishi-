<?php
$open = '<' . chr(63) . '= __(';   // translation echo opening
$close = ') ' . chr(63) . chr(62);  // translation echo closing
$needle = "'" . ') ' . chr(63) . chr(62); // broken fragment: quote paren space close
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    $clean = '';
    $pos = 0; $n = strlen($s); $skippedOpen = 0;
    while (($i = strpos($s, $open, $pos)) !== false) {
        $end = strpos($s, $needle, $i);
        if ($end === false) { $skippedOpen = -1; break; }
        $clean .= substr($s, $pos, $i - $pos);
        $pos = $end + strlen($needle);
        $skippedOpen++;
    }
    $clean .= substr($s, $pos);
    $bad = substr_count($clean, $needle);
    if ($bad) {
        echo '=== ', str_replace(__DIR__ . '/../', '', $f->getPathname()), " : $bad leftover-fragments\n";
        $p = 0; $shown = 0;
        while (($p = strpos($clean, $needle, $p)) !== false && $shown < 3) { echo '   ...', substr($clean, max(0, $p - 80), 110), "\n"; $p++; $shown++; }
    }
}
echo "scan done\n";
