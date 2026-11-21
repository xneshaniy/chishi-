<?php
/** Find hardcoded English text in HTML text nodes and attributes, outside of PHP tags. */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$lines = [];
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $parts = preg_split('/(<\?php.*?\?>|<\?=.*?\?>)/s', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1) continue; // inside PHP
        // Strip HTML tags
        $text = preg_replace('/<[^>]*>/', "\n", $p);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Look for English-looking lines: contains letters a-z outside of entities
            if (preg_match('/[A-Za-z]{3,}/', $line) && preg_match('/^[A-Za-z][A-Za-z0-9 .,&\/\-\(\)\'\*\?!:]+$/', $line) && !preg_match('/\{|\$|__\(|e\(/', $line)) {
                $lines[] = sprintf("%s: %s", $file->getPathname(), $line);
            }
        }
    }
}
echo count($lines), " raw-english lines\n";
foreach (array_slice($lines, 0, 250) as $l) echo "  ", $l, "\n";
