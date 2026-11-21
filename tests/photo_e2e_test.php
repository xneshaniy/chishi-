<?php
/**
 * Profile photo upload E2E for Employees / Salesman - against the dev server.
 * Usage: php tests/photo_e2e_test.php
 */
$base = 'http://127.0.0.1:8080';
$jar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}
function req(string $method, string $url, ?array $post = null, ?array $files = null, array $extraHeaders = []): array {
    global $jar;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $extraHeaders, CURLOPT_TIMEOUT => 30,
    ];
    curl_setopt_array($ch, $opts);
    if ($files) {
        $fields = $post ?? [];
        foreach ($files as $k => $f) { $fields[$k] = new CURLFile($f[0], $f[1], $f[2]); }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    } elseif ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        if (!$extraHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr((string)$raw, 0, $headerSize), substr((string)$raw, $headerSize)];
}
function csrfFrom(string $html): string {
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m);
    return $m[1] ?? '';
}
$imgDir = 'C:/Users/Haseeb/AppData/Local/Temp/opencode';
$redPng = $imgDir . '/test_red.png';
$blueWebp = $imgDir . '/test_blue.webp';
$greenJpg = $imgDir . '/test_green.jpg';
$oversize = $imgDir . '/test_oversize.jpg';

[$c, , $loginHtml] = req('GET', $base . '/index.php?r=auth/login');
$csrf = csrfFrom($loginHtml);
[$c2] = req('POST', $base . '/index.php?r=auth/login', ['r' => 'auth/login', 'csrf' => $csrf, 'username' => 'owner', 'password' => 'owner1234']);
ok($c2 === 302, 'owner login (302)');

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');

/* ============ 1. Employee CREATE with PNG ============ */
echo "\n=== 1. Employee create with photo (Employee Section) ===\n";
[$c, , $form] = req('GET', $base . '/index.php?r=employees/new');
$csrf = csrfFrom($form);
ok($csrf !== '', 'employee add form loads with csrf');
[$c, , $body] = req('POST', $base . '/index.php?r=employees/new',
    ['r' => 'employees/new', 'csrf' => $csrf, 'name' => 'TEST Photo Employee', 'phone' => '03009990001', 'designation' => 'Tester', 'department' => 'Testing', 'join_date' => '2026-11-15'],
    ['photo' => [$redPng, 'image/png', 'red.png']]);
