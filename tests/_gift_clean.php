<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
// Clean up leftover test gifts (GIFT-00001 from an aborted run) — production dev DB
$pdo->exec("DELETE FROM gifts WHERE gift_no = 'GIFT-00001'");
$pdo->exec("DELETE FROM approval_requests WHERE reference_type = 'gifts' AND reference_id = 1");
$pdo->exec("DELETE FROM audit_logs WHERE entity = 'gifts' AND entity_id = 1");
echo "gifts rows: " . (int)$pdo->query("SELECT COUNT(*) FROM gifts")->fetchColumn() . "\n";
echo "approval_requests gifts: " . (int)$pdo->query("SELECT COUNT(*) FROM approval_requests WHERE reference_type = 'gifts'")->fetchColumn() . "\n";
echo "audit gifts: " . (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity = 'gifts'")->fetchColumn() . "\n";
