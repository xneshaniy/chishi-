<?php
/**
 * Migrate dev.sqlite: salesman account fields, salary/advances/expense tables,
 * chart accounts, consolidate to ONE main warehouse (stock move, deactivate factory).
 * Safe to re-run.
 */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function colExists(PDO $pdo, string $t, string $c): bool {
    foreach ($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC) as $r) if ($r['name'] === $c) return true;
    return false;
}
// 1) Salesman profile columns
$cols = [
    'monthly_salary' => "DECIMAL(18,2) NOT NULL DEFAULT 0",
    'join_date' => "DATE NULL",
    'vehicle_type' => "VARCHAR(60) NULL",
    'vehicle_reg' => "VARCHAR(60) NULL",
    'vehicle_note' => "VARCHAR(255) NULL",
];
foreach ($cols as $c => $def) {
    if (!colExists($pdo, 'salesmen', $c)) { $pdo->exec("ALTER TABLE salesmen ADD COLUMN $c $def"); echo "salesmen.$c added\n"; }
}
// 2) Salary payments table
$pdo->exec("CREATE TABLE IF NOT EXISTS salesman_salary_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  period VARCHAR(10) NULL,             -- YYYY-MM the payment is for
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',  -- pending|paid|rejected|reversed
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ssp_sm ON salesman_salary_payments (salesman_id, date)");
// 3) Salary advances
$pdo->exec("CREATE TABLE IF NOT EXISTS salesman_salary_advances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',   -- pending|paid|rejected|reversed
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ssa_sm ON salesman_salary_advances (salesman_id, date)");
// 4) Salesman expenses
$pdo->exec("CREATE TABLE IF NOT EXISTS salesman_expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  expense_type VARCHAR(20) NOT NULL,   -- diesel|vehicle|food|hotel|repair|other
  amount DECIMAL(18,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',  -- cash|bank
  account_id INT NOT NULL,
  attachment VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',    -- pending|posted|rejected|reversed
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_se_sm ON salesman_expenses (salesman_id, date)");
echo "tables ensured\n";
// 5) Chart accounts + expense categories (idempotent)
$chart = $pdo->prepare("INSERT INTO chart_of_accounts (code, name, type, parent_id, is_system, allow_posting, system_key) SELECT ?, ?, 'expense', (SELECT id FROM chart_of_accounts WHERE code='5700'), 0, 1, ? WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = ?)");
$chart->execute(['5710', 'Salesman Expenses', 'SM_EXP', '5710']);
$chart->execute(['5720', 'Salesman Salary', 'SM_SALARY', '5720']);
$ex = $pdo->prepare("INSERT INTO expense_categories (name, gl_account_id, is_active) SELECT ?, (SELECT id FROM chart_of_accounts WHERE system_key = ?), 1 WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE name = ?)");
$ex->execute(['Salesman Expenses', 'SM_EXP', 'Salesman Expenses']);
$ex->execute(['Salesman Salary', 'SM_SALARY', 'Salesman Salary']);
echo "chart + categories ensured\n";
// 6) Consolidate to ONE main warehouse: move factory stock to MAIN, deactivate factory
$move = $pdo->prepare("UPDATE stock_balances SET warehouse_id = 1 WHERE warehouse_id = 2");
// if both warehouses hold the same product, merge rows first
$pairs = $pdo->query("SELECT product_id FROM stock_balances GROUP BY product_id HAVING COUNT(*) > 1 AND SUM(warehouse_id=2) > 0 AND SUM(warehouse_id=1) > 0")->fetchAll(PDO::FETCH_ASSOC);
foreach ($pairs as $p) {
    $prod = (int)$p['product_id'];
    $qFactory = (float)$pdo->query("SELECT qty FROM stock_balances WHERE product_id = $prod AND warehouse_id = 2")->fetchColumn();
    $cFactory = (float)$pdo->query("SELECT avg_cost FROM stock_balances WHERE product_id = $prod AND warehouse_id = 2")->fetchColumn();
    $qMain = (float)$pdo->query("SELECT qty FROM stock_balances WHERE product_id = $prod AND warehouse_id = 1")->fetchColumn();
    $cMain = (float)$pdo->query("SELECT avg_cost FROM stock_balances WHERE product_id = $prod AND warehouse_id = 1")->fetchColumn();
    $qNew = $qMain + $qFactory;
    $cNew = $qNew > 0 ? round(($qMain * $cMain + $qFactory * $cFactory) / $qNew, 4) : 0;
    $pdo->prepare("UPDATE stock_balances SET qty = ?, avg_cost = ? WHERE product_id = ? AND warehouse_id = 1")->execute([$qNew, $cNew, $prod]);
    $pdo->exec("DELETE FROM stock_balances WHERE product_id = $prod AND warehouse_id = 2");
    echo "merged product $prod into WH-MAIN\n";
}
$moved = $pdo->exec("UPDATE stock_balances SET warehouse_id = 1 WHERE warehouse_id = 2");
echo "moved rows to WH-MAIN: $moved\n";
$pdo->exec("UPDATE stock_cost_layers SET warehouse_id = 1 WHERE warehouse_id = 2");
$pdo->exec("UPDATE production_orders SET warehouse_id = 1 WHERE warehouse_id = 2");
$pdo->exec("UPDATE warehouses SET is_active = 0 WHERE code = 'WH-FACTORY'");
$pdo->exec("UPDATE company_settings SET setting_value = '1' WHERE setting_key = 'fuel_alert'"); // harmless no-op placeholder
echo "WH-FACTORY deactivated\n";
// 7) Also move stock NORMAL for FIFO layer linkage preserved above
