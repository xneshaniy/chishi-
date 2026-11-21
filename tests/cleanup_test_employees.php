<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$rows = $pdo->query("SELECT id, code, name FROM employees WHERE name LIKE 'TEST Photo Employee%'")->fetchAll(PDO::FETCH_ASSOC);
echo "rows to remove: " . count($rows) . "\n";
@mkdir($null, 0777, true);
foreach ($rows as $r) {
    $id = (int)$r['id'];
    $dir = dirname(__DIR__) . '/storage/uploads/employees/' . $id;
    if (is_dir($dir)) { foreach (glob($dir . '/*') as $f) @unlink($f); @rmdir($dir); }
    $pdo->exec('PRAGMA foreign_keys = ON');
    // child docs first (none expected — test employees had none), then the employee row
    $pdo->exec("DELETE FROM employee_documents WHERE employee_id = $id");
    $pdo->exec("DELETE FROM employees WHERE id = $id");
    echo 'removed ', $id, ' ', $r['code'], ' ', $r['name'], "\n";
}
echo "remaining TEST rows: ", $pdo->query("SELECT COUNT(*) FROM employees WHERE name LIKE 'TEST Photo Employee%'")->fetchColumn(), "\n";
