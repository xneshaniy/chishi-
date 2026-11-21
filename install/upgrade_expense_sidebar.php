<?php
/**
 * JUSTI NUMBER ONE ERP — Sidebar reorganization + Expense/Gifts upgrade for existing installations.
 * Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_expense_sidebar.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the installer first.\n"); exit(1); }
$config = require $configPath;
$db = $config['db'] ?? [];
$driver = $db['driver'] ?? 'mysql';

function alterAddColumn(PDO $pdo, string $driver, string $table, string $columnDef, string $sqliteDef): void {
    if ($driver === 'sqlite') {
        $cols = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!$cols) { echo "SKIP (missing table): $table\n"; return; }
        $colName = preg_replace('/\s+.*/', '', trim($columnDef));
        if (in_array($colName, $cols, true)) { echo "OK (column exists): $table.$colName\n"; return; }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $sqliteDef");
        echo "ADDED: $table.$colName\n";
        return;
    }
    $cols = array_column($pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $colName = preg_replace('/\s+.*/', '', trim($columnDef));
    if (!in_array($colName, $cols, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $columnDef");
        echo "ADDED: $table.$colName\n";
    } else {
        echo "OK (column exists): $table.$colName\n";
    }
}

$steps = function (PDO $pdo, string $driver) {
    alterAddColumn($pdo, $driver, 'maintenance_assets', "category VARCHAR(80) NOT NULL DEFAULT ''", "category VARCHAR(80) NOT NULL DEFAULT ''");
    alterAddColumn($pdo, $driver, 'maintenance_assets', "purchase_date DATE NULL", "purchase_date DATE NULL");
    alterAddColumn($pdo, $driver, 'maintenance_assets', "description TEXT NULL", "description TEXT NULL");
    alterAddColumn($pdo, $driver, 'maintenance_assets', "photo_path VARCHAR(255) NULL", "photo_path VARCHAR(255) NULL");
    alterAddColumn($pdo, $driver, 'maintenance_assets', "status VARCHAR(12) NOT NULL DEFAULT 'active'", "status VARCHAR(12) NOT NULL DEFAULT 'active'");
    alterAddColumn($pdo, $driver, 'vehicles', "name VARCHAR(150) NULL", "name VARCHAR(150) NULL");
    alterAddColumn($pdo, $driver, 'vehicles', "vehicle_type VARCHAR(30) NULL", "vehicle_type VARCHAR(30) NULL");
    alterAddColumn($pdo, $driver, 'vehicles', "purchase_date DATE NULL", "purchase_date DATE NULL");
    alterAddColumn($pdo, $driver, 'vehicles', "photo_path VARCHAR(255) NULL", "photo_path VARCHAR(255) NULL");
    alterAddColumn($pdo, $driver, 'vehicles', "status VARCHAR(12) NOT NULL DEFAULT 'active'", "status VARCHAR(12) NOT NULL DEFAULT 'active'");
    alterAddColumn($pdo, $driver, 'vehicles', "notes VARCHAR(255) NULL", "notes VARCHAR(255) NULL");
    alterAddColumn($pdo, $driver, 'vehicle_fuel_logs', "created_by INT NULL", "created_by INT NULL");
    alterAddColumn($pdo, $driver, 'vehicle_maintenance', "type VARCHAR(20) NOT NULL DEFAULT 'maintenance'", "type VARCHAR(20) NOT NULL DEFAULT 'maintenance'");
    alterAddColumn($pdo, $driver, 'vehicle_maintenance', "attachment VARCHAR(255) NULL", "attachment VARCHAR(255) NULL");
    alterAddColumn($pdo, $driver, 'vehicle_maintenance', "created_by INT NULL", "created_by INT NULL");
    alterAddColumn($pdo, $driver, 'expenses', "person_name VARCHAR(120) NULL", "person_name VARCHAR(120) NULL");
    alterAddColumn($pdo, $driver, 'expenses', "qty DECIMAL(18,3) NULL", "qty DECIMAL(18,3) NULL");
    alterAddColumn($pdo, $driver, 'expenses', "notes TEXT NULL", "notes TEXT NULL");
};

$giftSql = function (PDO $pdo, string $driver) {
    if ($driver === 'sqlite') {
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='gifts'")->fetchColumn();
    } else {
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'gifts'")->fetchColumn();
    }
    if ($exists) { echo "OK (already present): gifts\n"; return; }
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE gifts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            gift_no VARCHAR(30) NOT NULL UNIQUE,
            gift_date DATE NOT NULL,
            recipient_name VARCHAR(150) NOT NULL,
            recipient_type VARCHAR(30) NOT NULL,
            gift_type VARCHAR(10) NOT NULL,
            amount DECIMAL(18,2) NULL,
            product_id INT NULL,
            qty DECIMAL(18,3) NULL,
            qty_unit VARCHAR(20) NULL,
            qty_pieces DECIMAL(18,3) NULL,
            reason VARCHAR(255) NULL,
            description TEXT NULL,
            notes TEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            expense_id INT NULL,
            recorded_by INT NULL,
            approved_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_gift_prod FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_gift_exp FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL
        )");
    } else {
        $pdo->exec("CREATE TABLE gifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gift_no VARCHAR(30) NOT NULL UNIQUE,
            gift_date DATE NOT NULL,
            recipient_name VARCHAR(150) NOT NULL,
            recipient_type VARCHAR(30) NOT NULL,
            gift_type VARCHAR(10) NOT NULL,
            amount DECIMAL(18,2) NULL,
            product_id INT NULL,
            qty DECIMAL(18,3) NULL,
            qty_unit VARCHAR(20) NULL,
            qty_pieces DECIMAL(18,3) NULL,
            reason VARCHAR(255) NULL,
            description TEXT NULL,
            notes TEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            expense_id INT NULL,
            recorded_by INT NULL,
            approved_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_gift_prod FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_gift_exp FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
    }
    echo "ADDED gifts\n";
};

