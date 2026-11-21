<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');

// Extract all CREATE TABLE statements
preg_match_all('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/si', $sql, $matches, PREG_SET_ORDER);

$tables = [];

foreach ($matches as $m) {
    $tableName = $m[1];
    $body = $m[2];
    $tables[$tableName] = [
        'columns' => [],
        'fks' => []
    ];
    
    // Split lines
    $lines = explode("\n", $body);
    foreach ($lines as $line) {
        $line = trim($line);
        // Column definition
        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([A-Z0-9_\(\)]+)(.*)$/i', $line, $cm)) {
            $colName = $cm[1];
            if (in_array(strtoupper($colName), ['CONSTRAINT', 'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'])) {
                continue;
            }
            $rest = strtoupper($cm[3]);
            $isNotNull = (strpos($rest, 'NOT NULL') !== false);
            $tables[$tableName]['columns'][$colName] = [
                'type' => $cm[2],
                'not_null' => $isNotNull,
                'raw' => $line
            ];
        }
        
        // FK definition
        if (preg_match('/CONSTRAINT\s+`?([a-zA-Z0-9_]+)`?\s+FOREIGN KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(.*)$/i', $line, $fm)) {
            $tables[$tableName]['fks'][] = [
                'constraint' => $fm[1],
                'col' => $fm[2],
                'ref_table' => $fm[3],
                'ref_col' => $fm[4],
                'actions' => strtoupper($fm[5]),
                'raw' => $line
            ];
        }
    }
}

$issues = [];
foreach ($tables as $tName => $tData) {
    foreach ($tData['fks'] as $fk) {
        $col = $fk['col'];
        $refTable = $fk['ref_table'];
        $refCol = $fk['ref_col'];
        $actions = $fk['actions'];
        
        // 1. Check if column exists in table
        if (!isset($tData['columns'][$col])) {
            $issues[] = "Table `{$tName}`: FK references column `{$col}` which does not exist in table!";
            continue;
        }
        
        // 2. Check if column is NOT NULL but action is SET NULL
        if ($tData['columns'][$col]['not_null'] && strpos($actions, 'SET NULL') !== false) {
            $issues[] = "Table `{$tName}`: Column `{$col}` is NOT NULL, but FK has SET NULL! Line: {$fk['raw']}";
        }
        
        // 3. Check if ref table exists
        if (!isset($tables[$refTable])) {
            $issues[] = "Table `{$tName}`: Ref table `{$refTable}` does not exist!";
        }
    }
}

echo "TOTAL TABLES PARSED: " . count($tables) . "\n";
echo "ISSUES FOUND: " . count($issues) . "\n";
foreach ($issues as $issue) {
    echo " - " . $issue . "\n";
}
