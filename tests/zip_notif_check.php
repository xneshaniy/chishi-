<?php
$z = new ZipArchive();
$z->open(__DIR__ . '/../justi_erp_production.zip');
$files = [];
for ($i = 0; $i < $z->numFiles; $i++) $files[] = $z->getNameIndex($i);
foreach (['app/controllers/NotificationsController.php', 'app/services/Notifications.php', 'app/views/notifications/index.php', 'docs/NOTIFICATIONS.md', 'app/views/layout.php'] as $f) {
    $idx = array_search($f, $files, true);
    echo ($idx !== false ? '  OK   ' : '  MISS '), $f, $idx !== false ? ' — ' . number_format($z->statIndex($idx)['size']) . ' bytes' : '', "\n";
}
echo "total: ", count($files), " files\n";
$z->close();
