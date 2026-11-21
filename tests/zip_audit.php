<?php
$z = new ZipArchive();
$z->open(__DIR__ . '/../justi_erp_production.zip');
$files = [];
for ($i = 0; $i < $z->numFiles; $i++) $files[] = $z->getNameIndex($i);
sort($files);
echo count($files), " files:\n";
foreach ($files as $f) echo "  $f\n";

echo "\n--- sanity checks\n";
$must = ['index.php','.htaccess','.gitignore','install/index.php','config/config.sample.php','config/.htaccess','database/schema.mysql.sql','database/seed.php','database/.htaccess','bin/backup.php','bin/.htaccess','storage/.htaccess','storage/logs/.gitkeep','storage/backups/.gitkeep','storage/uploads/.gitkeep','app/core/bootstrap.php','assets/img/ctc-logo.svg','docs/DEPLOYMENT.md','docs/TEST-CHECKLIST.md','docs/DEPLOYMENT-CHECKLIST.md'];
foreach ($must as $m) echo (in_array($m, $files, true) ? '  OK   ' : '  MISS ') . $m . "\n";

echo "\n--- exclusion confirmation (must ALL be absent)\n";
$mustNot = ['config/config.php','storage/dev.sqlite','tests/functional.php','tests/sweep.php','tests/dev_init.php','tests/build_production_zip.php','database/seed_demo.php','database/schema.sqlite.sql','storage/logs/error.log'];
foreach ($mustNot as $m) echo (in_array($m, $files, true) ? '  LEAK!' : '  OK   ') . $m . "\n";

echo "\n--- directory/file counts\n";
$byDir = [];
foreach ($files as $f) { $d = explode('/', $f)[0]; $byDir[$d] = ($byDir[$d] ?? 0) + 1; }
foreach ($byDir as $d => $n) echo "  $d: $n file(s)\n";

echo "\n--- storage .htaccess content\n", $z->getFromName('storage/.htaccess');
echo "\n--- root .htaccess content\n", $z->getFromName('.htaccess');
$z->close();
