<?php
/** Salesman profile migration (additive) for dev.sqlite — idempotent. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');

$cols = array_column($pdo->query('PRAGMA table_info(salesmen)')->fetchAll(PDO::FETCH_ASSOC), 'name');
$add = [
    'cnic_front_path' => 'TEXT NULL',
    'cnic_back_path' => 'TEXT NULL',
];
foreach ($add as $name => $def) {
    if (!in_array($name, $cols, true)) { $pdo->exec("ALTER TABLE salesmen ADD COLUMN $name $def"); echo "added salesmen.$name\n"; }
    else echo "salesmen.$name already exists\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS salesman_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  doc_type VARCHAR(60) NOT NULL DEFAULT 'other',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sdoc_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
)");
echo "salesman_documents ready\n";

require __DIR__ . '/../database/seed.php';
seed_locations($pdo);
$prov = $pdo->query('SELECT COUNT(*) FROM pk_provinces')->fetchColumn();
$cit = $pdo->query('SELECT COUNT(*) FROM pk_cities')->fetchColumn();
echo "locations seeded: $prov provinces, $cit cities\n";
