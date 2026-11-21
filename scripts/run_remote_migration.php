<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$remotePath = 'domains/chishtitobaccocompany.com/public_html/software/migrate_run.php';
$localPath = __DIR__ . '/migrate_live_db.php';

echo "Uploading migration script to remote server...\n";
if (ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
    echo "Uploaded migrate_run.php!\n";
} else {
    die("Failed to upload migration script.\n");
}
ftp_close($conn);

// Trigger via HTTP
$url = 'https://software.chishtitobaccocompany.com/migrate_run.php';
echo "Executing {$url}...\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$output = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$code}\n";
echo "Output:\n{$output}\n";

// Delete migration script from remote server for security
$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);
ftp_delete($conn, $remotePath);
ftp_close($conn);
echo "Deleted remote migrate_run.php. Done!\n";
