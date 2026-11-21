<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query('SELECT id, code, name, designation, department, join_date, phone FROM employees ORDER BY id') as $r)
    echo "id={$r['id']} code={$r['code']} name={$r['name']} desig={$r['designation']} dept={$r['department']} join={$r['join_date']} phone={$r['phone']}\n";
echo "documents: ", $pdo->query('SELECT COUNT(*) FROM employee_documents')->fetchColumn(), "\n";
