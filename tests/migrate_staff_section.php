<?php
/** One-shot development migration: employee section columns + new tables (non-destructive). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');

$cols = array_column($pdo->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC), 'name');
$add = [
    'photo_path' => 'TEXT NULL',
    'cnic_front_path' => 'TEXT NULL',
    'cnic_back_path' => 'TEXT NULL',
    'address' => 'TEXT NULL',
];
foreach ($add as $name => $def) {
    if (!in_array($name, $cols, true)) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN $name $def");
        echo "added column employees.$name\n";
    } else echo "column employees.$name already exists\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS employee_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  doc_type VARCHAR(60) NOT NULL DEFAULT 'other',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_empdoc_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(8) NOT NULL,
  marked_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_staffatt_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT uq_staffatt UNIQUE (employee_id, date)
)");
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_staffatt_empdate ON staff_attendance (employee_id, date)');

$pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance_audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  old_status VARCHAR(8) NULL,
  new_status VARCHAR(8) NULL,
  changed_by INT NULL,
  note VARCHAR(255) NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

echo "employee tables: ", $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name IN ('employee_documents','staff_attendance','staff_attendance_audit')")->fetchColumn(), "\n";
echo "done\n";
