<?php
/** Add salesman_payments to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS salesman_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  receipt_no VARCHAR(30) NULL UNIQUE,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  total_amount DECIMAL(18,2) NOT NULL,
  cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_account_id INT NULL,
  bank_account_id INT NULL,
  bank_reference VARCHAR(60) NULL,
  payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
  notes VARCHAR(255) NULL,
  received_by INT NULL,
  journal_entry_id INT NULL,
  idem_key VARCHAR(80) NULL UNIQUE,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_smp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_smp_cash FOREIGN KEY (cash_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_bank FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_user FOREIGN KEY (received_by) REFERENCES users(id)
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_smp_sm ON salesman_payments (salesman_id, date)');
echo "salesman_payments: ", ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'salesman_payments'")->fetchColumn() ? 'ok' : 'MISSING'), "\n";
