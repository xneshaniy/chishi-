<?php
// Compare tables and columns between schema.sqlite.sql and schema.mysql.sql
$sqliteSql = file_get_contents(__DIR__ . '/../database/schema.sqlite.sql');
$mysqlSql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');

function parseSchema($sql) {
    $tables = [];
    $lines = explode("\n", $sql);
    $currentTable = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $line, $m)) {
            $currentTable = $m[1];
            $tables[$currentTable] = [];
            continue;
        }
        if (!$currentTable) continue;
        if (preg_match('/^\)\s*(?:ENGINE|;)/i', $line)) {
            $currentTable = null;
            continue;
        }
        // Column line
        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([A-Z0-9_\(\)]+)(.*)$/i', $line, $cm)) {
            $colName = $cm[1];
            if (!in_array(strtoupper($colName), ['CONSTRAINT', 'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'])) {
                $tables[$currentTable][$colName] = $cm[2] . ' ' . trim($cm[3]);
            }
        }
        // Also check if multiple columns on same line (e.g. SQLite dump with comma)
        if (preg_match_all('/,\s*`?([a-zA-Z0-9_]+)`?\s+(DECIMAL|VARCHAR|INT|INTEGER|DATETIME|DATE|TEXT|TINYINT)/i', $line, $multi, PREG_SET_ORDER)) {
            foreach ($multi as $mc) {
                if (!in_array(strtoupper($mc[1]), ['CONSTRAINT', 'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'])) {
                    $tables[$currentTable][$mc[1]] = $mc[2];
                }
            }
        }
    }
    return $tables;
}

$sqliteTables = parseSchema($sqliteSql);
$mysqlTables = parseSchema($mysqlSql);

echo "SQLite tables: " . count($sqliteTables) . "\n";
echo "MySQL tables: " . count($mysqlTables) . "\n\n";

$missingColsInMysql = [];

foreach ($sqliteTables as $t => $cols) {
    if (!isset($mysqlTables[$t])) {
        echo "Table `{$t}` exists in SQLite but NOT in MySQL!\n";
        continue;
    }
    foreach ($cols as $col => $def) {
        if (!isset($mysqlTables[$t][$col])) {
            $missingColsInMysql[] = "Table `{$t}`: missing column `{$col}` ({$def})";
        }
    }
}

echo "=== MISSING COLUMNS IN MYSQL (" . count($missingColsInMysql) . ") ===\n";
foreach ($missingColsInMysql as $m) {
    echo " - " . $m . "\n";
}
