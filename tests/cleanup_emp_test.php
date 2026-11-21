<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("DELETE FROM staff_attendance_audit WHERE employee_id = 2");
$pdo->exec("DELETE FROM staff_attendance WHERE employee_id = 2");
$pdo->exec("DELETE FROM employee_documents WHERE employee_id = 2");
$pdo->exec("DELETE FROM employees WHERE id = 2");
echo "employees remaining: ", $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(), "\n";
