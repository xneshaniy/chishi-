<?php
/** Seed a few DEMO labour workers into dev.sqlite (idempotent by code). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$demos = [
    ['LAB-0001', 'DEMO Labour One', '03001234567', 'Machine Operator', 'Ahmedpur Factory', '2024-03-01', 'daily', 1200],
    ['LAB-0002', 'DEMO Labour Two', '03007654321', 'Loader', 'Ahmedpur Factory', '2024-06-15', 'monthly', 28000],
    ['LAB-0003', 'DEMO Labour Three', '03009876543', 'Packer', 'Ahmedpur Factory', '2025-01-10', 'monthly', 25000],
];
$ins = $pdo->prepare("INSERT INTO employees (code, name, phone, designation, department, join_date, kind, salary_type, salary_rate, is_active, created_at)
    SELECT ?, ?, ?, ?, ?, ?, 'labour', ?, ?, 1, datetime('now')
    WHERE NOT EXISTS (SELECT 1 FROM employees WHERE code = ?)");
foreach ($demos as $d) {
    [$code, $name, $phone, $desig, $dept, $join, $st, $rate] = $d;
    $ins->execute([$code, $name, $phone, $desig, $dept, $join, $st, $rate, $code]);
}
foreach ($pdo->query("SELECT id, code, name, kind, salary_type, salary_rate FROM employees WHERE kind='labour' ORDER BY code") as $r) {
    echo $r['id'], ' | ', $r['code'], ' | ', $r['name'], ' | ', $r['kind'], ' | ', $r['salary_type'], ' | ', $r['salary_rate'], PHP_EOL;
}
