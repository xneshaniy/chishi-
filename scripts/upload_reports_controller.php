<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$remote = 'domains/chishtitobaccocompany.com/public_html/software/app/controllers/ReportsController.php';
$local = __DIR__ . '/../app/controllers/ReportsController.php';
$res = ftp_put($conn, $remote, $local, FTP_BINARY);
echo "Uploaded ReportsController.php: " . ($res ? "OK" : "FAILED") . "\n";

ftp_close($conn);
