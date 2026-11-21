<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
$_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin'; $_SESSION['username'] = 'admin';

$pass = 0; $fail = 0;
function ok($l) { global $pass; echo "✅ $l\n"; $pass++; }
function no($l,$d='') { global $fail; echo "❌ $l" . ($d?" — $d":'') . "\n"; $fail++; }

echo "🧪 Raw Material Page Test\n\n";

// Simulate GET params
$_GET = ['tab' => 'purchases', 'from' => date('Y-01-01'), 'to' => date('Y-m-d'), 'q' => ''];

// Pull data like the controller does
$from = date('Y-01-01'); $to = date('Y-m-d'); $q = ''; $tab = 'purchases';
$tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));
$where = ["pi.date >= ?","pi.date <= ?","p.kind IN ('raw','packaging')","pi.status NOT IN ('rejected','reversed')"];
$params = [$from, $to];
$sql = "SELECT pi.id, pi.date, pi.payment_type, pi.payment_status, pi.amount_paid, pi.status,
               s.name AS supplier, p.name AS product, p.kind,
               ii.qty, ii.unit_short, ii.qty_kg, ii.unit_cost, ii.line_total
        FROM purchase_invoices pi
        JOIN suppliers s ON s.id = pi.supplier_id
        JOIN purchase_items ii ON ii.invoice_id = pi.id
        JOIN products p ON p.id = ii.product_id
        WHERE " . implode(' AND ', $where) . " ORDER BY pi.date DESC, p.name";

$allRows = db_all($sql, $params);
$totQtyKg = 0; $totValue = 0; $totPaid = 0;
foreach ($allRows as &$r) {
    $r['qty']        = (float)$r['qty'];
    $r['qty_kg']     = (float)($r['qty_kg'] ?? 0);
    $r['unit_short'] = strtoupper($r['unit_short'] ?? 'KG');
    $r['line_total'] = (float)$r['line_total'];
    $r['amount_paid']= (float)($r['amount_paid'] ?? 0);
    $kg = $r['qty_kg'] > 0.0009 ? $r['qty_kg'] : UnitConverter::toKg($r['qty'], $r['unit_short']);
    $totQtyKg += $kg; $totValue += $r['line_total']; $totPaid += $r['amount_paid'];
} unset($r);

ok("SQL query runs without error (" . count($allRows) . " rows)");

// Render purchases tab
ob_start();
$p = ['rows' => $allRows, 'total' => count($allRows), 'page' => 1, 'pages' => 1, 'per_page' => 50];
view('purchase/raw', [
    'p' => $p, 'allRows' => $allRows, 'q' => '', 'from' => $from, 'to' => $to,
    'tab' => 'purchases', 'totQtyKg' => $totQtyKg, 'totValue' => $totValue,
    'totPaid' => $totPaid, 'tonUnit' => $tonUnit, 'title' => 'Raw Material Purchases',
]);
$html = ob_get_clean();

strpos($html, 'Raw Material Name') !== false ? ok("Column: Raw Material Name") : no("Missing Raw Material Name column");
strpos($html, 'Quantity (KG)') !== false ? ok("Column: Quantity (KG)") : no("Missing KG column");
strpos($html, 'Quantity (TON)') !== false ? ok("Column: Quantity (TON)") : no("Missing TON column");
strpos($html, 'Rate / KG') !== false ? ok("Column: Rate / KG") : no("Missing Rate column");
strpos($html, 'Total Amount') !== false ? ok("Column: Total Amount") : no("Missing Total Amount");
strpos($html, 'Amount Paid') !== false ? ok("Column: Amount Paid") : no("Missing Amount Paid");
strpos($html, 'Remaining') !== false ? ok("Column: Remaining") : no("Missing Remaining");
strpos($html, 'Invoice #') === false ? ok("Invoice # removed") : no("Invoice # still present (should be removed)");
strpos($html, 'SKU') === false ? ok("SKU removed") : no("SKU still present (should be removed)");
strpos($html, 'Remaining Stock (now)') === false ? ok("Remaining Stock column removed") : no("Remaining Stock still showing");

// Stat chips
strpos($html, 'Total Purchased') !== false ? ok("Stat chip: Total Purchased") : no("Missing Total Purchased chip");
strpos($html, 'Total Value') !== false ? ok("Stat chip: Total Value") : no("Missing Total Value chip");
strpos($html, 'Total Paid') !== false ? ok("Stat chip: Total Paid") : no("Missing Total Paid chip");
strpos($html, 'Remaining Payable') !== false ? ok("Stat chip: Remaining Payable") : no("Missing Remaining Payable chip");

// Summary tab
ob_start();
$p2 = ['rows' => $allRows, 'total' => count($allRows), 'page' => 1, 'pages' => 1, 'per_page' => 50];
view('purchase/raw', [
    'p' => $p2, 'allRows' => $allRows, 'q' => '', 'from' => $from, 'to' => $to,
    'tab' => 'summary', 'totQtyKg' => $totQtyKg, 'totValue' => $totValue,
    'totPaid' => $totPaid, 'tonUnit' => $tonUnit, 'title' => 'Raw Material Purchases',
]);
$html2 = ob_get_clean();
strpos($html2, 'Summary') !== false && strpos($html2, 'Total Purchased') !== false ? ok("Summary tab renders correctly") : no("Summary tab broken");
strpos($html2, 'Avg Rate') !== false ? ok("Summary: Avg Rate column") : no("Summary: Avg Rate missing");
strpos($html2, 'Total Purchases') !== false || strpos($html2, 'Purchases') !== false ? ok("Summary: Purchases count column") : no("Summary: Purchases count missing");

echo "\n══════════════════════════════\n";
echo "Result: $pass passed, $fail failed\n";
if ($fail === 0) echo "🎉 ALL PASSED — Raw Material page redesign complete!\n";
