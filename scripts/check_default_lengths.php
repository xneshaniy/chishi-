<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');
$lines = explode("\n", $sql);

$errors = [];
foreach ($lines as $lineNum => $line) {
    // match: col_name VARCHAR(N) ... DEFAULT 'value'
    if (preg_match('/`?([a-zA-Z0-9_]+)`?\s+VARCHAR\((\d+)\)(.*?)\s+DEFAULT\s+\'([^\']+)\'/i', $line, $m)) {
        $col = $m[1];
        $len = (int)$m[2];
        $val = $m[4];
        $valLen = strlen($val);
        if ($valLen > $len) {
            $errors[] = "Line " . ($lineNum + 1) . ": Column `{$col}` VARCHAR({$len}) has default '{$val}' of length {$valLen}!";
        }
    }
}

echo "Found " . count($errors) . " default value length errors:\n";
foreach ($errors as $e) {
    echo " - " . $e . "\n";
}
