<?php
/** Production section E2E — against the dev server. Creates throwaway FG products, an image upload,
 *  a multi-product daily entry, verifies stock, history, reports, reversal, permissions, cleanup. */
$base = 'http://127.0.0.1:8080';
$jar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
$managerJar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
$accJar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
$imgDir = 'C:/Users/Haseeb/AppData/Local/Temp/opencode';
$redPng = $imgDir . '/test_red.png';

function req(string $method, string $url, ?array $post = null, ?array $files = null, string $jar = '', string $contentType = 'application/x-www-form-urlencoded'): array {
    $jar = $jar ?: $GLOBALS['jar'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($files) {
        $fields = $post ?? [];
        foreach ($files as $k => $f) { $fields[$k] = new CURLFile($f[0], $f[1], $f[2]); }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    } elseif ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . $contentType]);
    }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m); return $m[1] ?? ''; }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$now = date('Y-m-d H:i:s');
$testStart = time();
// idempotency: remove leftovers from any previous crashed run (and the manual DEBUGPROD1)
$pdo->exec("DELETE FROM daily_production_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'TEST-PROD-%')");
$pdo->exec("DELETE FROM daily_production WHERE notes IN ('E2E morning shift', 'should not save')");
$pdo->exec("DELETE FROM stock_movements WHERE product_id IN (SELECT id FROM products WHERE sku IN ('TEST-PROD-A','TEST-PROD-B','TEST-PROD-C','DEBUGPROD1'))");
$pdo->exec("DELETE FROM stock_balances WHERE product_id IN (SELECT id FROM products WHERE sku IN ('TEST-PROD-A','TEST-PROD-B','TEST-PROD-C','DEBUGPROD1'))");
try { $pdo->exec("DELETE FROM stock_cost_layers WHERE product_id IN (SELECT id FROM products WHERE sku IN ('TEST-PROD-A','TEST-PROD-B','TEST-PROD-C','DEBUGPROD1'))"); } catch (Throwable $e) { /* no layer table */ }
$pdo->exec("DELETE FROM products WHERE sku IN ('TEST-PROD-A','TEST-PROD-B','TEST-PROD-C','DEBUGPROD1')");
// Clean orphan uploads from previous photo tests of these skus
foreach (['13','7','8','9'] as $orphan) { $d = __DIR__ . '/../storage/uploads/products/' . $orphan; if (is_dir($d)) { array_map('unlink', glob($d . '/*') ?: []); @rmdir($d); } }
// NOTE: product A is created via HTTP below (photo upload); B and C are seeded directly
foreach (['TEST-PROD-B' => 'Test Product Beta', 'TEST-PROD-C' => 'Test Product Gamma'] as $code => $name) {
    $pdo->exec("INSERT INTO products (sku, name, kind, sale_price, issue_rate, is_active, created_at) VALUES ('$code', '$name', 'finished', 100, 90, 1, '$now')");
}
echo "seeded B and C directly\n";

// Login owner + manager + accountant
req('GET', $base . '/index.php?r=auth/login', null, null, $jar);
$csrf = csrfFrom(req('GET', $base . '/index.php?r=auth/login', null, null, $jar)[1]);
req('POST', $base . '/index.php?r=auth/login', ['r' => 'auth/login', 'csrf' => $csrf, 'username' => 'owner', 'password' => 'owner1234'], null, $jar);
req('GET', $base . '/index.php?r=auth/login', null, null, $managerJar);
$csrf = csrfFrom(req('GET', $base . '/index.php?r=auth/login', null, null, $managerJar)[1]);
req('POST', $base . '/index.php?r=auth/login', ['r' => 'auth/login', 'csrf' => $csrf, 'username' => 'manager1', 'password' => 'Manager@1234x'], null, $managerJar);
req('GET', $base . '/index.php?r=auth/login', null, null, $accJar);
$csrf = csrfFrom(req('GET', $base . '/index.php?r=auth/login', null, null, $accJar)[1]);
req('POST', $base . '/index.php?r=auth/login', ['r' => 'auth/login', 'csrf' => $csrf, 'username' => 'accountant1', 'password' => 'Accountant@1234x'], null, $accJar);

