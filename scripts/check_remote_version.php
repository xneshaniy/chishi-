<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$temp = tempnam(sys_get_temp_dir(), 'ftp_check_');
ftp_get($conn, $temp, 'domains/chishtitobaccocompany.com/public_html/software/install/index.php', FTP_BINARY);
$content = file_get_contents($temp);
unlink($temp);

echo "Remote install/index.php length: " . strlen($content) . "\n";
echo "Contains Naveed? " . (strpos($content, 'Naveed') !== false ? 'YES' : 'NO') . "\n";
echo "Contains DROP TABLE IF EXISTS? " . (strpos($content, 'DROP TABLE IF EXISTS') !== false ? 'YES' : 'NO') . "\n";

$tempSql = tempnam(sys_get_temp_dir(), 'ftp_sql_');
ftp_get($conn, $tempSql, 'domains/chishtitobaccocompany.com/public_html/software/database/schema.mysql.sql', FTP_BINARY);
$sqlContent = file_get_contents($tempSql);
unlink($tempSql);

echo "Remote schema.mysql.sql length: " . strlen($sqlContent) . "\n";
echo "Contains inline comment on payment_type line 669? " . (strpos($sqlContent, 'payment_type VARCHAR(10) NOT NULL DEFAULT \'cash\',  --') !== false ? 'YES' : 'NO') . "\n";

ftp_close($conn);
