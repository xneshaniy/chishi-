<?php
$ftp = ftp_connect('82.25.96.121', 21, 15) ?: ftp_ssl_connect('82.25.96.121', 21, 15);
ftp_login($ftp, 'u997441419', 'Chishti@786@');
ftp_pasv($ftp, true);
$res = ftp_put($ftp, 'domains/chishtitobaccocompany.com/public_html/software/install/index.php', __DIR__ . '/../install/index.php', FTP_BINARY);
ftp_close($ftp);
echo "Uploaded install/index.php: " . ($res ? "OK" : "FAILED") . "\n";
