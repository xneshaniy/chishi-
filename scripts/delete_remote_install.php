<?php
$ftp = ftp_connect('82.25.96.121', 21, 15) ?: ftp_ssl_connect('82.25.96.121', 21, 15);
ftp_login($ftp, 'u997441419', 'Chishti@786@');
ftp_pasv($ftp, true);

function deleteFtpDir($ftp, $dir) {
    $files = ftp_nlist($ftp, $dir);
    if ($files) {
        foreach ($files as $file) {
            $base = basename($file);
            if ($base === '.' || $base === '..') continue;
            // check if dir
            if (@ftp_chdir($ftp, $file)) {
                ftp_cdup($ftp);
                deleteFtpDir($ftp, $file);
            } else {
                @ftp_delete($ftp, $file);
            }
        }
    }
    return @ftp_rmdir($ftp, $dir);
}

$installDir = 'domains/chishtitobaccocompany.com/public_html/software/install';
echo "Deleting remote install directory for security...\n";
$res = deleteFtpDir($ftp, $installDir);
echo "Delete install directory: " . ($res ? "OK" : "FAILED or already gone") . "\n";

ftp_close($ftp);
