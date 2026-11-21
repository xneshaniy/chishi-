<?php
/** E2E for optional-customer / walk-in flows (v24). Dev only; cleans up after itself. */
$base = 'http://127.0.0.1:8080';
$pdo  = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$results = [];
function ok($label, $cond) { global $results; $results[] = [$label, $cond ? 'PASS' : 'FAIL']; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }

function http_req(string $method, string $url, ?array $post = null, array &$cookies = [], bool $raw = false): array {
    global $base;
    $route = ltrim($url, '/');
    if (str_contains($route, '?')) { [$route, $qs] = explode('?', $route, 2); $full = $base . '/index.php?r=' . rawurlencode($route) . '&' . $qs; }
    else $full = $base . '/index.php?r=' . rawurlencode($route);
    $ch = curl_init($full);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($cookies) { $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; ')); }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $raw_ = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($raw_, 0, $len);
    $body = substr($raw_, $len);
    foreach (preg_split('/\r?\n/', $header) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return [$code, $body, $header];
}
function login(string $user, string $pass): array {
    $cookies = [];
    [$code, $body] = http_req('GET', 'auth/login', null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf on login\n"; exit(1); }
    http_req('POST', 'auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
    return $cookies;
}
function csrf_from(string $url, array &$cookies): string {
    [$code, $body] = http_req('GET', $url, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $url\n"; exit(1); }
    return $m[1];
}

$owner = login('owner', 'owner1234');
$today = date('Y-m-d');
echo "owner logged in\n";

/* ---------- pick a finished-goods product with central stock ---------- */
$prod = $pdo->query("SELECT p.id, p.name, p.min_price, COALESCE(SUM(sb.qty),0) AS qty FROM products p JOIN stock_balances sb ON sb.product_id = p.id JOIN warehouses w ON w.id = sb.warehouse_id WHERE p.kind = 'finished' AND w.type = 'central' AND p.min_price = 0 GROUP BY p.id HAVING qty >= 5 ORDER BY qty DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "product: {$prod['name']} (id {$prod['id']}, central stock {$prod['qty']})\n";
$beforeQty = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = {$prod['id']}")->fetchColumn();

/* ---------- 1. walk-in cash sales invoice (no customer) ---------- */
$csrf = csrf_from('sales/invoices/new', $owner);
[$code] = http_req('POST', 'sales/invoices/new', [
    'csrf' => $csrf, 'idem_key' => 'e2e-walkin-' . bin2hex(random_bytes(5)),
    'customer_id' => '', 'date' => $today, 'payment_type' => 'cash',
    'cash_account_id' => 1,
    'items' => ['product_id' => [(int)$prod['id']], 'qty' => ['1'], 'price' => ['500'], 'discount' => ['0'], 'tax_rate' => ['0']],
    'discount' => '0.00',
], $owner);
ok('walk-in invoice POST redirects', $code === 302);
$invId = (int)$pdo->query("SELECT MAX(id) FROM sales_invoices WHERE created_by = 1 AND payment_type = 'cash' AND date = '$today'")->fetchColumn();
$inv = $pdo->query("SELECT * FROM sales_invoices WHERE id = $invId")->fetch(PDO::FETCH_ASSOC);
ok('walk-in invoice created (customer_id NULL)', $inv && $inv['customer_id'] === null);
$invId = (int)$inv['id'];
$items = $pdo->query("SELECT * FROM sales_invoice_items WHERE invoice_id = $invId")->fetchAll(PDO::FETCH_ASSOC);
ok('invoice has 1 line item', count($items) === 1);
ok('invoice status posted', $inv['status'] === 'posted');
$afterQty = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = {$prod['id']}")->fetchColumn();
ok('stock deducted exactly once (−1)', $afterQty === $beforeQty - 1.0 || $afterQty === $beforeQty - 1);

/* ---------- 2. credit sale without customer must be rejected ---------- */
[$code, $body] = http_req('POST', 'sales/invoices/new', [
    'csrf' => csrf_from('sales/invoices/new', $owner), 'idem_key' => 'e2e-credit-' . bin2hex(random_bytes(5)),
    'customer_id' => '', 'date' => $today, 'payment_type' => 'credit',
    'items' => ['product_id' => [(int)$prod['id']], 'qty' => ['1'], 'price' => ['500'], 'discount' => ['0'], 'tax_rate' => ['0']],
    'discount' => '0.00',
], $owner);
$lastInv = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM sales_invoices")->fetchColumn();
ok('credit sale without customer rejected (no new invoice)', $lastInv === $invId);

/* ---------- 3. credit sale WITH a salesman must still work (guard intact) ---------- */
$sm3 = $pdo->query("SELECT id FROM salesmen WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
[$code] = http_req('POST', 'sales/invoices/new', [
    'csrf' => csrf_from('sales/invoices/new', $owner), 'idem_key' => 'e2e-creditok-' . bin2hex(random_bytes(5)),
    'salesman_id' => $sm3, 'date' => $today, 'payment_type' => 'credit',
    'items' => ['product_id' => [(int)$prod['id']], 'qty' => ['1'], 'price' => ['500'], 'discount' => ['0'], 'tax_rate' => ['0']],
    'discount' => '0.00',
], $owner);
ok('credit sale with salesman posts (302)', $code === 302);
$inv2 = $pdo->query("SELECT * FROM sales_invoices WHERE id > $invId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('credit invoice created and linked to the salesman', $inv2 && (int)$inv2['salesman_id'] === (int)$sm3);

/* ---------- 4. walk-in sales order ---------- */
[$code] = http_req('POST', 'sales/orders/new', [
    'csrf' => csrf_from('sales/orders/new', $owner), 'idem_key' => 'e2e-order-' . bin2hex(random_bytes(5)),
    'customer_id' => '', 'salesman_id' => '', 'order_date' => $today, 'delivery_date' => '', 'notes' => 'E2E walk-in order',
    'items' => ['product_id' => [(int)$prod['id']], 'qty' => ['2'], 'price' => ['500']],
], $owner);
$order = $pdo->query("SELECT * FROM sales_orders WHERE notes = 'E2E walk-in order' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('walk-in order saved with customer_id NULL', $order && $order['customer_id'] === null);

/* ---------- 5. walk-in salesman collection (no customer, person paid) ---------- */
// Need a salesman id — use the first active salesman
$sm = $pdo->query("SELECT id, code, name FROM salesmen WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$smCookies = login('owner', 'owner1234'); // still owner; collections are salesmen.settle — owner has it
[$code, $body] = http_req('POST', 'salesmen/' . $sm['id'] . '/collections', [
    'csrf' => csrf_from('salesmen/' . $sm['id'] . '/collections', $smCookies),
    'idem_key' => 'e2e-col-' . bin2hex(random_bytes(5)),
    'date' => $today, 'customer_id' => '', 'contact_name' => 'E2E Walking Customer',
    'amount' => '700', 'cash_amount' => '700', 'bank_amount' => '0', 'account_id' => '',
    'reference' => 'E2E ref', 'notes' => 'E2E walk-in collection',
], $smCookies);
ok('walk-in collection POST redirects', $code === 302);
$col = $pdo->query("SELECT * FROM salesman_collections WHERE notes = 'E2E walk-in collection' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('walk-in collection saved with contact_name', $col && $col['customer_id'] === null && $col['contact_name'] === 'E2E Walking Customer');

/* ---------- 6. collection without customer AND without payer must fail ---------- */
$colCount = (int)$pdo->query("SELECT COUNT(*) FROM salesman_collections")->fetchColumn();
http_req('POST', 'salesmen/' . $sm['id'] . '/collections', [
    'csrf' => csrf_from('salesmen/' . $sm['id'] . '/collections', $smCookies),
    'idem_key' => 'e2e-col2-' . bin2hex(random_bytes(5)),
    'date' => $today, 'customer_id' => '', 'contact_name' => '',
    'amount' => '100', 'cash_amount' => '100', 'bank_amount' => '0', 'account_id' => '',
    'reference' => '', 'notes' => 'E2E invalid',
], $smCookies);
$colCount2 = (int)$pdo->query("SELECT COUNT(*) FROM salesman_collections")->fetchColumn();
ok('collection without customer/payer rejected', $colCount === $colCount2);

/* ---------- 7. cash receipt with contact_name, no customer ---------- */
[$code] = http_req('POST', 'cashbank/receipts/new', [
    'csrf' => csrf_from('cashbank/receipts/new', $owner), 'idem_key' => 'e2e-rcpt-' . bin2hex(random_bytes(5)),
    'customer_id' => '', 'contact_name' => 'E2E Walk-in Payer', 'account_id' => 1,
    'date' => $today, 'amount' => '300', 'reference' => 'E2E', 'notes' => 'E2E walk-in receipt',
], $owner);
$rcpt = $pdo->query("SELECT * FROM receipts WHERE notes = 'E2E walk-in receipt' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('walk-in receipt saved (customer_id NULL, contact_name)', $rcpt && $rcpt['customer_id'] === null && $rcpt['contact_name'] === 'E2E Walk-in Payer');
if ($rcpt) {
    $journalId = (int)$rcpt['journal_entry_id'];
    $jrowCount = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='receipt' AND reference_id={$rcpt['id']}")->fetchColumn();
    ok('exactly-once receipt journal posted', $jrowCount === 1);
    $arCustLine = (int)$pdo->query("SELECT COUNT(*) FROM journal_lines WHERE entry_id=$journalId AND customer_id IS NOT NULL")->fetchColumn();
    ok('receipt posted with NULL customer line', $arCustLine === 0);
}

/* ---------- 8. reports show walk-in name ---------- */
[$code, $body] = http_req('GET', 'reports/daily-sales?date=' . $today, null, $owner);
ok('daily-sales 200 and shows Walk-in', $code === 200 && str_contains($body, 'Walk-in'));
[$code, $body] = http_req('GET', 'reports/sales-summary?group=customer&from=' . date('Y-m-01') . '&to=' . $today, null, $owner);
ok('sales-summary customer view 200 and Walk-in bucket', $code === 200 && str_contains($body, 'Walk-in'));

/* ---------- 9. walk-in invoice printable view renders ---------- */
[$code, $body] = http_req('GET', 'sales/invoices/' . $invId, null, $owner);
ok('walk-in invoice view 200 and shows Walk-in', $code === 200 && str_contains($body, 'Walk-in'));

/* ---------- 10. cleanup e2e data ---------- */
// reverse the credit invoice too so its stock returns
if ($inv2) {
    http_req('POST', 'sales/invoices/' . $inv2['id'] . '/reverse', ['csrf' => csrf_from('sales/invoices/' . $inv2['id'], $owner), 'reason' => 'E2E cleanup reversal'], $owner);
}
http_req('POST', 'sales/invoices/' . $invId . '/reverse', ['csrf' => csrf_from('sales/invoices/' . $invId, $owner), 'reason' => 'E2E cleanup reversal'], $owner);
$revInv = (int)$pdo->query("SELECT COUNT(*) FROM sales_invoices WHERE id = $invId AND status = 'reversed'")->fetchColumn();
ok('walk-in invoice reversed for cleanup (stock restored)', $revInv === 1);
$afterRev = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = {$prod['id']}")->fetchColumn();
ok('stock restored after reversal', $afterRev === $beforeQty);

// delete all e2e rows: journals + lines, movements, items, invoices, order, collections, receipt, audits
$e2eInvIds = array_filter([$invId, (int)($inv2['id'] ?? 0)]);
$jeIds = $pdo->query("SELECT id FROM journal_entries WHERE reference_type='sales_invoice' AND reference_id IN (" . implode(',', $e2eInvIds) . ") OR reversal_of IN (SELECT id FROM journal_entries WHERE reference_type='sales_invoice' AND reference_id IN (" . implode(',', $e2eInvIds) . "))")->fetchAll(PDO::FETCH_COLUMN);
if ($jeIds) {
    $in = implode(',', array_map('intval', $jeIds));
    $pdo->exec("DELETE FROM journal_lines WHERE entry_id IN ($in)");
    $pdo->exec("DELETE FROM journal_entries WHERE id IN ($in)");
}
foreach ($e2eInvIds as $ei) {
    $pdo->exec("DELETE FROM stock_movements WHERE ref_type='sales_invoice' AND ref_id=$ei");
    $pdo->exec("DELETE FROM sales_invoice_items WHERE invoice_id=$ei");
    $pdo->exec("DELETE FROM sales_invoices WHERE id=$ei");
}
$orderId = (int)$pdo->query("SELECT id FROM sales_orders WHERE notes = 'E2E walk-in order' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($orderId) {
    $pdo->exec("DELETE FROM sales_order_items WHERE sales_order_id = $orderId");
    $pdo->exec("DELETE FROM sales_orders WHERE id = $orderId");
}
$pdo->exec("DELETE FROM salesman_collections WHERE notes IN ('E2E walk-in collection', 'E2E invalid')");
$pdo->exec("DELETE FROM receipts WHERE notes = 'E2E walk-in receipt'");
$rcptIds = $pdo->query("SELECT id FROM receipts WHERE notes = 'E2E walk-in receipt'")->fetchAll(PDO::FETCH_COLUMN);
if ($journalId ?? null) { $pdo->exec("DELETE FROM journal_lines WHERE entry_id = $journalId"); $pdo->exec("DELETE FROM journal_entries WHERE id = $journalId"); }
$pdo->exec("DELETE FROM audit_logs WHERE details LIKE '%E2E%'");
$pdo->exec("DELETE FROM idempotency_keys WHERE idem_key LIKE 'e2e-%'");
$pdo->exec("DELETE FROM notifications WHERE entity_type = 'sales_invoice' AND entity_id NOT IN (SELECT id FROM sales_invoices)");
$pdo->exec("DELETE FROM notifications WHERE entity_type = 'receipt' AND entity_id NOT IN (SELECT id FROM receipts)");
echo "\nE2E artifacts cleaned up\n";

$fail = count(array_filter($results, fn($r) => $r[1] === 'FAIL'));
echo "\n", count($results), " checks, $fail failed\n";
exit($fail ? 1 : 0);
