<?php
$sql = file_get_contents(__DIR__ . '/../database/schema.sqlite.sql');
$p = new PDO('sqlite::memory:');
$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmts = preg_split('/;\s*\n/', $sql);
foreach ($stmts as $i => $s) {
    $s = trim($s);
    if ($s === '') continue;
    try {
        $p->exec($s);
    } catch (Throwable $e) {
        echo "STMT #$i FAILED: ", $e->getMessage(), "\n---\n", substr($s, 0, 400), "\n";
        exit(1);
    }
}
echo "ALL STATEMENTS OK\n";
