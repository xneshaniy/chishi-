<?php
$viewsDir = __DIR__ . '/../app/views';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
$found = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $lines = file($file->getPathname());
    $fileMatches = [];
    foreach ($lines as $lineNum => $line) {
        // Check if line contains Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $line)) {
            $fileMatches[] = [
                'line' => $lineNum + 1,
                'text' => trim($line)
            ];
        }
    }
    if (!empty($fileMatches)) {
        $found[$file->getPathname()] = $fileMatches;
    }
}

echo "Total view files containing Arabic/Urdu: " . count($found) . "\n\n";
foreach ($found as $path => $matches) {
    $rel = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    echo "=== {$rel} (" . count($matches) . " lines) ===\n";
    foreach (array_slice($matches, 0, 15) as $m) {
        echo "  L{$m['line']}: {$m['text']}\n";
    }
    if (count($matches) > 15) {
        echo "  ... and " . (count($matches) - 15) . " more lines\n";
    }
    echo "\n";
}
