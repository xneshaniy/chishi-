<?php
/** Migrate the dev SQLite staff_attendance / staff_attendance_audit tables to the generalized (employee|salesman) shape, preserving existing rows. */
$root = dirname(__DIR__);
$pdo = new PDO('sqlite:' . $root . '/storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = $pdo->query('PRAGMA table_info(staff_attendance)')->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'name');
echo "current staff_attendance columns: ", implode(', ', $names), "\n";
if (in_array('salesman_id', $names, true)) { echo "already migrated\n"; exit(0); }

$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->beginTransaction();
try {
    $pdo->exec("CREATE TABLE staff_attendance_mig (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      employee_id INT NULL,
      salesman_id INT NULL,
      date DATE NOT NULL,
      status VARCHAR(8) NOT NULL,
      marked_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      CONSTRAINT fk_staffatt_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
      CONSTRAINT fk_staffatt_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
      CONSTRAINT chk_sa_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
    )");
    $pdo->exec("INSERT INTO staff_attendance_mig (id, employee_id, salesman_id, date, status, marked_by, created_at, updated_at) SELECT id, employee_id, NULL, date, status, marked_by, created_at, updated_at FROM staff_attendance");
    $pdo->exec('DROP TABLE staff_attendance');
    $pdo->exec('ALTER TABLE staff_attendance_mig RENAME TO staff_attendance');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sa_emp_date ON staff_attendance (employee_id, date) WHERE salesman_id IS NULL');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sa_sm_date ON staff_attendance (salesman_id, date) WHERE employee_id IS NULL');

    $colsA = $pdo->query('PRAGMA table_info(staff_attendance_audit)')->fetchAll(PDO::FETCH_ASSOC);
    $namesA = array_column($colsA, 'name');
    echo "current staff_attendance_audit columns: ", implode(', ', $namesA), "\n";
    if (!in_array('salesman_id', $namesA, true)) {
        $pdo->exec("CREATE TABLE staff_attendance_audit_mig (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          employee_id INT NULL,
          salesman_id INT NULL,
          date DATE NOT NULL,
          old_status VARCHAR(8) NULL,
          new_status VARCHAR(8) NULL,
          changed_by INT NULL,
          note VARCHAR(255) NULL,
          changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_audit_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          CONSTRAINT fk_audit_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT chk_saaud_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
        )");
        $pdo->exec("INSERT INTO staff_attendance_audit_mig (id, employee_id, salesman_id, date, old_status, new_status, changed_by, note, changed_at) SELECT id, employee_id, NULL, date, old_status, new_status, changed_by, note, changed_at FROM staff_attendance_audit");
        $pdo->exec('DROP TABLE staff_attendance_audit');
        $pdo->exec('ALTER TABLE staff_attendance_audit_mig RENAME TO staff_attendance_audit');
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $pdo->exec('PRAGMA foreign_keys = ON');
    fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
$pdo->exec('PRAGMA foreign_keys = ON');
echo "staff_attendance rows: ", $pdo->query('SELECT COUNT(*) FROM staff_attendance')->fetchColumn(), "\n";
echo "staff_attendance_audit rows: ", $pdo->query('SELECT COUNT(*) FROM staff_attendance_audit')->fetchColumn(), "\n";
