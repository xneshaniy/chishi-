<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';
$ftpPort = 21;

echo "Connecting to FTP {$ftpHost}:{$ftpPort}...\n";
$conn = @ftp_connect($ftpHost, $ftpPort, 15);
if (!$conn) {
    // Try ssl connect
    echo "ftp_connect standard failed, trying ftp_ssl_connect...\n";
    $conn = @ftp_ssl_connect($ftpHost, $ftpPort, 15);
}

if (!$conn) {
    die("Could not connect to FTP host {$ftpHost}\n");
}

echo "Connected! Logging in as {$ftpUser}...\n";
if (!@ftp_login($conn, $ftpUser, $ftpPass)) {
    die("FTP login failed for {$ftpUser}\n");
}

echo "Login successful!\n";
ftp_pasv($conn, true);

echo "Current directory: " . ftp_pwd($conn) . "\n";
$nlist = ftp_nlist($conn, '.');
echo "Root listing:\n";
print_r($nlist);

$publicHtml = ftp_nlist($conn, 'public_html');
echo "public_html listing:\n";
print_r($publicHtml);

ftp_close($conn);
