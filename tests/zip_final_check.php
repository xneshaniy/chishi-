<?php
$z = new ZipArchive();
$z->open(__DIR__ . '/../justi_erp_production.zip');
$files = [];
for ($i = 0; $i < $z->numFiles; $i++) $files[] = $z->getNameIndex($i);
sort($files);
$img = ['install/index.php', 'database/seed.php', 'config/config.sample.php', 'app/services/Payroll.php', 'app/services/Approvals.php', 'docs/APPROVALS-AND-BONUS.md', 'docs/TEST-CHECKLIST.md', 'docs/DEPLOYMENT-CHECKLIST.md', 'dev-router.php'];
foreach ($img as $f) echo (in_array($f, $files, true) ? '  OK   ' : '  MISS ') . $f . "\n";
echo "\nTotal: ", count($files), " files\n";
$z->close();
