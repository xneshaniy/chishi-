<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT p.code, GROUP_CONCAT(r.code, ', ') AS roles
    FROM permissions p
    JOIN role_permissions rp ON rp.permission_id = p.id
    JOIN roles r ON r.id = rp.role_id
    WHERE p.code IN ('payroll.employees.view','payroll.employees.create','payroll.employees.edit','payroll.attendance.mark','reports.reports.view','reports.view','reports.view')
    GROUP BY p.code") as $r) echo $r['code'], ' => ', $r['roles'], "\n";
// also dump raw join for any containing employees/attendance
foreach ($pdo->query("SELECT p.code, r.code AS role FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN roles r ON r.id=rp.role_id WHERE p.code LIKE '%employees%' OR p.code LIKE '%attendance%' ORDER BY p.code, r.code") as $r) echo $r['code'], ' => ', $r['role'], "\n";
