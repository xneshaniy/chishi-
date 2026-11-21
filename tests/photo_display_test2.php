<?php
/** Proper end-to-end: login with session cookies, then hit photo-file streaming route as browser would. */
$cookie = sys_get_temp_dir() . '/photo_test_' . getmypid() . '.txt';
@unlink($cookie);
$base = 'http://127.0.0.1:8080';

// 1) GET login page with cookie jar
$ch = curl_init($base . '/index.php?r=auth/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
$loginHtml = curl_exec($ch);
curl_close($ch);
preg_match('/name="csrf" value="([^"]+)"/', $loginHtml, $m);
$csrf = $m[1];

// 2) POST login
$ch = curl_init($base . '/index.php?r=auth/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'r=auth/login&csrf=' . urlencode($csrf) . '&username=owner&password=owner1234',
    CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "login POST: HTTP $code\n";

// 3) stream the employee photo
$ch = curl_init($base . '/index.php?r=staff%2F1%2Fphoto-file');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_HEADER => true]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$size = strlen($body);
curl_close($ch);
echo "photo-file: HTTP $code type=$ctype bytes=$size\n";

// serve the salesman photo route too (expect 404 because no photo set yet)
$ch = curl_init($base . '/index.php?r=salesmen%2F1%2Fphoto-file');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_HEADER => true]);
$body2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype2 = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$size2 = strlen($body2);
curl_close($ch);
echo "salesman photo-file (not set yet): HTTP $code2 type=$ctype2 bytes=$size2\n";
