<?php
// Scan view files for hardcoded Urdu text or mixed English/Urdu strings
$dir = __DIR__ . '/../app/views';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$found = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    
    // Look for patterns like English (اردو) or raw Urdu outside __()
    // Regex for Arabic/Urdu unicode range: \p{Arabic}
    preg_match_all('/[a-zA-Z\s]+\s*\([\p{Arabic}\s\-\/]+\)/u', $content, $matches1);
    preg_match_all('/[\p{Arabic}]+/u', $content, $matches2);
    
    $mixedCount = count($matches1[0] ?? []);
    $rawUrduCount = count($matches2[0] ?? []);
    
    if ($mixedCount > 0 || $rawUrduCount > 0) {
        $found[] = [
            'file' => str_replace(__DIR__ . '/../', '', $file->getPathname()),
            'mixed_examples' => array_unique(array_slice($matches1[0] ?? [], 0, 5)),
            'mixed_count' => $mixedCount,
            'raw_urdu_count' => $rawUrduCount
        ];
    }
}

echo "Found " . count($found) . " files with Urdu/mixed strings:\n";
foreach ($found as $f) {
    if ($f['mixed_count'] > 0) {
        echo "FILE: " . $f['file'] . " (Mixed: {$f['mixed_count']}, Urdu tokens: {$f['raw_urdu_count']})\n";
        echo "  Examples: " . implode(' | ', $f['mixed_examples']) . "\n";
    }
}