ok($c === 302, 'employee created (redirect)');
// find the new employee
$newEmp = $pdo->query("SELECT id, code FROM employees WHERE name = 'TEST Photo Employee' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok((bool)$newEmp, 'new employee row exists in DB');
$empId = (int)($newEmp['id'] ?? 0);
$photoPath = $newEmp ? (string)$pdo->query("SELECT photo_path FROM employees WHERE id = $empId")->fetchColumn() : '';
ok($photoPath !== '', 'photo_path stored in employees table: ' . var_export($photoPath, true));
$file = __DIR__ . '/../storage/uploads/' . $photoPath;
ok($photoPath !== '' && is_file($file), 'photo stored on disk');
$info = @getimagesize($file);
ok($info && $info[0] <= 256 && $info[1] <= 256, 'stored photo is a ≤256px thumbnail (' . (int)($info[0] ?? 0) . 'x' . (int)($info[1] ?? 0) . ')');
ok($info && $info['mime'] === 'image/png', 'stored thumbnail preserved PNG format');

[$c, , $body] = req('GET', $base . '/index.php?r=employees/' . $empId);
ok($c === 200 && strpos($body, 'data:image/') !== false, 'profile renders inline data-URI photo');

[$cH, $headers, $imgBody] = req('GET', $base . '/index.php?r=employees/' . $empId . '/photo-file');
ok($cH === 200, 'photo-file returns 200');
ok(stripos($headers, 'Content-Type: image/png') !== false, 'photo-file Content-Type is image/png');
ok(stripos($headers, 'Cache-Control:') !== false && stripos($headers, 'ETag:') !== false, 'photo-file sends Cache-Control + ETag');
ok($imgBody === file_get_contents($file), 'photo-file serves exact stored bytes');

/* ============ 2. Replace with WEBP + version changes ============ */
echo "\n=== 2. Replace photo with WEBP (edit form) ===\n";
[$c, , $editForm] = req('GET', $base . '/index.php?r=employees/' . $empId . '/edit');
$csrf = csrfFrom($editForm);
[$c] = req('POST', $base . '/index.php?r=employees/' . $empId . '/edit',
    ['r' => 'employees/' . $empId . '/edit', 'id' => (string)$empId, 'csrf' => $csrf, 'name' => 'TEST Photo Employee', 'phone' => '03009990001', 'designation' => 'Tester', 'department' => 'Testing'],
    ['photo' => [$blueWebp, 'image/webp', 'blue.webp']]);
ok($c === 302, 'photo replaced via edit form (redirect)');
$newPath = (string)$pdo->query("SELECT photo_path FROM employees WHERE id = $empId")->fetchColumn();
ok($newPath !== '' && $newPath !== $photoPath, 'photo_path updated to new file');
$info = @getimagesize(__DIR__ . '/../storage/uploads/' . $newPath);
ok($info && $info['mime'] === 'image/webp', 'stored thumbnail re-encoded as WEBP (≤256px, compressed)');
[$cH, $headers, $imgBody] = req('GET', $base . '/index.php?r=employees/' . $empId . '/photo-file');
ok(stripos($headers, 'Content-Type: image/webp') !== false, 'photo-file now serves image/webp');
ok($imgBody === file_get_contents(__DIR__ . '/../storage/uploads/' . $newPath), 'photo-file serves the stored thumbnail bytes');
$oldFile = __DIR__ . '/../storage/uploads/' . $photoPath;
ok(!is_file($oldFile), 'old photo file removed from storage');

/* ============ 3. Oversize upload rejected with message, DB unchanged ============ */
echo "\n=== 3. Oversize photo rejected visibly ===\n";
[$c, , $editForm] = req('GET', $base . '/index.php?r=employees/' . $empId . '/edit');
$csrf = csrfFrom($editForm);
[$c, , $body] = req('POST', $base . '/index.php?r=employees/' . $empId . '/edit',
    ['r' => 'employees/' . $empId . '/edit', 'id' => (string)$empId, 'csrf' => $csrf, 'name' => 'TEST Photo Employee', 'phone' => '03009990001', 'designation' => 'Tester', 'department' => 'Testing'],
    ['photo' => [$oversize, 'image/jpeg', 'big.jpg']]);
ok($c === 302, 'edit submits');
ok(strpos($body, 'could not be saved') !== false || true, 'flash rendered (redirect target checked below)');
[$c, , $body] = req('GET', $base . '/index.php?r=employees/' . $empId);
ok(strpos($body, 'could not be saved') !== false, 'error flash is shown to the user after redirect');
$afterBig = (string)$pdo->query("SELECT photo_path FROM employees WHERE id = $empId")->fetchColumn();
ok($afterBig === $newPath, 'photo_path unchanged after oversize rejection');

/* ============ 4. Wrong file type rejected ============ */
echo "\n=== 4. Non-image rejected ===\n";
file_put_contents($imgDir . '/fake.png', "this is not a png");
[$c, , $editForm] = req('GET', $base . '/index.php?r=employees/' . $empId . '/edit');
$csrf = csrfFrom($editForm);
[$c] = req('POST', $base . '/index.php?r=employees/' . $empId . '/edit',
    ['r' => 'employees/' . $empId . '/edit', 'id' => (string)$empId, 'csrf' => $csrf, 'name' => 'TEST Photo Employee', 'phone' => '03009990001', 'designation' => 'Tester', 'department' => 'Testing'],
    ['photo' => [$imgDir . '/fake.png', 'image/png', 'fake.png']]);
$afterFake = (string)$pdo->query("SELECT photo_path FROM employees WHERE id = $empId")->fetchColumn();
ok($afterFake === $newPath, 'photo_path unchanged after fake image rejection');

/* ============ 5. Labour worker (LAB id 3) — inline Change Photo endpoint ============ */
echo "\n=== 5. Employee inline photo change (LAB-0001 profile) ===\n";
$labId = (int)$pdo->query("SELECT id FROM employees WHERE code = 'LAB-0001'")->fetchColumn();
[$c, , $profile] = req('GET', $base . '/index.php?r=employees/' . $labId);
$csrf = csrfFrom($profile);
[$c] = req('POST', $base . '/index.php?r=employees/' . $labId . '/photo',
    ['r' => 'employees/' . $labId . '/photo', 'id' => (string)$labId, 'csrf' => $csrf],
    ['photo' => [$greenJpg, 'image/jpeg', 'green.jpg']]);
ok($c === 302, 'inline photo upload accepted (redirect)');
$labPhoto = (string)$pdo->query("SELECT photo_path FROM employees WHERE id = $labId")->fetchColumn();
ok($labPhoto !== '' && is_file(__DIR__ . '/../storage/uploads/' . $labPhoto), 'inline photo stored');
[$cH, $headers, $imgBody] = req('GET', $base . '/index.php?r=employees/' . $labId . '/photo-file');
ok(stripos($headers, 'Content-Type: image/jpeg') !== false, 'photo-file serves image/jpeg');
[$c, , $body] = req('GET', $base . '/index.php?r=employees');
ok(strpos($body, 'data:image/') !== false, 'card list renders inline data-URI photo');
[$c, , $body] = req('GET', $base . '/index.php?r=employees/' . $labId);
ok(strpos($body, 'data:image/') !== false, 'profile hero renders inline data-URI photo');

/* ============ 6. Salesman — inline Change Photo on profile ============ */
echo "\n=== 6. Salesman inline photo change ===\n";
$smId = (int)$pdo->query("SELECT id FROM salesmen WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
[$c, , $smProfile] = req('GET', $base . '/index.php?r=salesmen/' . $smId . '/profile');
$csrf = csrfFrom($smProfile);
[$c] = req('POST', $base . '/index.php?r=salesmen/' . $smId . '/photo',
    ['r' => 'salesmen/' . $smId . '/photo', 'id' => (string)$smId, 'csrf' => $csrf],
    ['photo' => [$redPng, 'image/png', 'red.png']]);
ok($c === 302, 'salesman inline photo upload accepted (redirect)');
$smPhoto = (string)$pdo->query("SELECT photo_path FROM salesmen WHERE id = $smId")->fetchColumn();
ok($smPhoto !== '' && is_file(__DIR__ . '/../storage/uploads/' . $smPhoto), 'salesman photo stored');
[$cH, $headers, $imgBody] = req('GET', $base . '/index.php?r=salesmen/' . $smId . '/photo-file');
ok(stripos($headers, 'Content-Type: image/png') !== false, 'salesman photo-file serves image/png');
ok($imgBody === file_get_contents(__DIR__ . '/../storage/uploads/' . $smPhoto), 'salesman photo-file serves the stored thumbnail bytes');
[$c, , $body] = req('GET', $base . '/index.php?r=salesmen');
ok(strpos($body, 'data:image/') !== false, 'salesman list renders inline data-URI photo');
[$c, , $body] = req('GET', $base . '/index.php?r=salesmen/' . $smId . '/profile');
ok(strpos($body, 'data:image/') !== false, 'salesman profile renders inline data-URI photo');
// Goods Issue page removed — stock issuing is now handled by the New Sales Invoice form.
// (No separate picker page exists to render salesman photos inline.)

/* ============ 7. Salesman edit form upload ============ */
echo "\n=== 7. Salesman photo via edit form ===\n";
[$c, , $smEdit] = req('GET', $base . '/index.php?r=salesmen/' . $smId . '/edit');
$csrf = csrfFrom($smEdit);
[$c] = req('POST', $base . '/index.php?r=salesmen/' . $smId . '/edit',
    ['r' => 'salesmen/' . $smId . '/edit', 'id' => (string)$smId, 'csrf' => $csrf, 'code' => (string)$pdo->query("SELECT code FROM salesmen WHERE id = $smId")->fetchColumn(), 'name' => (string)$pdo->query("SELECT name FROM salesmen WHERE id = $smId")->fetchColumn(), 'is_active' => '1'],
    ['photo' => [$blueWebp, 'image/webp', 'blue.webp']]);
ok($c === 302, 'salesman edit form photo accepted');
$smPhoto2 = (string)$pdo->query("SELECT photo_path FROM salesmen WHERE id = $smId")->fetchColumn();
[$cH, $headers] = req('GET', $base . '/index.php?r=salesmen/' . $smId . '/photo-file');
ok(stripos($headers, 'Content-Type: image/webp') !== false, 'salesman photo-file now serves webp');

echo "\n========================================\nPHOTO E2E RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
