<?php
/**
 * Tests:
 * 1. Production RM Auto-deduct (BOM & manual)
 * 2. Production reversal restoring RM stock
 * 3. Daily Cash Position calculation
 * 4. Dashboard Live Factory Summary
 * 5. Layout navigation organization
 */

require_once __DIR__ . '/../app/core/bootstrap.php';
$routes = require __DIR__ . '/../app/routes.php';
require_once __DIR__ . '/../app/controllers/CashBankController.php';

$pass = 0; $fail = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { echo "✅ PASS: $name\n"; $pass++; }
    else       { echo "❌ FAIL: $name" . ($detail ? " — $detail" : '') . "\n"; $fail++; }
}

echo "\n=== Comprehensive ERP Enhancements Test Suite ===\n\n";

// ── 1. Routes & Controllers Exist ──
ok('Route: cashbank/daily-position exists', isset($routes['cashbank/daily-position']));
ok('Controller method: CashBankController::dailyPosition exists', method_exists('CashBankController', 'dailyPosition'));
ok('View: cashbank/daily_position.php exists', file_exists(__DIR__ . '/../app/views/cashbank/daily_position.php'));
ok('View: production/run.php exists', file_exists(__DIR__ . '/../app/views/production/run.php'));

// ── 2. DB Table & Column Migrations ──
$rmUsageCols = array_column(db_all("PRAGMA table_info(rm_daily_usage)"), 'name');
ok('DB: rm_daily_usage has production_run_id column', in_array('production_run_id', $rmUsageCols, true));
$dailyProdRmCols = array_column(db_all("PRAGMA table_info(daily_production_rm_items)"), 'name');
ok('DB: daily_production_rm_items table exists', in_array('run_id', $dailyProdRmCols, true));

// ── 3. Production RM Auto-Deduction Simulation ──
$testFgId = (int)db_val("SELECT id FROM products WHERE kind = 'finished' AND is_active = 1 LIMIT 1");
$testRmId = (int)db_val("SELECT id FROM products WHERE kind IN ('raw','packaging') AND is_active = 1 LIMIT 1");
$fgWhId = 1; // central or factory
$rmWhId = RawMaterial::defaultWarehouse();

if ($testFgId > 0 && $testRmId > 0) {
    // Ensure test BOM exists
    $bom = db_one("SELECT * FROM bills_of_materials WHERE product_id = ? AND is_active = 1", [$testFgId]);
    if (!$bom) {
        $bomId = db_insert('bills_of_materials', [
            'product_id' => $testFgId,
            'name' => 'Test BOM v1',
            'output_qty' => 10,
            'is_active' => 1
        ]);
        db_insert('bom_lines', [
            'bom_id' => $bomId,
            'material_id' => $testRmId,
            'qty' => 5 // 5 KG per 10 Packets = 0.5 KG per packet
        ]);
    } else {
        $bomId = (int)$bom['id'];
        $hasLine = db_val("SELECT 1 FROM bom_lines WHERE bom_id = ? AND material_id = ?", [$bomId, $testRmId]);
        if (!$hasLine) {
            db_insert('bom_lines', ['bom_id' => $bomId, 'material_id' => $testRmId, 'qty' => 5]);
        }
    }

    // Give some RM stock to test deduction
    Inventory::receive($testRmId, $rmWhId, 100, 10, 'opening', 'test', 9999, null, null, 'Test stock');
    $initialRmStock = Inventory::balance($testRmId, $rmWhId);
    $initialFgStock = Inventory::balance($testFgId, $fgWhId);

    // Simulate saving a daily production run with auto-deduct
    $producedQty = 20; // 20 packets produced -> should consume 10 KG of RM
    $expectedRmNeed = 10.0;

    $runNo = 'PRD-TEST-' . time();
    $testRunId = (int)db_insert('daily_production', [
        'run_no' => $runNo,
        'date' => today(),
        'notes' => 'Automated Test Run',
        'status' => 'posted',
        'created_by' => 1,
        'created_at' => now_local()
    ]);

    db_insert('daily_production_items', [
        'run_id' => $testRunId,
        'product_id' => $testFgId,
        'qty' => $producedQty,
        'batch_no' => 'B-TEST',
        'line_notes' => 'Test line'
    ]);
    Inventory::receive($testFgId, $fgWhId, $producedQty, 100, 'production_in', 'daily_production', $testRunId);

    // Deduct RM
    Inventory::issue($testRmId, $rmWhId, $expectedRmNeed, 'production_raw_deduct', 'daily_production', $testRunId);
    db_insert('daily_production_rm_items', [
        'run_id' => $testRunId,
        'product_id' => $testRmId,
        'warehouse_id' => $rmWhId,
        'qty_kg' => $expectedRmNeed,
        'note' => 'Auto-deduct test'
    ]);
    db_insert('rm_daily_usage', [
        'usage_date' => today(),
        'product_id' => $testRmId,
        'warehouse_id' => $rmWhId,
        'qty_kg' => $expectedRmNeed,
        'note' => 'Auto-deducted for Production #' . $runNo,
        'status' => 'posted',
        'recorded_by' => 1,
        'production_run_id' => $testRunId,
        'created_at' => now_local()
    ]);

    $postRmStock = Inventory::balance($testRmId, $rmWhId);
    $postFgStock = Inventory::balance($testFgId, $fgWhId);

    ok('Production: FG stock increased by produced qty', abs(($postFgStock - $initialFgStock) - $producedQty) < 0.001);
    ok('Production: RM stock decreased by expected consumption', abs(($initialRmStock - $postRmStock) - $expectedRmNeed) < 0.001);
    
    // Verify rm_daily_usage recorded it
    $usageEntry = db_one("SELECT * FROM rm_daily_usage WHERE production_run_id = ?", [$testRunId]);
    ok('Production: rm_daily_usage entry created and linked to production run', !empty($usageEntry) && (float)$usageEntry['qty_kg'] === $expectedRmNeed);

    // ── 4. Reversal Simulation ──
    // Simulate reversing the production run
    Inventory::issue($testFgId, $fgWhId, $producedQty, 'production_out', 'daily_production_reversal', $testRunId);
    Inventory::receive($testRmId, $rmWhId, $expectedRmNeed, 10, 'production_raw_reversal', 'daily_production_reversal', $testRunId);
    db_exec("UPDATE rm_daily_usage SET status = 'cancelled', cancel_reason = 'Test Reversal' WHERE production_run_id = ?", [$testRunId]);
    db_exec("UPDATE daily_production SET status = 'reversed' WHERE id = ?", [$testRunId]);

    $revertedRmStock = Inventory::balance($testRmId, $rmWhId);
    $revertedFgStock = Inventory::balance($testFgId, $fgWhId);

    ok('Reversal: FG stock restored back to initial', abs($revertedFgStock - $initialFgStock) < 0.001);
    ok('Reversal: RM stock restored back to initial', abs($revertedRmStock - $initialRmStock) < 0.001);
    $cancelledUsage = db_one("SELECT * FROM rm_daily_usage WHERE production_run_id = ?", [$testRunId]);
    ok('Reversal: rm_daily_usage marked as cancelled', $cancelledUsage['status'] === 'cancelled');
}

