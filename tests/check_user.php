<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "users testaccttwo: ", $pdo->query("SELECT COUNT(*) FROM users WHERE username='testaccttwo'")->fetchColumn(), "\n";
echo "employees for that user: ", $pdo->query("SELECT COUNT(*) FROM employees e JOIN users u ON u.id = e.user_id WHERE u.username='testaccttwo'")->fetchColumn(), "\n";
echo "total employees: ", $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn(), "\n";
