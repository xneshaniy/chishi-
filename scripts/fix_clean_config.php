<?php
$ftp = ftp_connect('82.25.96.121', 21, 15) ?: ftp_ssl_connect('82.25.96.121', 21, 15);
ftp_login($ftp, 'u997441419', 'Chishti@786@');
ftp_pasv($ftp, true);

$cleanConfig = <<<'PHP'
<?php
/**
 * JUSTI NUMBER ONE ERP — Production Configuration
 * Chishti Tobacco Company (CTC)
 */
return [
    'db' => [
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'name'    => 'u997441419_software',
        'user'    => 'u997441419_software',
        'pass'    => 'Chishti@786@',
        'charset' => 'utf8mb4',
        'port'    => 3306,
    ],

    'app' => [
        'name'      => 'JUSTI NUMBER ONE ERP',
        'company'   => 'Chishti Tobacco Company',
        'env'       => 'production',
        'debug'     => false,
        'timezone'  => 'Asia/Karachi',
        'currency'  => 'PKR',
        'language'  => 'en',
        'base_url'  => 'https://software.chishtitobaccocompany.com',
        'https'     => true,
    ],

    'costing_method' => 'weighted_average',
    'allow_negative_stock' => false,
    'default_tax_rate' => 18.0,

    'session' => [
        'name'     => 'CTC_ERP_SESS',
        'lifetime' => 120,
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
    ],

    'uploads' => [
        'max_mb'        => 5,
        'allowed_types' => ['jpg','jpeg','png','gif','webp','pdf','csv','xls','xlsx','doc','docx'],
    ],
];
PHP;

$temp = tempnam(sys_get_temp_dir(), 'clean_cfg_');
file_put_contents($temp, $cleanConfig);
$res = ftp_put($ftp, 'domains/chishtitobaccocompany.com/public_html/software/config/config.php', $temp, FTP_BINARY);
unlink($temp);

ftp_close($ftp);
echo "Uploaded clean config/config.php: " . ($res ? "OK" : "FAILED") . "\n";
