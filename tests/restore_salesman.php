<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec("UPDATE salesmen SET province_id = NULL, city_id = NULL, area_id = NULL, address = 'Main City', area = 'Main City' WHERE id = 2");
$pdo->exec("DELETE FROM areas WHERE name = 'Farid Gate'");
foreach ($pdo->query("SELECT id, code, area, province_id, city_id, area_id, address FROM salesmen WHERE id = 2") as $r)
    echo "restored: id={$r['id']} code={$r['code']} area={$r['area']} prov={$r['province_id']} city={$r['city_id']} area_id={$r['area_id']} addr={$r['address']}\n";
echo "areas left: ", $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn(), "\n";
