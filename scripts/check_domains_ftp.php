<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

$conn = ftp_connect($ftpHost, 21, 15) ?: ftp_ssl_connect($ftpHost, 21, 15);
ftp_login($conn, $ftpUser, $ftpPass);
ftp_pasv($conn, true);

function scanFtp($conn, $dir) {
    echo "=== Listing {$dir} ===\n";
    $list = ftp_nlist($conn, $dir);
    if ($list) {
        foreach ($list as $item) {
            echo " - {$item}\n";
        }
    } else {
        echo " (empty or cannot read)\n";
    }
}

scanFtp($conn, 'domains');
$domains = ftp_nlist($conn, 'domains');
if ($domains) {
    foreach ($domains as $d) {
        scanFtp($conn, "domains/{$d}");
        scanFtp($conn, "domains/{$d}/public_html");
    }
}

ftp_close($conn);
