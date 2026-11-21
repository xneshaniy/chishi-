<?php
/**
 * JUSTI NUMBER ONE ERP — Optional-Customer upgrade for existing installations.
 * Removes the mandatory Customer requirement where it is not actually needed:
 *   - sales_invoices.customer_id, sales_returns.customer_id, sales_orders.customer_id
 *     are relaxed to NULL (sales to walk-in / counter parties without a customer master).
 *   - salesman_collections / receipts gain contact_name (VARCHAR(120) NULL) —
 *     free-text payer/person for collections/receipts made without a registered customer.
 * Historical data is untouched. FK relations are preserved (children re-point by name).
 * Idempotent — safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_optional_customers.php
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

if ($driver === 'sqlite') {
    $dsn = (string)($db['dsn'] ?? '');
    if ($dsn === '') { fwrite(STDERR, "sqlite dsn missing in config\n"); exit(1); }
    $pdo = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('PRAGMA legacy_alter_table = ON');

    // sales_invoices
    $col = $pdo->query("PRAGMA table_info(sales_invoices)")->fetchAll(PDO::FETCH_ASSOC);
    $cust = array_filter($col, fn($c) => $c['name'] === 'customer_id');
    $custNullable = $cust && (int)reset($cust)['notnull'] === 0;
    if (!$custNullable) {
        $pdo->exec('ALTER TABLE sales_invoices RENAME TO sales_invoices__old');
        $pdo->exec("CREATE TABLE sales_invoices (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          invoice_no VARCHAR(30) NOT NULL UNIQUE,
          customer_id INT NULL,
          salesman_id INT NULL,
          warehouse_id INT NOT NULL,
          date DATE NOT NULL,
          due_date DATE NULL,
          payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
          cash_account_id INT NULL,
          subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
          discount DECIMAL(18,2) NOT NULL DEFAULT 0,
          tax DECIMAL(18,2) NOT NULL DEFAULT 0,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          cost_total DECIMAL(18,2) NOT NULL DEFAULT 0,
          authorized_by INT NULL,
          bonus_scheme VARCHAR(100) NULL,
          status VARCHAR(10) NOT NULL DEFAULT 'posted',
          journal_entry_id INT NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_si_c FOREIGN KEY (customer_id) REFERENCES customers(id),
          CONSTRAINT fk_si_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL,
          CONSTRAINT fk_si_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        )");
        $cols = ['id','invoice_no','customer_id','salesman_id','warehouse_id','date','due_date','payment_type','cash_account_id','subtotal','discount','tax','total','cost_total','authorized_by','bonus_scheme','status','journal_entry_id','created_by','created_at'];
        $pdo->exec('INSERT INTO sales_invoices (' . implode(',', $cols) . ') SELECT ' . implode(',', $cols) . ' FROM sales_invoices__old');
        $pdo->exec('DROP TABLE sales_invoices__old');
        echo "sales_invoices.customer_id relaxed to NULL\n";
    } else {
        echo "sales_invoices.customer_id already nullable\n";
    }

    // sales_returns
    $col = $pdo->query("PRAGMA table_info(sales_returns)")->fetchAll(PDO::FETCH_ASSOC);
    $cust = array_filter($col, fn($c) => $c['name'] === 'customer_id');
    $custNullable = $cust && (int)reset($cust)['notnull'] === 0;
    if (!$custNullable) {
        $pdo->exec('ALTER TABLE sales_returns RENAME TO sales_returns__old');
        $pdo->exec("CREATE TABLE sales_returns (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          return_no VARCHAR(30) NOT NULL UNIQUE,
          sales_invoice_id INT NOT NULL,
          customer_id INT NULL,
          date DATE NOT NULL,
          reason VARCHAR(255) NULL,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          status VARCHAR(10) NOT NULL DEFAULT 'posted',
          journal_entry_id INT NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_sr_si FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id),
          CONSTRAINT fk_sr_c FOREIGN KEY (customer_id) REFERENCES customers(id)
        )");
        $cols = ['id','return_no','sales_invoice_id','customer_id','date','reason','total','status','journal_entry_id','created_by','created_at'];
        $pdo->exec('INSERT INTO sales_returns (' . implode(',', $cols) . ') SELECT ' . implode(',', $cols) . ' FROM sales_returns__old');
        $pdo->exec('DROP TABLE sales_returns__old');
        echo "sales_returns.customer_id relaxed to NULL\n";
    } else {
        echo "sales_returns.customer_id already nullable\n";
    }

    // sales_orders
    $col = $pdo->query("PRAGMA table_info(sales_orders)")->fetchAll(PDO::FETCH_ASSOC);
    $cust = array_filter($col, fn($c) => $c['name'] === 'customer_id');
    $custNullable = $cust && (int)reset($cust)['notnull'] === 0;
    if (!$custNullable) {
        $pdo->exec('ALTER TABLE sales_orders RENAME TO sales_orders__old');
        $pdo->exec("CREATE TABLE sales_orders (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          so_no VARCHAR(30) NOT NULL UNIQUE,
          customer_id INT NULL,
          salesman_id INT NULL,
          order_date DATE NOT NULL,
          delivery_date DATE NULL,
          status VARCHAR(12) NOT NULL DEFAULT 'draft',
          subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          notes VARCHAR(255) NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_so_c FOREIGN KEY (customer_id) REFERENCES customers(id),
          CONSTRAINT fk_so_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL
        )");
        $cols = ['id','so_no','customer_id','salesman_id','order_date','delivery_date','status','subtotal','total','notes','created_by','created_at'];
        $pdo->exec('INSERT INTO sales_orders (' . implode(',', $cols) . ') SELECT ' . implode(',', $cols) . ' FROM sales_orders__old');
        $pdo->exec('DROP TABLE sales_orders__old');
        echo "sales_orders.customer_id relaxed to NULL\n";
    } else {
        echo "sales_orders.customer_id already nullable\n";
    }

    // contact_name columns
    foreach (['salesman_collections', 'receipts'] as $t) {
        $found = false;
        foreach ($pdo->query("PRAGMA table_info($t)") as $c) { if ($c['name'] === 'contact_name') { $found = true; break; } }
        if ($found) { echo "$t.contact_name already present\n"; continue; }
        $pdo->exec("ALTER TABLE $t ADD COLUMN contact_name VARCHAR(120) NULL");
        echo "$t.contact_name added\n";
    }

    $pdo->exec('PRAGMA legacy_alter_table = OFF');
    $pdo->exec('PRAGMA foreign_keys = ON');

    // integrity check
    $bad = (int)$pdo->query("PRAGMA foreign_key_check")->fetchColumn();
    $bad = 0; foreach ($pdo->query("PRAGMA foreign_key_check") as $row) { $bad++; }
    echo $bad === 0 ? "foreign_key_check OK\n" : "WARNING foreign_key_check: $bad problem(s)\n";
    echo "OK\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$nullable = function (string $table, string $col) use ($pdo): bool {
    $n = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $n->execute([$table, $col]);
    return strtoupper((string)$n->fetchColumn()) === 'YES';
};
$hasCol = function (string $table, string $col) use ($pdo): bool {
    $n = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $n->execute([$table, $col]);
    return (int)$n->fetchColumn() > 0;
};
if (!$nullable('sales_invoices', 'customer_id')) { $pdo->exec('ALTER TABLE sales_invoices MODIFY customer_id INT NULL'); echo "sales_invoices.customer_id relaxed to NULL\n"; } else { echo "sales_invoices.customer_id already nullable\n"; }
if (!$nullable('sales_returns', 'customer_id')) { $pdo->exec('ALTER TABLE sales_returns MODIFY customer_id INT NULL'); echo "sales_returns.customer_id relaxed to NULL\n"; } else { echo "sales_returns.customer_id already nullable\n"; }
if (!$nullable('sales_orders', 'customer_id')) { $pdo->exec('ALTER TABLE sales_orders MODIFY customer_id INT NULL'); echo "sales_orders.customer_id relaxed to NULL\n"; } else { echo "sales_orders.customer_id already nullable\n"; }
foreach (['salesman_collections', 'receipts'] as $t) {
    if ($hasCol($t, 'contact_name')) { echo "$t.contact_name already present\n"; continue; }
    $pdo->exec("ALTER TABLE $t ADD COLUMN contact_name VARCHAR(120) NULL AFTER customer_id");
    echo "$t.contact_name added\n";
}
echo "OK\n";
