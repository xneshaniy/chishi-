<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec("DELETE FROM staff_attendance_audit WHERE date = '2026-11-14'");
$pdo->exec("DELETE FROM staff_attendance WHERE date = '2026-11-14'");
echo "staff_attendance rows: ", $pdo->query('SELECT COUNT(*) FROM staff_attendance')->fetchColumn(), "\n";
