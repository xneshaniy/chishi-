<?php
/**
 * JUSTI NUMBER ONE ERP — unified Salary + Attendance upgrade for EXISTING installations.
 * Generalises staff_attendance / staff_attendance_audit to support salesmen (keeps employee rows).
 * Safe to run multiple times; preserves all existing attendance/audit rows.
 *
 * Usage (on the server):  php install/upgrade_salary_attendance.php
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
    $pdo = new PDO(strpos($dsn, 'sqlite:') === 0 ? $dsn : 'sqlite:' . $dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cols = $pdo->query('PRAGMA table_info(staff_attendance)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (in_array('salesman_id', $names, true)) { echo "staff_attendance already generalized — nothing to do.\n"; }
    else {
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
            $cols2 = $pdo->query('PRAGMA table_info(staff_attendance_audit)')->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('salesman_id', array_column($cols2, 'name'), true)) {
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
        echo "staff_attendance / staff_attendance_audit generalized (rows preserved).\n";
    }
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $hasSales = false;
    foreach ($pdo->query('SHOW COLUMNS FROM staff_attendance') as $c) {
        if ($c['Field'] === 'salesman_id') { $hasSales = true; break; }
    }
    if ($hasSales) { echo "staff_attendance already generalized — nothing to do.\n"; exit(0); }
    // MySQL: rebuild preserving rows (SQLite-style flow doesn't work directly on InnoDB)
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE staff_attendance_mig (
          id INT AUTO_INCREMENT PRIMARY KEY,
          employee_id INT NULL,
          salesman_id INT NULL,
          date DATE NOT NULL,
          status VARCHAR(8) NOT NULL,
          marked_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NULL,
          UNIQUE KEY uq_sa_person_date (employee_id, salesman_id, date),
          CONSTRAINT fk_staffatt_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          CONSTRAINT fk_staffatt_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT chk_sa_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT INTO staff_attendance_mig (id, employee_id, salesman_id, date, status, marked_by, created_at, updated_at) SELECT id, employee_id, NULL, date, status, marked_by, created_at, updated_at FROM staff_attendance");
        $pdo->exec('DROP TABLE staff_attendance');
        $pdo->exec('RENAME TABLE staff_attendance_mig TO staff_attendance');
        $pdo->exec("CREATE TABLE staff_attendance_audit_mig (
          id INT AUTO_INCREMENT PRIMARY KEY,
          employee_id INT NULL,
          salesman_id INT NULL,
          date DATE NOT NULL,
          old_status VARCHAR(8) NULL,
          new_status VARCHAR(8) NULL,
          changed_by INT NULL,
          note VARCHAR(255) NULL,
          changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_staffatt_audit_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          CONSTRAINT fk_staffatt_audit_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT chk_saaud_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT INTO staff_attendance_audit_mig (id, employee_id, salesman_id, date, old_status, new_status, changed_by, note, changed_at) SELECT id, employee_id, NULL, date, old_status, new_status, changed_by, note, changed_at FROM staff_attendance_audit");
        $pdo->exec('DROP TABLE staff_attendance_audit');
        $pdo->exec('RENAME TABLE staff_attendance_audit_mig TO staff_attendance_audit');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
        exit(1);
    }
    echo "staff_attendance / staff_attendance_audit generalized (rows preserved).\n";
}
echo "Done.\n";
