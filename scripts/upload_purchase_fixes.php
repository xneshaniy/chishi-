<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$base = 'domains/chishtitobaccocompany.com/public_html/software';

echo "Uploading PurchaseController.php...\n";
$r1 = ftp_put($conn, "{$base}/app/controllers/PurchaseController.php", __DIR__ . '/../app/controllers/PurchaseController.php', FTP_BINARY);
echo "PurchaseController.php: " . ($r1 ? "OK" : "FAILED") . "\n";

echo "Uploading app/views/raw/usage.php...\n";
$r2 = ftp_put($conn, "{$base}/app/views/raw/usage.php", __DIR__ . '/../app/views/raw/usage.php', FTP_BINARY);
echo "app/views/raw/usage.php: " . ($r2 ? "OK" : "FAILED") . "\n";

ftp_close($conn);
