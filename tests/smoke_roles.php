<?php
/** HTTP smoke test: three-role policy pages render for an owner session. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');
function req(string $url, ?string $post = null, ?string $jar = null, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $raw];
}
[$c, $body] = req($base . 'auth/login', null, $jar);
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
[$c] = req($base . 'auth/login', 'csrf=' . urlencode($m[1] ?? '') . '&username=owner&password=owner1234', $jar, false);
echo "login: $c\n";
foreach (['admin/team','users','users/new','users/5/edit','advances','admin/approvals','employees','salesmen','payroll'] as $page) {
    [$c, $body] = req($base . $page, null, $jar);
    $err = (stripos($body, 'Fatal error') !== false || stripos($body, 'Parse error') !== false || stripos($body, 'Deprecated:') !== false) ? 'PHP-ERROR' : 'clean';
    echo str_pad($page, 22), " $c $err\n";
}
// edit view of a DEACTIVATED user renders the authorized-roles notice
[$c2, $userList] = req($base . 'users', null, $jar);
echo (strpos($userList, 'salesman1') !== false) ? "users list shows salesman1\n" : "salesman1 missing from list\n";
// login page shows the authorized-roles note
$jar2 = tempnam(sys_get_temp_dir(), 'ck2');
[$c3, $loginPage] = req($base . 'auth/login', null, $jar2);
echo (strpos($loginPage, 'Owner/Admin, Manager and Accountant') !== false) ? "login note present\n" : "login note MISSING\n";
unlink($jar); unlink($jar2);
