<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$scriptToRun = $argv[1] ?? '';
if (!$scriptToRun || !file_exists($scriptToRun)) {
    die("Usage: php exec_remote_php.php <path_to_php_script>\n");
}

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

$rndName = 'temp_run_' . bin2hex(random_bytes(4)) . '.php';
$remotePath = "domains/chishtitobaccocompany.com/public_html/software/{$rndName}";

if (!ftp_put($conn, $remotePath, $scriptToRun, FTP_BINARY)) {
    ftp_close($conn);
    die("Failed to upload {$rndName}\n");
}
ftp_close($conn);

$url = "https://software.chishtitobaccocompany.com/{$rndName}";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);
$output = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$code}\n";
echo "Output:\n{$output}\n";

// Cleanup
$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);
ftp_delete($conn, $remotePath);
ftp_close($conn);
echo "Cleaned up {$rndName}.\n";
