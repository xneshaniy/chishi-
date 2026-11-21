<?php
/** Migrate storage/dev.sqlite to maintenance schema + seed new permissions/category. */
require __DIR__ . '/../app/core/helpers.php';
$dbFile = __DIR__ . '/../storage/dev.sqlite';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

// 1) new tables (idempotent)
$db->exec("CREATE TABLE IF NOT EXISTS maintenance_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(150) NOT NULL,
  asset_no VARCHAR(60) NULL UNIQUE,
  department VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS maintenance_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  record_no VARCHAR(30) NOT NULL UNIQUE,
  asset_id INT NULL,
  machine_name VARCHAR(150) NOT NULL,
  asset_no VARCHAR(60) NULL,
  department VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(120) NULL,
  problem TEXT NOT NULL,
  problem_date DATE NOT NULL,
  work_performed TEXT NULL,
  work_date DATE NULL,
  vendor VARCHAR(150) NULL,
  labour_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  parts_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  payment_type VARCHAR(10) NOT NULL DEFAULT 'cash',
  account_id INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  attachment VARCHAR(255) NULL,
  expense_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mr_asset FOREIGN KEY (asset_id) REFERENCES maintenance_assets(id) ON DELETE SET NULL,
  CONSTRAINT fk_mr_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL
)");
$db->exec("CREATE TABLE IF NOT EXISTS maintenance_parts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  maintenance_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_mp_rec FOREIGN KEY (maintenance_id) REFERENCES maintenance_records(id) ON DELETE CASCADE
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_mr_status ON maintenance_records(status)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_mr_department ON maintenance_records(department)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_mr_problem_date ON maintenance_records(problem_date)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_ma_department ON maintenance_assets(department)");
echo "tables ok\n";

// 2) new permissions + assign to current roles
$newPerms = [
    'maintenance.view' => 'maintenance',
    'maintenance.create' => 'maintenance',
    'maintenance.approve' => 'maintenance',
];
foreach ($newPerms as $code => $module) {
    $db->prepare('INSERT OR IGNORE INTO permissions (code, module, description) VALUES (?,?,?)')->execute([$code, $module, ucfirst(str_replace(['.', '_'], [' ', ' '], $code))]);
}
$grants = ['owner' => ['maintenance.view','maintenance.create','maintenance.approve'], 'admin' => ['maintenance.view','maintenance.create','maintenance.approve'], 'accountant' => ['maintenance.view'], 'manager' => ['maintenance.view','maintenance.create'], 'storekeeper' => ['maintenance.view','maintenance.create']];
foreach ($grants as $roleCode => $codes) {
    $roleId = $db->query("SELECT id FROM roles WHERE code = '" . $roleCode . "'")->fetchColumn();
    if (!$roleId) continue;
    foreach ($codes as $code) {
        $permId = $db->query("SELECT id FROM permissions WHERE code = '" . $code . "'")->fetchColumn();
        if (!$permId) continue;
        $db->prepare('INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)')->execute([(int)$roleId, (int)$permId]);
    }
}
echo "permissions ok\n";

// 3) expense category Factory Maintenance & Repairs -> GL 5440
$gl = (int)$db->query("SELECT id FROM chart_of_accounts WHERE code = '5440'")->fetchColumn();
$exists = $db->query("SELECT id FROM expense_categories WHERE name = 'Factory Maintenance & Repairs'")->fetchColumn();
if (!$exists && $gl) {
    $db->prepare('INSERT INTO expense_categories (name, gl_account_id, is_active) VALUES (?,?,1)')->execute(['Factory Maintenance & Repairs', $gl]);
    echo "category inserted\n";
} else { echo "category exists or GL missing\n"; }
echo "dev.sqlite migrated\n";
