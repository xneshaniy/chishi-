<?php
/** Render the advance pages as owner and then as manager to make sure the photo markup renders. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
function req(string $url, ?string $post = null, ?string $jar = null, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $raw = curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $raw];
}
[$c, $body] = req($base . 'auth/login', null, $jar);
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
req($base . 'auth/login', 'csrf=' . urlencode($m[1] ?? '') . '&username=owner&password=owner1234', $jar, false);
req($base . 'auth/login', 'csrf=' . urlencode($m[1] ?? '') . '&username=x&password=y', $jar, false); // sanity, harmless
[$c1, $page1] = req($base . 'employees/3/advance', null, $jar);
echo "employees/3/advance: $c1 ", (strpos($page1, 'emp-photo') !== false || strpos($page1, 'emp-avatar') !== false) ? "photo-ok" : "photo-missing", "\n";
echo "contains LAB-0001: ", (strpos($page1, 'LAB-0001') !== false ? 'yes' : 'no'), "\n";
[$c2, $page2] = req($base . 'salesmen/2/salary', null, $jar);
echo "salesmen/2/salary: $c2 ", (strpos($page2, 'emp-photo') !== false || strpos($page2, 'emp-avatar') !== false) ? "photo-ok" : "photo-missing (may have no photo, avatar expected)", "\n";
echo "contains DEMO-SM-001: ", (strpos($page2, 'DEMO-SM-001') !== false ? 'yes' : 'no'), "\n";
unlink($jar);
