<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');

// Split tables
preg_match_all('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/si', $sql, $matches, PREG_SET_ORDER);

$errors = [];

foreach ($matches as $m) {
    $tableName = $m[1];
    $body = $m[2];
    
    // Find all columns and their NOT NULL status
    $lines = explode("\n", $body);
    $cols = [];
    $fks = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([A-Z0-9_\(\)]+)(.*?)(?:,|$)/i', $line, $colMatch)) {
            $colName = $colMatch[1];
            if (in_array(strtoupper($colName), ['CONSTRAINT', 'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN'])) {
                continue;
            }
            $rest = strtoupper($colMatch[3]);
            $isNotNull = strpos($rest, 'NOT NULL') !== false;
            $cols[$colName] = [
                'type' => $colMatch[2],
                'not_null' => $isNotNull,
                'line' => $line
            ];
        }
        
        if (preg_match('/FOREIGN KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(.*?)(?:,|$)/i', $line, $fkMatch)) {
            $fks[] = [
                'col' => $fkMatch[1],
                'refTable' => $fkMatch[2],
                'refCol' => $fkMatch[3],
                'actions' => strtoupper($fkMatch[4]),
                'line' => $line
            ];
        }
    }
    
    foreach ($fks as $fk) {
        $col = $fk['col'];
        if (isset($cols[$col])) {
            if ($cols[$col]['not_null'] && strpos($fk['actions'], 'SET NULL') !== false) {
                $errors[] = "Table `{$tableName}`: column `{$col}` is NOT NULL, but foreign key has ON DELETE/UPDATE SET NULL! ({$fk['line']})";
            }
        }
    }
}

echo "Found " . count($errors) . " foreign key errors:\n";
foreach ($errors as $err) {
    echo " - " . $err . "\n";
}
