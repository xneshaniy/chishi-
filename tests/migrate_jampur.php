<?php
/** Development migration for the Jampur grinding module (non-destructive). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("CREATE TABLE IF NOT EXISTS grinding_sends (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_no VARCHAR(40) NOT NULL UNIQUE,
  date DATE NOT NULL,
  product_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  party_name VARCHAR(150) NOT NULL,
  charges_per_kg DECIMAL(18,4) NULL,
  vehicle VARCHAR(120) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gs_prod FOREIGN KEY (product_id) REFERENCES products(id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS grinding_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_id INT NOT NULL,
  grind_date DATE NOT NULL,
  qty_received_kg DECIMAL(18,3) NOT NULL,
  loss_kg DECIMAL(18,3) NOT NULL DEFAULT 0,
  charges DECIMAL(18,2) NOT NULL DEFAULT 0,
  charges_status VARCHAR(10) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gr_send FOREIGN KEY (send_id) REFERENCES grinding_sends(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS grinding_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_id INT NOT NULL,
  return_date DATE NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gret_send FOREIGN KEY (send_id) REFERENCES grinding_sends(id)
)");

echo "tables: ", $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name IN ('grinding_sends','grinding_records','grinding_returns')")->fetchColumn(), "\n";