echo "\n=== 1. Create product WITH photo via HTTP ===\n";
[$c, $form] = req('GET', $base . '/index.php?r=products/new', null, null, $jar);
$csrf = csrfFrom($form);
ok($csrf !== '', 'product form loads with csrf');
$skuA = 'TEST-PROD-A';
[$c] = req('POST', $base . '/index.php?r=products/new',
    ['r' => 'products/new', 'csrf' => $csrf, 'sku' => $skuA, 'name' => 'Test Product Alpha', 'kind' => 'finished', 'purchase_price' => '80', 'sale_price' => '100', 'issue_rate' => '90', 'min_price' => '95', 'tax_rate' => '0', 'reorder_level' => '0', 'has_expiry' => '0', 'description' => 'E2E throwaway product with photo'],
    ['photo' => [$redPng, 'image/png', 'red.png']], $jar);
ok($c === 302, 'product with photo created (redirect)');
$ids = [];
foreach ($pdo->query("SELECT id, sku FROM products WHERE sku IN ('TEST-PROD-A','TEST-PROD-B','TEST-PROD-C')") as $r) { $ids[$r['sku']] = (int)$r['id']; }
echo 'test product ids: ' . json_encode($ids) . "\n";
$prodA = $pdo->query("SELECT id, photo_path FROM products WHERE sku = '$skuA'")->fetch(PDO::FETCH_ASSOC);
ok((bool)$prodA, 'product row exists');
$photoPath = $prodA ? (string)$prodA['photo_path'] : '';
ok($photoPath !== '', 'photo_path stored on product row');
ok($photoPath !== '' && is_file(__DIR__ . '/../storage/uploads/' . $photoPath), 'photo stored on disk');
$info = $photoPath ? @getimagesize(__DIR__ . '/../storage/uploads/' . $photoPath) : false;
ok($info && $info[0] <= 256 && $info[1] <= 256, 'stored photo is a <=256px thumbnail' . ($info ? ' (' . $info[0] . 'x' . $info[1] . ')' : ''));

echo "\n=== 2. Production landing renders ===\n";
[$c, $body] = req('GET', $base . '/index.php?r=production', null, null, $jar);
ok($c === 200, 'cards page loads');
ok(strpos($body, 'Production') !== false, 'page title present');
ok(strpos($body, "Today's Production") !== false || strpos($body, 'Today’s Production') !== false, 'today stat present');
ok(strpos($body, 'This Month') !== false, 'month stat present');
ok(strpos($body, 'Test Product Alpha') !== false, 'throwaway product card rendered');
ok(strpos($body, 'data:image/') !== false, 'product cards render inline photos (photo_data_uri)');

echo "\n=== 3. Product photo persists after refresh ===\n";
$prodAId = (int)$prodA['id'];
[$c, $body] = req('GET', $base . '/index.php?r=production/' . $prodAId, null, null, $jar);
ok(strpos($body, 'data:image/') !== false, 'product detail still shows photo after refresh');
[$c] = req('GET', $base . '/index.php?r=products/' . $prodAId . '/photo-file', null, null, $jar);
ok($c === 200, 'photo-file streams 200');

