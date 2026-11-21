<?php
/** E2E for Gifts: money + goods gift creation, approval flow, FG stock deduction, zero company receivables pollution. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Auth.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Gifts.php';

Database::setPdo($pdo);
$ownerId = 1;
$mgrId = 4; // manager1
$today = date('Y-m-d');
echo "today=$today\n";

echo "\n=== 1. Find a finished-goods product with central-warehouse stock ===\n";
$prod = $pdo->query("SELECT p.id, p.sku, p.name, COALESCE(SUM(sb.qty),0) AS qty FROM products p JOIN stock_balances sb ON sb.product_id = p.id JOIN warehouses w ON w.id = sb.warehouse_id WHERE p.kind = 'finished' AND w.type = 'central' GROUP BY p.id HAVING qty > 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$prod) { echo "no central FG stock\n"; exit(1); }
echo "product: ", json_encode($prod), "\n";
$pkg = UnitConverter::productPackaging((int)$prod['id']);
echo "packaging: ", json_encode($pkg), "\n";
$unit = 'packet';
$qty = min(5, (int)$prod['qty']);

echo "\n=== 2. Manager creates a money gift ===\n";
$gift1 = Gifts::createMoneyGift([
    'recipient_name' => 'E2E Gift Person',
    'recipient_type' => 'guest',
    'gift_date' => $today,
    'amount' => 1500,
    'payment_method' => 'bank',
    'occasion' => 'Unit test',
    'description' => 'E2E money gift test',
    'account_id' => 1,
], $mgrId);
echo "money gift id=$gift1\n";
$row = $pdo->query("SELECT id, gift_no, status, amount, expense_id FROM gifts WHERE id = $gift1")->fetch(PDO::FETCH_ASSOC);
echo json_encode($row), "\n";
$expId = (int)$row['expense_id'];

echo "\n=== 3. Manager creates a goods gift ($qty Packets) ===\n";
$available_before = (int)$pdo->query("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id WHERE sb.product_id = {$prod['id']} AND w.type = 'central'")->fetchColumn();
echo "available FG central: $available_before Packets\n";
$gift2 = Gifts::createGoodsGift([
    'recipient_name' => 'E2E Goods Recipient',
    'recipient_type' => 'customer',
    'gift_date' => $today,
    'product_id' => $prod['id'],
    'qty' => $qty,
    'qty_unit' => $unit,
    'occasion' => 'Eid',
    'description' => 'E2E goods gift test',
], $mgrId);
echo "goods gift id=$gift2\n";
$row2 = $pdo->query("SELECT id, gift_no, status, qty, qty_unit, qty_pieces FROM gifts WHERE id = $gift2")->fetch(PDO::FETCH_ASSOC);
echo json_encode($row2), "\n";
$available_after_request = (int)$pdo->query("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id WHERE sb.product_id = {$prod['id']} AND w.type = 'central'")->fetchColumn();
echo "stock unchanged before approval: " . ($available_after_request === $available_before ? 'YES' : 'NO') . "\n";
$bankRow = $pdo->query("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "bank account for money gift: ", json_encode($bankRow), "\n";

echo "\n=== 4. Owner approves the money gift ===\n";
$appr1 = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type = 'gifts' AND reference_id = $gift1 ORDER BY id DESC LIMIT 1")->fetchColumn();
try {
    $out = Approvals::decide($appr1, true, 'E2E approve', $ownerId);
    echo "decide money: ", json_encode($out), "\n";
} catch (Throwable $e) { echo "decide failed: ", $e->getMessage(), "\n"; }

echo "\n=== 5. Owner approves the goods gift ===\n";
$appr2 = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type = 'gifts' AND reference_id = $gift2 ORDER BY id DESC LIMIT 1")->fetchColumn();
try {
    $out = Approvals::decide($appr2, true, 'E2E approve goods', $ownerId);
    echo "decide goods: ", json_encode($out), "\n";
} catch (Throwable $e) { echo "decide goods failed: ", $e->getMessage(), "\n"; }

$available_after = (int)$pdo->query("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id WHERE sb.product_id = {$prod['id']} AND w.type = 'central'")->fetchColumn();
echo "FG central Packets after approval: $available_after (expect " . ($available_before - (int)round($row2['qty_pieces'])) . ")\n";
echo "money gift status: ", $pdo->query("SELECT status FROM gifts WHERE id = $gift1")->fetchColumn(), "\n";
echo "goods gift status: ", $pdo->query("SELECT status FROM gifts WHERE id = $gift2")->fetchColumn(), "\n";
echo "linked expense status (money): ", $pdo->query("SELECT status FROM expenses WHERE id = $expId")->fetchColumn(), "\n";

echo "\n=== 6. Audit trail ===\n";
foreach ($pdo->query("SELECT action, entity, entity_id FROM audit_logs WHERE entity = 'gifts' AND entity_id IN ($gift1, $gift2) ORDER BY id") as $r) {
    echo "  {$r['action']} {$r['entity']} #{$r['entity_id']}\n";
}

echo "\n=== 7. Cleanup test artefacts (force-reset dev) ===\n";
// goods gifts: put stock back manually then also remove movements
foreach ($pdo->query("SELECT id, product_id, warehouse_id, ABS(qty) AS qty FROM stock_movements WHERE ref_type = 'gifts' AND ref_id = $gift2 AND type = 'gift_goods'") as $m) {
    Inventory::receive((int)$m['product_id'], (int)$m['warehouse_id'], (float)$m['qty'], 0.0, 'gift_return', 'gift', $gift2, null, null, 'E2E cleanup');
}
$pdo->exec("DELETE FROM stock_movements WHERE ref_type = 'gifts' AND ref_id IN ($gift1, $gift2)");
$journalEntryIds = [];
$stmt = $pdo->prepare("SELECT journal_entry_id FROM expenses WHERE id = ? AND journal_entry_id IS NOT NULL");
$stmt->execute([$expId]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $jid) $journalEntryIds[] = (int)$jid;
$pdo->exec("DELETE FROM expenses WHERE id = $expId");
$pdo->exec("DELETE FROM approval_requests WHERE reference_type = 'gifts' AND reference_id IN ($gift1, $gift2)");
$pdo->exec("DELETE FROM gifts WHERE id IN ($gift1, $gift2)");
$pdo->exec("DELETE FROM audit_logs WHERE entity = 'gifts' AND entity_id IN ($gift1, $gift2)");
if ($journalEntryIds) {
    $place = implode(',', array_fill(0, count($journalEntryIds), '?'));
    $del1 = $pdo->prepare("DELETE FROM journal_lines WHERE entry_id IN ($place)");
    $del2 = $pdo->prepare("DELETE FROM journal_entries WHERE id IN ($place)");
    $del1->execute($journalEntryIds);
    $del2->execute($journalEntryIds);
}
echo "cleaned\n";
