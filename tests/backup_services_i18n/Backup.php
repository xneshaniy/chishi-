<?php
/** Shared SQL dump logic (used by bin/backup.php CLI and Settings → Backup). */
final class Backup {
    /** Dump all tables (INSERTs with data) to $file. Returns rows written. */
    public static function dump(string $file): int {
        $isSqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $tables = array_map('reset', db_all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
        } else {
            $tables = array_map('reset', db_all('SHOW TABLES'));
        }
        $rows = 0;
        $fh = fopen($file, 'w');
        fwrite($fh, "-- JUSTI NUMBER ONE ERP backup — " . date('Y-m-d H:i:s') . " Asia/Karachi\n");
        fwrite($fh, "-- Restore: create empty DB, import database/schema.mysql.sql, then this file.\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            fwrite($fh, "-- Table: $table\n");
            foreach (db_all("SELECT * FROM `$table`") as $r) {
                $cols = array_map(fn($c) => "`$c`", array_keys($r));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), array_values($r));
                fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
                $rows++;
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        chmod($file, 0640);
        return $rows;
    }
}