echo "\n=== 4. Daily production: multi-product save ===\n";
$balBefore = [];
foreach ($ids as $sku => $pid) $balBefore[$sku] = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid")->fetchColumn();
[$c, $body] = req('GET', $base . '/index.php?r=production/run', null, null, $jar);
$csrf = csrfFrom($body);
$idem = 'testprod-' . bin2hex(random_bytes(8));
$post = ['r' => 'production/run', 'csrf' => $csrf, 'date' => '2026-11-16', 'notes' => 'E2E morning shift', 'idem_key' => $idem];
$post['items']['product_id'] = [$ids['TEST-PROD-A'], $ids['TEST-PROD-B'], $ids['TEST-PROD-C']];
$post['items']['qty'] = ['500', '300', '200']; // entered in Packets — stored as Packets, no ×10 conversion
$post['items']['batch_no'] = ['B-E2E-1', 'B-E2E-1', ''];
$post['items']['line_notes'] = ['alpha run', 'beta run', ''];
[$c] = req('POST', $base . '/index.php?r=production/run', $post, null, $jar);
ok($c === 302, "run saved (redirect $c)");
$runId = (int)$pdo->query("SELECT id FROM daily_production WHERE date = '2026-11-16' AND notes = 'E2E morning shift' ORDER BY id DESC LIMIT 1")->fetchColumn();
ok($runId > 0, "daily_production row created (id $runId)");
$runItems = $pdo->query("SELECT COUNT(*), SUM(qty) FROM daily_production_items WHERE run_id = $runId")->fetch(PDO::FETCH_NUM);
ok(((int)$runItems[0]) === 3, 'three product lines saved');
ok(abs((float)$runItems[1] - 1000) < 0.001, 'total produced = 1,000 Packets');
foreach ($ids as $sku => $pid) {
    $after = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid")->fetchColumn();
    $expected = $balBefore[$sku] + ['TEST-PROD-A' => 500, 'TEST-PROD-B' => 300, 'TEST-PROD-C' => 200][$sku];
    ok(abs($after - $expected) < 0.001, "$sku stock increased to $expected Packets (got $after)");
}
$mv = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'daily_production' AND ref_id = $runId AND type = 'production_in'")->fetchColumn();
ok($mv === 3, 'stock_movements recorded for all 3 products');

echo "\n=== 5. Double submission prevented ===\n";
$runsBefore = (int)$pdo->query("SELECT COUNT(*) FROM daily_production")->fetchColumn();
[$c] = req('POST', $base . '/index.php?r=production/run', $post, null, $jar);
$runsAfter = (int)$pdo->query("SELECT COUNT(*) FROM daily_production")->fetchColumn();
ok($runsAfter === $runsBefore, 'same idem_key did NOT create a second run');

echo "\n=== 6. Zero-quantity validation ===\n";
$post2 = ['r' => 'production/run', 'csrf' => $csrf, 'date' => '2026-11-16', 'notes' => 'should not save', 'idem_key' => 'testprod-' . bin2hex(random_bytes(8))];
$post2['items']['product_id'] = [$ids['TEST-PROD-A']];
$post2['items']['qty'] = ['0'];
$post2['items']['batch_no'] = [''];
$post2['items']['line_notes'] = [''];
[$c] = req('POST', $base . '/index.php?r=production/run', $post2, null, $jar);
$runsZero = (int)$pdo->query("SELECT COUNT(*) FROM daily_production WHERE notes = 'should not save'")->fetchColumn();
ok($runsZero === 0, 'zero-qty lines rejected, no run created');

echo "\n=== 7. Product history ===\n";
[$c, $body] = req('GET', $base . '/index.php?r=production/' . $ids['TEST-PROD-A'], null, null, $jar);
ok($c === 200, 'product history page loads');
ok(strpos($body, 'B-E2E-1') !== false, 'batch number shown');
ok(strpos($body, 'alpha run') !== false, 'line note shown');
ok(strpos($body, '500') !== false, 'quantity shown');
ok(strpos($body, 'Production History') !== false, 'history table present');
ok(strpos($body, 'Total Production') !== false, 'total production card present');

echo "\n=== 8. Production report ===\n";
[$c, $body] = req('GET', $base . '/index.php?r=production/report&mode=range&from=2026-11-01&to=2026-11-30&product_id=' . $ids['TEST-PROD-A'], null, null, $jar);
ok($c === 200, 'report loads');
ok(strpos($body, 'Grand Total Produced') !== false, 'grand total card present');
ok(strpos($body, 'Test Product Alpha') !== false, 'per-product row present');
$alphaRow = preg_match('/Test Product Alpha[\s\S]{0,200}?500/', $body);
ok($alphaRow === 1, 'report shows 500 produced for Product Alpha');
ok(strpos($body, '(1 products)') !== false, 'product filter limits totals to one product');
[$c, $body] = req('GET', $base . '/index.php?r=production/report&mode=month&month=2026-11', null, null, $jar);
ok($c === 200, 'monthly report loads');
[$c, $body] = req('GET', $base . '/index.php?r=production/report&mode=day&date=2026-11-16', null, null, $jar);
ok($c === 200, 'daily report loads');

