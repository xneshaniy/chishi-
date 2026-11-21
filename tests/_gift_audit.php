<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "--- audit rows for entity=gifts (latest)\n";
foreach ($pdo->query("SELECT id, action, entity_id, details FROM audit_logs WHERE entity IN ('gifts','approval_requests') AND details LIKE '%gift%' ORDER BY id DESC LIMIT 30") as $r) print_r($r);
echo "--- gifts table rows\n";
foreach ($pdo->query("SELECT id, gift_no, gift_type, status FROM gifts ORDER BY id DESC LIMIT 10") as $r) print_r($r);
echo "--- approval_requests last 5 (gifts)\n";
foreach ($pdo->query("SELECT id, request_type, reference_type, reference_id, status FROM approval_requests WHERE reference_type = 'gifts' ORDER BY id DESC LIMIT 5") as $r) print_r($r);
