<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$base = 'domains/chishtitobaccocompany.com';
echo "=== Listing {$base} ===\n";
print_r(ftp_nlist($conn, $base));

$sub = "{$base}/public_html";
echo "=== Listing {$sub} ===\n";
print_r(ftp_nlist($conn, $sub));

ftp_close($conn);
