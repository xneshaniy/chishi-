<?php
$ftp = ftp_connect('82.25.96.121', 21, 15) ?: ftp_ssl_connect('82.25.96.121', 21, 15);
ftp_login($ftp, 'u997441419', 'Chishti@786@');
ftp_pasv($ftp, true);

$temp = tempnam(sys_get_temp_dir(), 'ftp_cfg_');
ftp_get($ftp, $temp, 'domains/chishtitobaccocompany.com/public_html/software/config/config.php', FTP_BINARY);
$content = file_get_contents($temp);
unlink($temp);

echo "Remote config/config.php:\n";
echo $content;

ftp_close($ftp);
