<?php
/**
 * FTP uploader for ctc_diag.php to web root
 */
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) or die("FTP connect failed\n");
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$base = 'domains/chishtitobaccocompany.com/public_html/software';
$local = __DIR__ . '/../ctc_diag.php';
$remote = "$base/ctc_diag.php";

$res = ftp_put($conn, $remote, $local, FTP_BINARY);
echo "Upload ctc_diag.php: " . ($res ? "OK" : "FAILED") . "\n";

ftp_close($conn);
