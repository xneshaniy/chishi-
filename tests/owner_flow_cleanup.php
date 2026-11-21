<?php
/** Reverse the Rs 1 automated render-check entries (dev cleanup). Uses app bootstrap. */
$GLOBALS['CONFIG'] = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/bootstrap.php';
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$jid = (int)$pdo->query("SELECT id FROM journal_entries WHERE memo LIKE '%automated render check%' AND reference_type='capital_investment' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($jid) {
    $result = Accounting::reverseEntry($jid, 'Automated render-check cleanup');
    echo "capital investment journal #$jid reversed (new entry #$result)\n";
} else {
    echo "no capital entry found\n";
}
$iid = (int)$pdo->query("SELECT id FROM other_income WHERE description LIKE '%automated render check%' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($iid) {
    $jid2 = (int)$pdo->query("SELECT journal_entry_id FROM other_income WHERE id = $iid")->fetchColumn();
    if ($jid2) {
        $result = Accounting::reverseEntry($jid2, 'Automated render-check cleanup');
        echo "income journal #$jid2 reversed (new entry #$result)\n";
    }
    $pdo->exec("DELETE FROM approval_requests WHERE reference_type='other_income' AND reference_id = $iid");
    $pdo->exec("DELETE FROM other_income WHERE id = $iid");
    echo "other_income test row deleted\n";
} else {
    echo "no income entry found\n";
}
