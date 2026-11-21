<?php
/** Migrate dev.sqlite to the RM purchasing / packaging / consumption schema. Safe to re-run. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colExists(PDO $pdo, string $t, string $c): bool {
    foreach ($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC) as $r) if ($r['name'] === $c) return true;
    return false;
}
if (!colExists($pdo, 'purchase_items', 'unit_short')) {
    $pdo->exec("ALTER TABLE purchase_items ADD COLUMN unit_short VARCHAR(10) NULL");
    echo "purchase_items.unit_short added\n";
} else echo "unit_short exists\n";
if (!colExists($pdo, 'purchase_items', 'qty_kg')) {
    $pdo->exec("ALTER TABLE purchase_items ADD COLUMN qty_kg DECIMAL(18,3) NULL");
    echo "purchase_items.qty_kg added\n";
} else echo "qty_kg exists\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS product_packaging (
  product_id INT PRIMARY KEY,
  pack_pieces DECIMAL(10,3) NULL,
  bundle_packs DECIMAL(10,3) NULL,
  packet_bundles DECIMAL(10,3) NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_pack_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");
echo "product_packaging ensured\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS factory_issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  batch_no VARCHAR(60) NULL,
  details VARCHAR(255) NULL,
  production_order_id INT NULL,
  issued_by INT NULL,
  approved_by INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fi_po FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE SET NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS factory_issue_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INT NOT NULL,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_fii_i FOREIGN KEY (issue_id) REFERENCES factory_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_fii_p FOREIGN KEY (product_id) REFERENCES products(id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_fii_p ON factory_issue_items (product_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_fii_i ON factory_issue_items (issue_id)");
echo "factory_issues ensured\n";

// Seed MAUND unit if missing
$man = $pdo->query("SELECT id FROM units WHERE short = 'MAN'")->fetchColumn();
if (!$man) { $pdo->exec("INSERT INTO units (name, short) VALUES ('Maund','MAN')"); echo "Maund unit added\n"; } else echo "Maund unit exists\n";

// Backfill qty_kg for existing raw/packaging purchase lines (products are KG already)
$n = $pdo->exec("UPDATE purchase_items SET qty_kg = qty, unit_short = 'KG' WHERE qty_kg IS NULL");
echo "purchase_items backfilled: $n\n";
// Lock raw/packaging products to KG on the dev DB too
$kgId = (int)$pdo->query("SELECT id FROM units WHERE short='KG'")->fetchColumn();
$n = $pdo->exec("UPDATE products SET unit_id = $kgId WHERE kind IN ('raw','packaging') AND unit_id IS NOT NULL AND unit_id <> $kgId");
echo "raw/packaging products re-based to KG: $n\n";
// Sanity: any factory_consumption details
