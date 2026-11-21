<?php
$file = __DIR__ . '/../database/schema.mysql.sql';
$content = file_get_contents($file);

// Remove all inline SQL comments (-- ...) but only on data definition lines 
// (preserve lines that are purely comments like ------ dividers)
// Strategy: remove inline comments on column definition lines only
$lines = explode("\n", $content);
$changed = 0;
foreach ($lines as &$line) {
    // If line contains a column definition (starts with whitespace + word) AND has -- comment
    if (preg_match('/^\s+\w/', $line) && strpos($line, '--') !== false) {
        // Remove the inline comment part
        $new = preg_replace('/\s*--[^\n]*$/', '', $line);
        if ($new !== $line) {
            $line = $new;
            $changed++;
        }
    }
}
unset($line);

$newContent = implode("\n", $lines);
file_put_contents($file, $newContent);
echo "Removed {$changed} inline SQL comments from column definitions!\n";
