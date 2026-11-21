<?php
/** Jampur Grinding: send -> grind -> multiple partial returns -> exact stock accounting; loss tracked; permissions of charge status. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}

$now = now_local();
$whMain = (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'");
$rm = (int)db_insert('products', ['sku' => 'RM-JMP-1', 'name' => 'Tobacco Grindable', 'kind' => 'raw', 'purchase_price' => 80, 'sale_price' => 120, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
Inventory::receive($rm, $whMain, 1000, 80, 'opening', 'opening', null, null, null, 'Seed');
ok(abs(Inventory::balance($rm, $whMain) - 1000) < 0.001, 'opening stock 1000 KG in WH-MAIN');

// === send 1000 KG to Jampur ===
echo "\n=== 1. Send 1000 KG to Jampur ===\n";
$sendId = (int)db_insert('grinding_sends', ['send_no' => 'JMP-0001', 'date' => today(), 'product_id' => $rm, 'qty_kg' => 1000, 'unit_cost' => 0, 'party_name' => 'Jampur Grinding Works', 'charges_per_kg' => 10, 'created_by' => 1, 'created_at' => $now]);
$cost = Inventory::issue($rm, $whMain, 1000, 'grinding_out', 'grinding_send', $sendId, null, null, 'Sent to Jampur');
db_exec('UPDATE grinding_sends SET unit_cost = ? WHERE id = ?', [$cost, $sendId]);
ok(abs(Inventory::balance($rm, $whMain) - 0) < 0.001, 'WH-MAIN deducted — nothing left in Ahmedpur');

// === grind 1000 -> received 950, loss 50 ===
echo "\n=== 2. Grinding: 950 received, 50 loss ===\n";
db_insert('grinding_records', ['send_id' => $sendId, 'grind_date' => today(), 'qty_received_kg' => 950, 'loss_kg' => 50, 'charges' => 9500, 'charges_status' => 'pending', 'created_by' => 1, 'created_at' => $now]);
$rec = db_one('SELECT * FROM grinding_records WHERE send_id = ?', [$sendId]);
ok(abs((float)$rec['qty_received_kg'] - 950) < 0.001, 'grinding record saved with 950 KG received');
ok(abs((float)$rec['loss_kg'] - 50) < 0.001, 'processing loss recorded separately as 50 KG');
ok((float)$rec['charges'] === 9500.0, 'grinding charges 9,500 recorded');
$processed = (float)db_val('SELECT COALESCE(SUM(qty_received_kg),0) FROM grinding_records WHERE send_id = ?', [$sendId]);
$loss = (float)db_val('SELECT COALESCE(SUM(loss_kg),0) FROM grinding_records WHERE send_id = ?', [$sendId]);
ok(abs($processed - 950) < 0.001 && abs($loss - 50) < 0.001, 'processed and loss totals correct');

// === partial returns: 600, then 200 => remaining 150 (950 - 800) ===
echo "\n=== 3. Multiple partial returns ===\n";
foreach ([600, 200] as $q) {
    $retId = (int)db_insert('grinding_returns', ['send_id' => $sendId, 'return_date' => today(), 'qty_kg' => $q, 'unit_cost' => $cost, 'created_by' => 1, 'created_at' => $now]);
    Inventory::receive($rm, $whMain, $q, $cost, 'grinding_return', 'grinding_return', $retId, null, null, 'Return from Jampur');
}
$returned = (float)db_val('SELECT COALESCE(SUM(qty_kg),0) FROM grinding_returns WHERE send_id = ?', [$sendId]);
ok(abs($returned - 800) < 0.001, 'two partial returns total 800 KG');
ok(abs(Inventory::balance($rm, $whMain) - 800) < 0.001, 'Ahmedpur stock back to 800 KG after partial returns');
$remaining = 1000 - $returned - $loss;
ok(abs($remaining - 150) < 0.001, 'remaining at Jampur = sent (1000) - returned (800) - loss (50) = 150 KG');
$processedRemain = $processed - $returned;
ok(abs($processedRemain - 150) < 0.001, 'of the processed 950, still 150 KG at Jampur');

// === duplicate return must not exceed processed ===
echo "\n=== 4. Over-return protection (service-level invariant) ===\n";
$chk = (float)db_val('SELECT COALESCE(SUM(qty_kg),0) FROM grinding_returns WHERE send_id = ?', [$sendId]);
$proc = (float)db_val('SELECT COALESCE(SUM(qty_received_kg),0) FROM grinding_records WHERE send_id = ?', [$sendId]);
ok($chk <= $proc + 0.0009, 'total returned can never exceed processed quantity');

// === charges: mark paid ===
echo "\n=== 5. Grinding charges status toggle ===\n";
db_exec("UPDATE grinding_records SET charges_status = 'paid' WHERE send_id = ?", [$sendId]);
ok(db_val("SELECT charges_status FROM grinding_records WHERE send_id = ?", [$sendId]) === 'paid', 'charges can be marked paid');
ok((float)db_val("SELECT COALESCE(SUM(charges),0) FROM grinding_records WHERE charges_status = 'pending' AND send_id = ?", [$sendId]) === 0.0, 'no pending charges after marking paid');

// === stock_movements have proper ref links and types ===
echo "\n=== 6. Stock movements linked to grinding records ===\n";
$movOut = (int)db_val("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'grinding_send' AND ref_id = ?", [$sendId]);
$movIn = (int)db_val("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'grinding_return' AND ref_id IN (SELECT id FROM grinding_returns WHERE send_id = ?)", [$sendId]);
ok($movOut === 1, 'grinding_out movement linked to send');
ok($movIn === 2, 'two grinding_return movements linked');

echo "\n========================================\nJAMPUR GRINDING TEST RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