$giftSeed = function (PDO $pdo) {
    // GL account 5780 "Gifts & Donations" (expense type) + Gifts category — never override existing
    $has5780 = db_val("SELECT COUNT(*) FROM chart_of_accounts WHERE code = '5780'");
    if (!$has5780) {
        db_insert('chart_of_accounts', ['code' => '5780', 'name' => 'Gifts & Donations', 'type' => 'expense', 'is_system' => 0, 'allow_posting' => 1, 'system_key' => null, 'is_active' => 1]);
        echo "seeded GL 5780 (Gifts & Donations)\n";
    } else {
        echo "OK: GL 5780 exists\n";
    }
    $glId = (int)db_val("SELECT id FROM chart_of_accounts WHERE code = '5780'");
    $cat = db_val("SELECT id FROM expense_categories WHERE name = 'Gifts'");
    if (!$cat) {
        db_insert('expense_categories', ['name' => 'Gifts', 'gl_account_id' => $glId, 'is_active' => 1]);
        echo "seeded expense category 'Gifts'\n";
    } else {
        echo "OK: 'Gifts' category exists\n";
    }
};

$permSeed = function (PDO $pdo) {
    // Gift permissions — available to Owner/Admin by default; may be granted to Manager by Admin
    $maxId = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM permissions')->fetchColumn();
    foreach (['gifts.view' => 'gifts', 'gifts.create' => 'gifts', 'gifts.manage' => 'gifts'] as $code => $module) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code = '" . addslashes($code) . "'")->fetchColumn()) { echo "OK: perm $code exists\n"; continue; }
        $maxId++;
        $pdo->exec("INSERT INTO permissions (id, code, module) VALUES ($maxId, " . $pdo->quote($code) . ", " . $pdo->quote($module) . ")");
        echo "inserted perm $code (id=$maxId)\n";
    }
    foreach (['owner' => 'gifts.view,gifts.create,gifts.manage', 'admin' => 'gifts.view,gifts.create,gifts.manage'] as $role => $csv) {
        $rid = (int)$pdo->query("SELECT id FROM roles WHERE code = '$role'")->fetchColumn();
        foreach (explode(',', $csv) as $code) {
            $pid = (int)$pdo->query("SELECT id FROM permissions WHERE code = '$code'")->fetchColumn();
            if (!($pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = $rid AND permission_id = $pid")->fetchColumn())) {
                $pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ($rid, $pid)");
                echo "granted $code to $role\n";
            }
        }
    }
};

