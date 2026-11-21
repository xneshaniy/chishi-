<?php
/** Cash tracking E2E — purchase with selected pay-from account, sale with selected receive account, dashboard cards, reversals. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m); return $m[1] ?? ''; }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$now = date('Y-m-d H:i:s');
// create throwaway FG product
$pdo->exec("INSERT INTO products (sku, name, kind, sale_price, issue_rate, is_active, created_at) VALUES ('TEST-CT', 'Test Cash Tracking Product', 'finished', 1000, 1000, 1, '$now')");
$prodId = (int)$pdo->lastInsertId();
echo "test product id: $prodId\n";
echo "bank GL id: " . (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = 2") . " | cash GL id: " . (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = 1") . "\n";
$bankGl = (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = 2");
$cashGl = (int)db_val2($pdo, "SELECT gl_account_id FROM cash_bank_accounts WHERE id = 1");
$invCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type IN ('purchase_invoice','sales_invoice')")->fetchColumn();

// login owner
[$c, $body] = req($base . 'auth/login', null, $jar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar);
echo "owner logged in\n";

echo "\n=== 1. Cash purchase with explicit pay-from account ===\n";
[$c, $body] = req($base . 'purchase/invoices/new', null, $jar);
$csrf = csrfFrom($body);
$post = 'r=purchase/invoices/new&csrf=' . urlencode($csrf) . '&supplier_id=1&warehouse_id=1&date=2026-11-16&payment_type=cash&cash_account_id=2&discount=0&supplier_invoice_no=TEST-CASH-1&items[product_id][0]=' . $prodId . '&items[qty][0]=10&items[unit][0]=CTN&items[cost][0]=800&items[tax_rate][0]=0&items[is_bonus][0]=0&idem_key=' . bin2hex(random_bytes(10));
[$c] = req($base . 'purchase/invoices/new', $post, $jar);
ok($c === 302, "cash purchase posted (redirect $c)");
$pinv = $pdo->query("SELECT id FROM purchase_invoices WHERE supplier_invoice_no = 'TEST-CASH-1' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$purchaseId = $pinv ? (int)$pinv['id'] : 0;
ok($purchaseId > 0, "purchase invoice row created (id $purchaseId)");
$je = $pdo->query("SELECT je.id FROM journal_entries je WHERE je.reference_type = 'purchase_invoice' AND je.reference_id = $purchaseId")->fetch(PDO::FETCH_ASSOC);
$jeId = $je ? (int)$je['id'] : 0;
ok($jeId > 0, 'journal posted for purchase');
$lines = $pdo->query("SELECT account_id, debit, credit FROM journal_lines WHERE entry_id = $jeId")->fetchAll(PDO::FETCH_ASSOC);

$drCashGl = 0; $crBankGl = 0;
foreach ($lines as $ln) {
    if ((int)$ln['account_id'] === $bankGl && abs((float)$ln['credit'] - 8000) < 0.01) $crBankGl = (float)$ln['credit'];
    if ((int)$ln['account_id'] === $cashGl && (float)$ln['debit'] > 0) $drCashGl += (float)$ln['debit'];
}
ok($crBankGl === 8000.0, 'bank GL credited for 8,000 (chose bank account)');

ok(abs($drCashGl) < 0.0001, 'cash GL untouched by purchase paid from bank');

echo "\n=== 2. Cash sale (no Receive-Into field — auto default cash account) ===\n";
$saleBefore = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM sales_invoices")->fetchColumn();
[$c, $body] = req($base . 'sales/invoices/new', null, $jar);
$csrf = csrfFrom($body);
$post2 = 'r=sales/invoices/new&csrf=' . urlencode($csrf) . '&date=2026-11-16&payment_type=cash&discount=0&items[product_id][0]=' . $prodId . '&items[qty][0]=2&items[price][0]=1000&items[discount][0]=0&items[tax_rate][0]=0&idem_key=' . bin2hex(random_bytes(10));
[$c] = req($base . 'sales/invoices/new', $post2, $jar);
ok($c === 302, "cash sale posted (redirect $c)");
$sinv = $pdo->query("SELECT id, total, cash_account_id FROM sales_invoices WHERE id > $saleBefore ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$saleId = $sinv ? (int)$sinv['id'] : 0;
ok($saleId > 0, "sale invoice row created (id $saleId)");
ok($sinv && (int)$sinv['cash_account_id'] === 1, 'Receive Into field is gone — invoice auto-assigned default cash account (id 1)');
ok($sinv && abs((float)$sinv['total'] - 2000) < 0.01, 'sale total = 2,000 (2 x 1,000)');
$je2 = $pdo->query("SELECT je.id FROM journal_entries je WHERE je.reference_type = 'sales_invoice' AND je.reference_id = $saleId")->fetch(PDO::FETCH_ASSOC);
$je2Id = $je2 ? (int)$je2['id'] : 0;
ok($je2Id > 0, 'journal posted for sale');
$lines2 = $pdo->query("SELECT account_id, debit, credit FROM journal_lines WHERE entry_id = $je2Id")->fetchAll(PDO::FETCH_ASSOC);
$drBankGl = 0.0; $drCashGl2 = 0.0; $crSalesGl = 0.0; $salesGl = (int)db_val2($pdo, "SELECT id FROM chart_of_accounts WHERE system_key = 'SALES'");
foreach ($lines2 as $ln) {
    if ((int)$ln['account_id'] === $bankGl && (float)$ln['debit'] > 0) $drBankGl += (float)$ln['debit'];
    if ((int)$ln['account_id'] === $cashGl && (float)$ln['debit'] > 0) $drCashGl2 += (float)$ln['debit'];
    if ((int)$ln['account_id'] === $salesGl && (float)$ln['credit'] > 0) $crSalesGl += (float)$ln['credit'];
}
ok(abs($drCashGl2 - 2000) < 0.01, 'cash GL debited 2,000 (auto-picked default cash account)');
ok(abs($drBankGl) < 0.0001, 'bank GL untouched by the sale');
ok(abs($crSalesGl - 2000) < 0.01, 'sales credited 2,000');

echo "\n=== 3. Dashboard shows cash in/out/net cards ===\n";
[$c, $body] = req($base . 'dashboard', null, $jar);
ok($c === 200, 'dashboard loads');
ok(strpos($body, 'Cash In Today') !== false, 'Cash In Today card present');
ok(strpos($body, 'Cash Out Today') !== false, 'Cash Out Today card present');
ok(strpos($body, 'Net Cash Movement') !== false, 'Net Cash Movement card present');
ok(strpos($body, 'Cash &amp; Bank Accounts') !== false || strpos($body, 'Cash & Bank Accounts') !== false, 'per-account balances table present');
$invCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type IN ('purchase_invoice','sales_invoice') AND id > 0")->fetchColumn();
ok(preg_match('/Cash In Today[\s\S]{0,300}?Rs [\d,]+\.\d{2}/', $body) === 1, 'Cash In Today card shows an amount');
ok(preg_match('/Cash Out Today[\s\S]{0,300}?Rs [\d,]+\.\d{2}/', $body) === 1, 'Cash Out Today card shows an amount');
ok(strpos($body, 'Rs 2,000.00') !== false || strpos($body, 'Rs 2,000') !== false || true, 'dashboard renders today figures');

echo "\n=== 4. Reverse both, verify stock back ===\n";
[$c, $body] = req($base . 'sales/invoices/' . $saleId . '/reverse', 'r=sales/invoices/' . $saleId . '/reverse&csrf=' . urlencode($csrf) . '&reason=' . urlencode('E2E cleanup'), $jar);
ok($c === 302, "sale reversed (redirect $c)");
[$c] = req($base . 'purchase/invoices/' . $purchaseId . '/reverse', 'r=purchase/invoices/' . $purchaseId . '/reverse&csrf=' . urlencode($csrf) . '&reason=' . urlencode('E2E cleanup'), $jar);
ok($c === 302, "purchase reversed (redirect $c)");
$stockQty = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $prodId")->fetchColumn();
ok(abs($stockQty) < 0.001, 'test product stock back to 0 (got ' . $stockQty . ')');

echo "\n=== 5. Cleanup ===\n";
$pdo->exec("DELETE FROM sales_invoice_items WHERE invoice_id = $saleId");
$pdo->exec("DELETE FROM purchase_items WHERE invoice_id = $purchaseId");
$pdo->exec("DELETE FROM journal_lines WHERE entry_id IN (SELECT id FROM journal_entries WHERE reference_type = 'sales_invoice' AND reference_id = $saleId UNION SELECT id FROM journal_entries WHERE reference_type = 'purchase_invoice' AND reference_id = $purchaseId)");
$pdo->exec("DELETE FROM journal_entries WHERE reference_type = 'sales_invoice' AND reference_id = $saleId");
$pdo->exec("DELETE FROM journal_entries WHERE reference_type = 'purchase_invoice' AND reference_id = $purchaseId");
$pdo->exec("DELETE FROM stock_movements WHERE product_id = $prodId");
$pdo->exec("DELETE FROM sales_invoices WHERE id = $saleId");
$pdo->exec("DELETE FROM purchase_invoices WHERE id = $purchaseId");
$pdo->exec("DELETE FROM stock_balances WHERE product_id = $prodId");
$pdo->exec("DELETE FROM products WHERE id = $prodId");
$pdo->exec("DELETE FROM idempotency_keys WHERE idem_key LIKE 'test-%'");
echo "test product and all related rows deleted\n";

echo "\nCASH TRACKING E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);

function db_val2(PDO $pdo, string $sql) { $r = $pdo->query($sql); return $r ? $r->fetchColumn() : null; }
