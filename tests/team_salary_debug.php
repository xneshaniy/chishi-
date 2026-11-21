<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT e.id, e.code, e.salary_rate, e.salary_type, e.user_id, u.username FROM employees e JOIN users u ON u.id = e.user_id WHERE u.username = 'testaccttwo'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
echo "count testaccttwo emp: ", $pdo->query("SELECT COUNT(*) FROM employees e JOIN users u ON u.id = e.user_id WHERE u.username = 'testaccttwo'")->fetchColumn(), "\n";
