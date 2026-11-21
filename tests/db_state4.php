<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "POs:\n";
foreach ($db->query("SELECT po_no, status, total FROM purchase_orders") as $r) echo $r['po_no'], ' | ', $r['status'], ' | ', $r['total'], "\n";
echo "\napprovals:\n";
foreach ($db->query("SELECT id, request_type, reference_type, reference_id, status, amount FROM approval_requests ORDER BY id DESC LIMIT 5") as $r) echo $r['id'], ' | ', $r['request_type'], ' | ', $r['reference_type'], '/', $r['reference_id'], ' | ', $r['status'], ' | ', $r['amount'], "\n";
echo "\nnotifications:\n";
foreach ($db->query("SELECT id, type, user_id, title FROM notifications ORDER BY id DESC LIMIT 8") as $r) echo $r['id'], ' | ', $r['type'], ' | user ', $r['user_id'], ' | ', $r['title'], "\n";
