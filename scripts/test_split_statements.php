<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');
$statements = preg_split('/;\s*(\r?\n|$)/', $sql);

echo "Total statements: " . count($statements) . "\n";
$emptyCount = 0;
$createTableCount = 0;
$alterTableCount = 0;
$otherCount = 0;

$tableNames = [];

foreach ($statements as $idx => $st) {
    $st = trim($st);
    if ($st === '') {
        $emptyCount++;
        continue;
    }
    if (preg_match('/^CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $st, $m)) {
        $createTableCount++;
        $tableNames[] = $m[1];
    } elseif (preg_match('/^ALTER TABLE/i', $st)) {
        $alterTableCount++;
    } else {
        $otherCount++;
        echo "Other statement at index {$idx}: " . substr($st, 0, 60) . "...\n";
    }
}

echo "CREATE TABLE: {$createTableCount}\n";
echo "ALTER TABLE: {$alterTableCount}\n";
echo "Other: {$otherCount}\n";
echo "Empty: {$emptyCount}\n";
