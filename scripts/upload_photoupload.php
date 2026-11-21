<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$remotePath = 'domains/chishtitobaccocompany.com/public_html/software/app/services/PhotoUpload.php';
$localPath = __DIR__ . '/../app/services/PhotoUpload.php';

$res = ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
echo "Uploaded PhotoUpload.php: " . ($res ? "OK" : "FAILED") . "\n";

ftp_close($conn);
