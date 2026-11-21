<?php
/** Add other_income table to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS other_income (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  income_no VARCHAR(30) NOT NULL UNIQUE,
  category VARCHAR(60) NOT NULL DEFAULT 'other',
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  payer VARCHAR(150) NULL,
  description VARCHAR(255) NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
  account_id INT NOT NULL,
  attachment VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oi_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL
)");
echo "other_income: ", $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='other_income'")->fetchColumn(), "\n";
