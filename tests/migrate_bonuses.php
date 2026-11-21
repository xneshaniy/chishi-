<?php
/** Add employee_bonuses to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS employee_bonuses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
  account_id INT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eb_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_eb_emp ON employee_bonuses (employee_id, date)');
echo "employee_bonuses: ", ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'employee_bonuses'")->fetchColumn() ? 'ok' : 'MISSING'), "\n";