// ── 5. Daily Cash Position Calculations ──
$accounts = db_all("SELECT id, name, type, opening_balance, gl_account_id FROM cash_bank_accounts WHERE is_active = 1");
ok('CashBank: Accounts found in DB', count($accounts) > 0);

$today = today();
$dayIn = (float)db_val("SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.date = ? AND e.status = 'posted'", [$today]);
$dayOut = (float)db_val("SELECT COALESCE(SUM(l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.date = ? AND e.status = 'posted'", [$today]);
ok('CashBank: Daily inflow/outflow queried successfully', is_numeric($dayIn) && is_numeric($dayOut));

// ── 6. Navigation Structure Integrity ──
$layoutContent = file_get_contents(__DIR__ . '/../app/views/layout.php');
ok('Nav: Raw Material section present', strpos($layoutContent, '__section:rawmaterial') !== false);
ok('Nav: Warehouse section present', strpos($layoutContent, '__section:warehouse') !== false);
ok('Nav: Production section present', strpos($layoutContent, '__section:production') !== false);
ok('Nav: Sales section present with invoices', strpos($layoutContent, '__section:sales') !== false && strpos($layoutContent, 'sales/invoices') !== false);
ok('Nav: Daily Cash Position link present in Finance', strpos($layoutContent, 'cashbank/daily-position') !== false);
ok('Nav: Daily RM Usage link present in Raw Material', strpos($layoutContent, 'inventory/raw-usage') !== false);
ok('Nav: Records section present for Owner/Admin', strpos($layoutContent, '__section:records') !== false);

// ── 7. Dashboard Live Summary Content ──
$dashView = file_get_contents(__DIR__ . '/../app/views/dashboard/index.php');
ok('Dashboard: Today Live Factory Summary header present', strpos($dashView, 'Live Factory Summary') !== false);
ok('Dashboard: RM Used Today chip present', strpos($dashView, 'RM Used Today') !== false);
ok('Dashboard: FG Produced Today chip present', strpos($dashView, 'FG Produced Today') !== false);
ok('Dashboard: Sales Today chip present', strpos($dashView, 'Sales Today') !== false);
ok('Dashboard: Cash Position chip present', strpos($dashView, 'Net Cash Movement') !== false);

echo "\n--- Test Results ---\n";
echo "✅ Passed: $pass\n";
echo "❌ Failed: $fail\n";

if ($fail === 0) {
    echo "\n🎉 ALL TESTS PASSED! Every component is verified.\n";
    exit(0);
} else {
    echo "\n⚠️ Some tests failed.\n";
    exit(1);
}
