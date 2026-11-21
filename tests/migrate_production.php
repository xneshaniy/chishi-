<?php
/** Add products.photo_path + products.description + daily_production(_items) to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$cols = $pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'name');
if (!in_array('photo_path', $names, true)) { $pdo->exec('ALTER TABLE products ADD COLUMN photo_path VARCHAR(255) NULL'); echo "products.photo_path added\n"; } else { echo "products.photo_path exists\n"; }
if (!in_array('description', $names, true)) { $pdo->exec('ALTER TABLE products ADD COLUMN description TEXT NULL'); echo "products.description added\n"; } else { echo "products.description exists\n"; }
$pdo->exec("CREATE TABLE IF NOT EXISTS daily_production (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  run_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  reversed_at DATETIME NULL,
  reversed_by INT NULL,
  reversal_reason VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_dp_date ON daily_production (date)');
$pdo->exec("CREATE TABLE IF NOT EXISTS daily_production_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  run_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  batch_no VARCHAR(60) NULL,
  line_notes VARCHAR(255) NULL,
  CONSTRAINT fk_dpi_run FOREIGN KEY (run_id) REFERENCES daily_production(id) ON DELETE CASCADE,
  CONSTRAINT fk_dpi_p FOREIGN KEY (product_id) REFERENCES products(id)
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_dpi_p ON daily_production_items (product_id, run_id)');
echo "daily_production: ", ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'daily_production'")->fetchColumn() ? 'ok' : 'MISSING'), "\n";
echo "daily_production_items: ", ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'daily_production_items'")->fetchColumn() ? 'ok' : 'MISSING'), "\n";
