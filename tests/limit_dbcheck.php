<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
echo "setting advance_max_amount: ", $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'advance_max_amount'")->fetchColumn(), "\n";
echo "advances today:\n";
foreach ($pdo->query("SELECT id, employee_id, amount, status, notes, requested_by, created_at FROM employee_advances ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) as $a) {
    echo "  id={$a['id']} amount={$a['amount']} status={$a['status']} notes={$a['notes']} by={$a['requested_by']} at={$a['created_at']}\n";
}
echo "approvals for advances:\n";
foreach ($pdo->query("SELECT reference_id, amount, status, reason FROM approval_requests WHERE reference_type = 'employee_advances' ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  ref={$r['reference_id']} amount={$r['amount']} status={$r['status']} reason={$r['reason']}\n";
}
