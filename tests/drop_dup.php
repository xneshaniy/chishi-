<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try { $pdo->exec("ALTER TABLE salesmen DROP COLUMN monthly_salary"); echo "monthly_salary dropped\n"; } catch (Throwable $e) { echo "drop: ", $e->getMessage(), "\n"; }
foreach ($pdo->query("PRAGMA table_info(salesmen)")->fetchAll(PDO::FETCH_ASSOC) as $c) echo $c['name'], " ({$c['type']})\n";
