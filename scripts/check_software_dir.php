<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$sw = 'domains/chishtitobaccocompany.com/public_html/software';
echo "=== Listing {$sw} ===\n";
print_r(ftp_nlist($conn, $sw));

ftp_close($conn);
