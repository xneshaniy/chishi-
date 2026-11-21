<?php
/** Find raw English in HTML text, attributes, and JS string literals outside of __(). */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$rows = [];
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $src = file_get_contents($f->getPathname());
    $parts = preg_split('/(<\?php.*?\?>|<\?=.*?\?>)/s', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1) {
            // inside PHP: look for confirm('...') / alert('...') raw en-US literals (not already __())
            if (preg_match_all("/confirm\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\)|alert\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\)/", $p, $m)) {
                foreach ($m[1] as $j => $lit) {
                    $lit = $lit ?: $m[2][$j];
                    if (preg_match('/[A-Za-z]{3,}/', $lit)) $rows[] = basename($f->getPathname()) . ': JS: ' . $lit;
                }
            }
            continue;
        }
        // attributes with raw english (title= alt= aria-label= placeholder= done)
        if (preg_match_all('/(?:title|alt|aria-label)="([^"<]*)"/', $p, $m)) {
            foreach ($m[1] as $v) {
                if (preg_match('/^[A-Za-z][A-Za-z0-9 .,&\/()\'\-]*$/', trim($v)) && strlen(trim($v)) > 3 && !preg_match('/^(CTC|JUSTI|CHISHTI|DEV|RS|SKU|GRN|AY)$/i', trim($v))) {
                    $rows[] = basename($f->getPathname()) . ': attr: ' . trim($v);
                }
            }
        }
        // bare text nodes
        $text = str_replace("\n", ' ', strip_tags($p));
        foreach (preg_split('/\s{2,}/', $text) as $chunk) {
            $chunk = trim($chunk);
            if (preg_match('/^[A-Za-z][A-Za-z0-9 .,&\/()\'\-]{3,}$/', $chunk) && !preg_match('/\{|\$|__\(|e\(|print|log/', $chunk)) {
                $rows[] = basename($f->getPathname()) . ': text: ' . $chunk;
            }
        }
    }
}
echo count($rows), " candidates\n";
sort($rows);
foreach ($rows as $r) echo "  $r\n";
