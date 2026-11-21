<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT id, code, province_id, city_id, area_id, area FROM salesmen WHERE id = 2") as $r) {
    echo "id={$r['id']} code={$r['code']} province={$r['province_id']} city={$r['city_id']} area_id={$r['area_id']} area={$r['area']}\n";
}
foreach ($pdo->query("SELECT a.id, a.name, p.name AS province, c.name AS city FROM areas a LEFT JOIN pk_provinces p ON p.id = a.province_id LEFT JOIN pk_cities c ON c.id = a.city_id") as $r) {
    echo "area#{$r['id']} {$r['name']} — {$r['province']} / {$r['city']}\n";
}
