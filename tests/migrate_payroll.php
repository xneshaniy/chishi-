<?php
/** Migrate dev.sqlite to the payroll-partial-payment schema. Safe to re-run. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colExists(PDO $pdo, string $t, string $c): bool {
    foreach ($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['name'] === $c) return true;
    }
    return false;
}

if (!colExists($pdo, 'employees', 'payment_method')) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN payment_method VARCHAR(10) NOT NULL DEFAULT 'cash'");
    echo "employees.payment_method added\n";
} else { echo "employees.payment_method exists\n"; }

if (!colExists($pdo, 'payroll', 'amount_paid')) {
    $pdo->exec("ALTER TABLE payroll ADD COLUMN amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0");
    echo "payroll.amount_paid added\n";
} else { echo "payroll.amount_paid exists\n"; }

$pdo->exec("CREATE TABLE IF NOT EXISTS payroll_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  payroll_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pp_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_pp_payroll ON payroll_payments (payroll_id)');
echo "payroll_payments ensured\n";

$n = $pdo->exec("UPDATE payroll SET amount_paid = net_pay WHERE status = 'paid' AND amount_paid = 0");
echo "payroll rows backfilled with amount_paid=net_pay: $n\n";
