<?php
/** Add labour module tables to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_leaves (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days INT NOT NULL DEFAULT 1,
  leave_type VARCHAR(40) NOT NULL DEFAULT 'casual',
  is_paid INTEGER NOT NULL DEFAULT 1,
  reason VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_staff_leave FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_staff_leaves_emp ON staff_leaves (employee_id, start_date)');
$pdo->exec("CREATE TABLE IF NOT EXISTS salary_changes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
  old_rate DECIMAL(18,2) NULL,
  new_rate DECIMAL(18,2) NOT NULL,
  effective_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salary_change FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS employee_deductions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  approved_by INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_edd_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");
echo "tables: ", $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name IN ('staff_leaves','salary_changes','employee_deductions')")->fetchColumn(), "\n";
