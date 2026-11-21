<?php
// Script to run on live Hostinger MySQL database to add all missing columns/tables
$configPath = file_exists(__DIR__ . '/config/config.php') ? __DIR__ . '/config/config.php' : __DIR__ . '/../config/config.php';
$config = require $configPath;
$db = $config['db'];

try {
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}", $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to MySQL successfully!\n";
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

function addColumnIfNotExists($pdo, $table, $column, $def) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    if ($stmt->fetch()) {
        echo "Column `{$table}`.`{$column}` already exists.\n";
        return;
    }
    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}";
    $pdo->exec($sql);
    echo "Added column `{$table}`.`{$column}` successfully!\n";
}

// 1. payroll
addColumnIfNotExists($pdo, 'payroll', 'amount_paid', 'DECIMAL(18,2) NOT NULL DEFAULT 0');

// 2. employees
addColumnIfNotExists($pdo, 'employees', 'left_date', 'DATE DEFAULT NULL');
addColumnIfNotExists($pdo, 'employees', 'departure_reason', 'TEXT DEFAULT NULL');

// 3. salesmen
addColumnIfNotExists($pdo, 'salesmen', 'left_date', 'DATE DEFAULT NULL');
addColumnIfNotExists($pdo, 'salesmen', 'departure_reason', 'TEXT DEFAULT NULL');

// 4. personal_loans
addColumnIfNotExists($pdo, 'personal_loans', 'account_id', 'INT NULL');

// 5. personal_loan_repayments
addColumnIfNotExists($pdo, 'personal_loan_repayments', 'account_id', 'INT NULL');

// 6. users
addColumnIfNotExists($pdo, 'users', 'province_id', 'INT NULL');
addColumnIfNotExists($pdo, 'users', 'city_id', 'INT NULL');
addColumnIfNotExists($pdo, 'users', 'area_id', 'INT NULL');
addColumnIfNotExists($pdo, 'users', 'deactivated_at', 'DATETIME NULL');
addColumnIfNotExists($pdo, 'users', 'deactivated_by', 'INT NULL');
addColumnIfNotExists($pdo, 'users', 'temp_password', 'VARCHAR(100) DEFAULT NULL');

// 7. supplier_products
$pdo->exec("
CREATE TABLE IF NOT EXISTS supplier_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  product_id INT NOT NULL,
  default_rate DECIMAL(18,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sp (supplier_id, product_id)
) ENGINE=InnoDB;
");
echo "Verified table `supplier_products`!\n";

// 8. user_permissions
$pdo->exec("
CREATE TABLE IF NOT EXISTS user_permissions (
  user_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (user_id, permission_id)
) ENGINE=InnoDB;
");
echo "Verified table `user_permissions`!\n";

echo "ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n";
