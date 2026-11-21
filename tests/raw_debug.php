<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
$_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin'; $_SESSION['username'] = 'admin';

$from = date('Y-01-01'); $to = date('Y-m-d');
$tonUnit = max(1, (float)setting('rm_unit_ton', '1000'));
$where = ["pi.date >= ?","pi.date <= ?","p.kind IN ('raw','packaging')","pi.status NOT IN ('rejected','reversed')"];
$params = [$from, $to];
$sql = "SELECT pi.id, pi.date, pi.payment_type, pi.payment_status, pi.amount_paid, pi.status,
               s.name AS supplier, p.name AS product, p.kind,
               ii.qty, ii.unit_short, ii.qty_kg, ii.unit_cost, ii.line_total
        FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
        JOIN purchase_items ii ON ii.invoice_id = pi.id
        JOIN products p ON p.id = ii.product_id
        WHERE " . implode(' AND ', $where) . " ORDER BY pi.date DESC, p.name";
$allRows = db_all($sql, $params);
$totQtyKg=0; $totValue=0; $totPaid=0;
foreach ($allRows as &$r) {
    $r['qty']=(float)$r['qty']; $r['qty_kg']=(float)($r['qty_kg']??0);
    $r['unit_short']=strtoupper($r['unit_short']??'KG');
    $r['line_total']=(float)$r['line_total']; $r['amount_paid']=(float)($r['amount_paid']??0);
    $kg=$r['qty_kg']>0.0009?$r['qty_kg']:UnitConverter::toKg($r['qty'],$r['unit_short']);
    $totQtyKg+=$kg; $totValue+=$r['line_total']; $totPaid+=$r['amount_paid'];
} unset($r);
$p=['rows'=>$allRows,'total'=>count($allRows),'page'=>1,'pages'=>1,'per_page'=>50];
ob_start();
view('purchase/raw', ['p'=>$p,'allRows'=>$allRows,'q'=>'','from'=>$from,'to'=>$to,
    'tab'=>'summary','totQtyKg'=>$totQtyKg,'totValue'=>$totValue,
    'totPaid'=>$totPaid,'tonUnit'=>$tonUnit,'title'=>'Raw Material Purchases']);
$html = ob_get_clean();
// print just the table headers area
preg_match('/<thead>.*?<\/thead>/s', $html, $m);
echo $m[0] ?? "No thead found\n";
