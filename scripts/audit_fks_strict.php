<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');
$lines = explode("\n", $sql);

$currentTable = null;
$cols = [];
$fks = [];
$errors = [];

foreach ($lines as $num => $line) {
    $line = trim($line);
    if (preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $line, $m)) {
        $currentTable = $m[1];
        $cols[$currentTable] = [];
        continue;
    }
    
    if (!$currentTable) continue;
    
    if (preg_match('/^\)\s*ENGINE/i', $line)) {
        $currentTable = null;
        continue;
    }
    
    // Column: col_name TYPE ...
    if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([A-Z0-9_\(\)]+)(.*)$/i', $line, $cm)) {
        $name = $cm[1];
        if (!in_array(strtoupper($name), ['CONSTRAINT', 'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'])) {
            $isNotNull = (strpos(strtoupper($cm[3]), 'NOT NULL') !== false);
            $cols[$currentTable][$name] = [
                'type' => $cm[2],
                'not_null' => $isNotNull,
                'line_num' => $num + 1,
                'raw' => $line
            ];
        }
    }
    
    // Foreign key:
    if (preg_match('/FOREIGN KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(.*)$/i', $line, $fm)) {
        $fks[] = [
            'table' => $currentTable,
            'col' => $fm[1],
            'ref_table' => $fm[2],
            'ref_col' => $fm[3],
            'actions' => strtoupper($fm[4]),
            'line_num' => $num + 1,
            'raw' => $line
        ];
    }
}

foreach ($fks as $fk) {
    $t = $fk['table'];
    $c = $fk['col'];
    if (isset($cols[$t][$c])) {
        if ($cols[$t][$c]['not_null'] && strpos($fk['actions'], 'SET NULL') !== false) {
            $errors[] = "Line {$fk['line_num']}: Table `{$t}`: Column `{$c}` is NOT NULL (defined line {$cols[$t][$c]['line_num']}), but FK has SET NULL!";
        }
    }
}

echo "Total tables parsed: " . count($cols) . "\n";
echo "Total foreign keys parsed: " . count($fks) . "\n";
echo "ERRORS FOUND: " . count($errors) . "\n";
foreach ($errors as $e) {
    echo "  " . $e . "\n";
}
