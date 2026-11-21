<?php
/**
 * CLI backup for cron — e.g. Hostinger hPanel → Advanced → Cron Jobs:
 *   php /home/USER/public_html/bin/backup.php
 * Writes storage/backups/ctc_erp_YYYYMMDD_HHMMSS.sql and prunes files older than 30 days (keep 10 minimum).
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Backup.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the web installer first.\n"); exit(1); }
$GLOBALS['CONFIG'] = require $configPath;
date_default_timezone_set($GLOBALS['CONFIG']['app']['timezone'] ?? 'Asia/Karachi');

$backupDir = __DIR__ . '/../storage/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
$file = $backupDir . '/ctc_erp_' . date('Ymd_His') . '.sql';
$rows = Backup::dump($file);
echo 'Backup written: ', $file, ' (', number_format(filesize($file) / 1024, 1), " KB, $rows rows)\n";

// Prune: keep last 10, delete anything older than 30 days beyond that
$files = glob($backupDir . '/ctc_erp_*.sql') ?: [];
rsort($files);
foreach ($files as $i => $f) {
    if ($i >= 10 && (time() - filemtime($f)) > 30 * 86400) { unlink($f); echo 'Pruned old backup: ', basename($f), "\n"; }
}