echo "\n=== 9. Permissions ===\n";
[$c, $body] = req('GET', $base . '/index.php?r=production/run', null, null, $accJar);
[$c] = req('POST', $base . '/index.php?r=production/run', ['r' => 'production/run', 'csrf' => csrfFrom($body), 'date' => '2026-11-16', 'idem_key' => 'testprod-' . bin2hex(random_bytes(8))], null, $accJar);
ok($c === 403, 'accountant (no production.create) gets 403 on POST');
[$c, $body] = req('GET', $base . '/index.php?r=production', null, null, $accJar);
ok($c === 403, 'accountant (no production.view) is forbidden from production cards');

echo "\n=== 10. Reverse run (Owner/Admin only) ===\n";
[$c] = req('POST', $base . '/index.php?r=production/runs/' . $runId . '/reverse', ['r' => 'production/runs/' . $runId . '/reverse', 'csrf' => csrfFrom(req('GET', $base . '/index.php?r=production/' . $ids['TEST-PROD-A'], null, null, $managerJar)[1]), 'reason' => 'E2E cleanup'], null, $managerJar);
ok($c === 403, 'manager cannot reverse (Owner/Admin only)');
[$c, $body] = req('GET', $base . '/index.php?r=production/' . $ids['TEST-PROD-A'], null, null, $jar);
$csrf = csrfFrom($body);
[$c] = req('POST', $base . '/index.php?r=production/runs/' . $runId . '/reverse', ['r' => 'production/runs/' . $runId . '/reverse', 'csrf' => $csrf, 'reason' => 'E2E cleanup'], null, $jar);
ok($c === 302, 'owner reversed the run');
$status = $pdo->query("SELECT status FROM daily_production WHERE id = $runId")->fetchColumn();
ok($status === 'reversed', 'run marked reversed');
foreach ($ids as $sku => $pid) {
    $after = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = $pid")->fetchColumn();
    ok(abs($after - $balBefore[$sku]) < 0.001, "$sku stock back to pre-run level ($after)");
}
$mvOut = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'daily_production_reversal' AND ref_id = $runId AND type = 'production_out'")->fetchColumn();
ok($mvOut === 3, 'reversal issued stock back out exactly once');
// second reverse should fail cleanly (no double issue)
[$c, $body] = req('GET', $base . '/index.php?r=production/' . $ids['TEST-PROD-A'], null, null, $jar);
$csrf = csrfFrom($body);
$mvOutBefore = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'daily_production_reversal' AND ref_id = $runId")->fetchColumn();
[$c] = req('POST', $base . '/index.php?r=production/runs/' . $runId . '/reverse', ['r' => 'production/runs/' . $runId . '/reverse', 'csrf' => $csrf, 'reason' => 'double reverse attempt'], null, $jar);
$mvOutAfter = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'daily_production_reversal' AND ref_id = $runId")->fetchColumn();
ok($mvOutAfter === $mvOutBefore, 'second reverse did NOT issue stock again');

echo "\n=== 11. Cleanup ===\n";
$pdo->exec("DELETE FROM daily_production_items WHERE run_id IN (SELECT id FROM daily_production WHERE notes = 'E2E morning shift')");
$pdo->exec("DELETE FROM daily_production WHERE notes = 'E2E morning shift'");
foreach ($ids as $pid) {
    $pdo->exec("DELETE FROM stock_movements WHERE product_id = $pid");
    $pdo->exec("DELETE FROM stock_balances WHERE product_id = $pid");
    try { $pdo->exec("DELETE FROM stock_cost_layers WHERE product_id = $pid"); } catch (Throwable $e) { /* layer table may not exist */ }
}
foreach (['TEST-PROD-A', 'TEST-PROD-B', 'TEST-PROD-C'] as $code) $pdo->exec("DELETE FROM products WHERE sku = '$code'");
@rrmdir(__DIR__ . '/../storage/uploads/products');
echo "throwaway products, runs, stock and movements deleted\n";
echo "note: photo files removed in tests\n";

echo "\nPRODUCTION E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
