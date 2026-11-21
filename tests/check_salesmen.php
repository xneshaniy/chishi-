<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT id, code, name, phone, cnic, area, province_id, city_id, area_id, photo_path FROM salesmen ORDER BY id") as $r)
    echo "id={$r['id']} code={$r['code']} name={$r['name']} phone={$r['phone']} cnic={$r['cnic']} area={$r['area']} prov={$r['province_id']} city={$r['city_id']} area_id={$r['area_id']} photo={$r['photo_path']}\n";
