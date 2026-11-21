<?php
/** Verify photo storage + streaming display path for employee & salesman. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$emp = $pdo->query("SELECT id, photo_path FROM employees WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$sm = $pdo->query("SELECT id, photo_path FROM salesmen ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "employee photo_path: ", var_export($emp['photo_path'], true), "\n";
$base = __DIR__ . '/../storage/uploads/';
echo "file exists on disk: ", is_file($base . $emp['photo_path']) ? 'yes' : 'no', "\n";
echo "salesman photo_path: ", var_export($sm['photo_path'], true), "\n";

$base2 = 'http://127.0.0.1:8080';
$cookie = tempnam(sys_get_temp_dir(), 'cu');
$loginHtml = file_get_contents($base2 . '/index.php?r=auth/login');
preg_match('/name="csrf" value="([^"]+)"/', $loginHtml, $m);
$csrf = $m[1];
// find session cookie from login page
$hdr = get_headers($base2 . '/index.php?r=auth/login');

$ch = curl_init($base2 . '/index.php?r=auth/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'r=auth/login&csrf=' . urlencode($csrf) . '&username=owner&password=owner1234',
    CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
]);
$resp = curl_exec($ch); curl_close($ch);
echo "\n[after login] HTTP ", curl_getinfo($ch, CURLINFO_HTTP_CODE), "\n";

$photo = $base2 . '/index.php?r=staff%2F1%2Fphoto-file';
$ch = curl_init($photo);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_HEADER => true]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);
echo "photo-file: HTTP $code type=$ctype size=" . strlen($body) . "\n";
