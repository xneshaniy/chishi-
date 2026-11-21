<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$temp = tempnam(sys_get_temp_dir(), 'ftp_err_');
$remoteLog = 'domains/chishtitobaccocompany.com/public_html/software/storage/logs/error.log';

if (@ftp_get($conn, $temp, $remoteLog, FTP_BINARY)) {
    echo "=== REMOTE ERROR LOG ===\n";
    $content = file_get_contents($temp);
    echo $content ? substr($content, -3000) : "Log file is empty\n";
} else {
    echo "Could not fetch {$remoteLog}. Checking storage/logs dir...\n";
    print_r(ftp_nlist($conn, 'domains/chishtitobaccocompany.com/public_html/software/storage/logs'));
}
unlink($temp);
ftp_close($conn);
