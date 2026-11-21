<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "records:\n";
foreach ($db->query("SELECT id, record_no, created_by, status, expense_id FROM maintenance_records") as $r) {
    echo $r['id'], ' | ', $r['record_no'], ' | created_by=', $r['created_by'], ' | ', $r['status'], ' | expense=', $r['expense_id'], "\n";
}
echo "approval requests:\n";
foreach ($db->query("SELECT id, request_type, reference_id, requested_by, status FROM approval_requests WHERE request_type='maintenance'") as $r) {
    echo $r['id'], ' | ', $r['request_type'], '/', $r['reference_id'], ' | by=', $r['requested_by'], ' | ', $r['status'], "\n";
}
