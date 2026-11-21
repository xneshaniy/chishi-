<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
echo "approval_requests:\n";
foreach ($pdo->query("SELECT id, reference_type, reference_id, amount, status, approver_id, decision_note FROM approval_requests ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) as $r) print_r($r);
echo "payroll_payments:\n";
foreach ($pdo->query("SELECT id, payroll_id, amount, status, journal_entry_id FROM payroll_payments ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) as $r) print_r($r);
echo "payroll:\n";
foreach ($pdo->query("SELECT id, employee_id, period_start, net_pay, amount_paid, status FROM payroll ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) as $r) print_r($r);
