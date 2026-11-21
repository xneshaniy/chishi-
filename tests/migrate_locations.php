<?php
/** Create the location tables in dev.sqlite if missing (additive). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS pk_provinces (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(60) NOT NULL UNIQUE,
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS pk_cities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  province_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  CONSTRAINT fk_pkcity_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE CASCADE,
  CONSTRAINT uq_pkcity UNIQUE (province_id, name)
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS areas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  province_id INT NULL,
  city_id INT NULL,
  name VARCHAR(120) NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT uq_area_city UNIQUE (city_id, name),
  CONSTRAINT fk_area_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE SET NULL,
  CONSTRAINT fk_area_city FOREIGN KEY (city_id) REFERENCES pk_cities(id) ON DELETE CASCADE
)");
foreach (['customers', 'salesmen', 'employees', 'users'] as $t) {
    $cols = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['province_id' => 'INT NULL', 'city_id' => 'INT NULL', 'area_id' => 'INT NULL', 'deactivated_at' => 'DATETIME NULL', 'deactivated_by' => 'INT NULL'] as $name => $def) {
        if (!in_array($name, $cols, true)) { $pdo->exec("ALTER TABLE $t ADD COLUMN $name $def"); }
    }
    if ($t === 'salesmen' && !in_array('photo_path', $cols, true)) $pdo->exec("ALTER TABLE salesmen ADD COLUMN photo_path VARCHAR(255) NULL");
    if ($t === 'employees' && !in_array('kind', $cols, true)) $pdo->exec("ALTER TABLE employees ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'employee'");
}
echo "location tables ready\n";
