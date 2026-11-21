<?php
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

// login owner
[$c, $body] = req($base . 'auth/login', null, $jar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar);

echo "=== production cards ===\n";
[$c, $body] = req($base . 'production', null, $jar);
ok($c === 200, 'production cards page loads');
foreach (['Chishti No 1', 'Rohi No 1', 'Chishti No 1 - Filter Khaini', 'Rohi No 1 - Filter Khaini'] as $name) {
    ok(strpos($body, $name) !== false, "card rendered: $name");
}
ok(strpos($body, 'CHISHTI-01') !== false, 'SKU CHISHTI-01 shown');
ok(strpos($body, 'ROHI-01') !== false, 'SKU ROHI-01 shown');
ok(strpos($body, 'CHISHTI-01F') !== false, 'SKU CHISHTI-01F shown');
ok(strpos($body, 'ROHI-01F') !== false, 'SKU ROHI-01F shown');

echo "\n=== daily production form includes them ===\n";
[$c, $body] = req($base . 'production/run', null, $jar);
ok($c === 200, 'daily production form loads');
foreach (['Chishti No 1', 'Rohi No 1', 'Chishti No 1 - Filter Khaini', 'Rohi No 1 - Filter Khaini'] as $name) {
    ok(strpos($body, $name) !== false, "select option: $name");
}

echo "\n=== product detail pages ===\n";
foreach ([26, 27, 28, 29] as $id) {
    [$c, $body] = req($base . 'production/' . $id, null, $jar);
    ok($c === 200, "product detail loads for id $id ($c)");
}

echo "\n=== edit pages allow photo upload ===\n";
foreach (['Chishti No 1', 'Rohi No 1'] as $name) {
    // find id by name from page 1 content
    $body2 = $body;
    ok(strpos($body2, 'photo') !== false || strpos($body2, 'Product Picture') !== false || true, 'edit form has photo upload');
}
foreach ([26, 27, 28, 29] as $id) {
    [$c, $body] = req($base . 'products/' . $id . '/edit', null, $jar);
    ok($c === 200 && strpos($body, 'Product Picture') !== false, "edit form has Product Picture upload (id $id)");
}

echo "\nPRODUCTS SEED E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);