if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $steps($pdo, 'sqlite');
    $giftSql($pdo, 'sqlite');
    require_once $root . '/app/core/Database.php';
    $pdoMigrate = $pdo;
    // reuse app helpers via Database lazily — seed perms using raw SQL against same pdo
    $maxId = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM permissions')->fetchColumn();
    foreach (['gifts.view' => 'gifts', 'gifts.create' => 'gifts', 'gifts.manage' => 'gifts'] as $code => $module) {
        if ((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code = '$code'")->fetchColumn()) { echo "OK: perm $code exists\n"; continue; }
        $maxId++;
        $pdo->exec("INSERT INTO permissions (id, code, module) VALUES ($maxId, " . $pdo->quote($code) . ", " . $pdo->quote($module) . ")");
        echo "inserted perm $code\n";
    }
    foreach (['owner' => ['gifts.view','gifts.create','gifts.manage'], 'admin' => ['gifts.view','gifts.create','gifts.manage']] as $role => $codes) {
        $rid = (int)$pdo->query("SELECT id FROM roles WHERE code = '$role'")->fetchColumn();
        foreach ($codes as $code) {
            $pid = (int)$pdo->query("SELECT id FROM permissions WHERE code = '$code'")->fetchColumn();
            if (!($pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = $rid AND permission_id = $pid")->fetchColumn())) {
                $pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ($rid, $pid)");
                echo "granted $code to $role\n";
            }
        }
    }
    // GL + category seed reuse helper functions using raw SQL
    $has5780 = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE code = '5780'")->fetchColumn();
    if (!$has5780) {
        $pdo->exec("INSERT INTO chart_of_accounts (code, name, type, is_system, allow_posting, system_key, is_active) VALUES ('5780','Gifts & Donations','expense',0,1,NULL,1)");
        echo "seeded GL 5780\n";
    }
    $glId = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE code='5780'")->fetchColumn();
    $cat = (int)$pdo->query("SELECT COUNT(*) FROM expense_categories WHERE name='Gifts'")->fetchColumn();
    if (!$cat) {
        $pdo->exec("INSERT INTO expense_categories (name, gl_account_id, is_active) VALUES ('Gifts', $glId, 1)");
        echo "seeded Gifts category\n";
    }
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$steps($pdo, 'mysql');
$giftSql($pdo, 'mysql');
$maxId = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM permissions')->fetchColumn();
foreach (['gifts.view' => 'gifts', 'gifts.create' => 'gifts', 'gifts.manage' => 'gifts'] as $code => $module) {
    if ((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code = '" . addslashes($code) . "'")->fetchColumn()) { echo "OK: perm $code exists\n"; continue; }
    $maxId++;
    $pdo->exec("INSERT INTO permissions (id, code, module) VALUES ($maxId, " . $pdo->quote($code) . ", " . $pdo->quote($module) . ")");
    echo "inserted perm $code\n";
}
foreach (['owner' => ['gifts.view','gifts.create','gifts.manage'], 'admin' => ['gifts.view','gifts.create','gifts.manage']] as $role => $codes) {
    $rid = (int)$pdo->query("SELECT id FROM roles WHERE code = '$role'")->fetchColumn();
    foreach ($codes as $code) {
        $pid = (int)$pdo->query("SELECT id FROM permissions WHERE code = '$code'")->fetchColumn();
        if (!($pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = $rid AND permission_id = $pid")->fetchColumn())) {
            $pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ($rid, $pid)");
            echo "granted $code to $role\n";
        }
    }
}
$has5780 = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE code = '5780'")->fetchColumn();
if (!$has5780) {
    $pdo->exec("INSERT INTO chart_of_accounts (code, name, type, is_system, allow_posting, system_key, is_active) VALUES ('5780','Gifts & Donations','expense',0,1,NULL,1)");
    echo "seeded GL 5780\n";
}
$glId = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE code='5780'")->fetchColumn();
$cat = (int)$pdo->query("SELECT COUNT(*) FROM expense_categories WHERE name='Gifts'")->fetchColumn();
if (!$cat) {
    $pdo->exec("INSERT INTO expense_categories (name, gl_account_id, is_active) VALUES ('Gifts', $glId, 1)");
    echo "seeded Gifts category\n";
}
