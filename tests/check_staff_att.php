<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query('SELECT employee_id, date, status, marked_by, created_at FROM staff_attendance') as $r)
    echo "staff_attendance: emp={$r['employee_id']} date={$r['date']} status={$r['status']} marked_by={$r['marked_by']} at={$r['created_at']}\n";
foreach ($pdo->query('SELECT employee_id, date, old_status, new_status, changed_by, note FROM staff_attendance_audit') as $r)
    echo "audit: emp={$r['employee_id']} date={$r['date']} {$r['old_status']}->{$r['new_status']} by={$r['changed_by']} note={$r['note']}\n";
