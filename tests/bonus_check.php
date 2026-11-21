<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT sii.id, i.issue_no, i.date, sii.bonus_qty, sii.bonus_cost, i.bonus_reason, i.issue_type, i.status FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = 2 AND sii.bonus_qty > 0 ORDER BY i.id DESC LIMIT 5") as $r) print_r($r);
