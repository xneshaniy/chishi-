<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$remotePath = 'domains/chishtitobaccocompany.com/public_html/software/clean_emp.php';
$localPath = __DIR__ . '/clean_test_emp.php';

ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
ftp_close($conn);

$url = 'https://software.chishtitobaccocompany.com/clean_emp.php';
$output = file_get_contents($url);
echo "Result: {$output}\n";

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);
ftp_delete($conn, $remotePath);
ftp_close($conn);
