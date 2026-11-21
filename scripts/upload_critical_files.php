<?php
$ftpHost = '82.25.96.121';
$ftpUser = 'u997441419';
$ftpPass = 'Chishti@786@';

echo "Connecting to FTP...\n";
$conn = ftp_connect($ftpHost, 21, 20) ?: ftp_ssl_connect($ftpHost, 21, 20);
if (!$conn || !ftp_login($conn, $ftpUser, $ftpPass)) {
    die("FTP connection/login failed\n");
}
ftp_pasv($conn, true);

$baseRemote = 'domains/chishtitobaccocompany.com/public_html/software';

// 1. Upload fixed schema.mysql.sql
echo "Uploading database/schema.mysql.sql...\n";
if (ftp_put($conn, "{$baseRemote}/database/schema.mysql.sql", __DIR__ . '/../database/schema.mysql.sql', FTP_BINARY)) {
    echo " -> schema.mysql.sql uploaded successfully!\n";
} else {
    echo " -> FAILED uploading schema.mysql.sql!\n";
}

// 2. Upload install/index.php
echo "Uploading install/index.php...\n";
if (ftp_put($conn, "{$baseRemote}/install/index.php", __DIR__ . '/../install/index.php', FTP_BINARY)) {
    echo " -> install/index.php uploaded successfully!\n";
} else {
    echo " -> FAILED uploading install/index.php!\n";
}

// 3. Upload chishti_software_live.zip
echo "Uploading chishti_software_live.zip...\n";
if (ftp_put($conn, "{$baseRemote}/chishti_software_live.zip", __DIR__ . '/../chishti_software_live.zip', FTP_BINARY)) {
    echo " -> chishti_software_live.zip uploaded successfully!\n";
} else {
    echo " -> FAILED uploading chishti_software_live.zip!\n";
}

ftp_close($conn);
echo "FTP upload completed.\n";
