<?php
/**
 * Archive / restore / safely-delete master-data records.
 *  - "Delete" is allowed only when NO posted rows reference the record (through any FK).
 *  - "Archive" is always allowed — row is hidden from normal lists, retrievable later (and restored with full history).
 *  - Archive / restore / delete all audit with who + reason.
 *  - All endpoints are owner/admin-only (Admin filter area gate plus is_owner_or_admin() double-check).
 */
final class Archive {
    /** Tables the admin UI may archive / restore / safely delete. Kept to master data. */
    public static function allTables(): array {
        return [
            'products' => 'Products', 'suppliers' => 'Suppliers', 'customers' => 'Customers',
            'salesmen' => 'Salesmen', 'employees' => 'Employees', 'warehouses' => 'Warehouses',
            'branches' => 'Branches', 'expense_categories' => 'Expense Categories',
            'units' => 'Units', 'cash_bank_accounts' => 'Cash / Bank Accounts',
            'product_categories' => 'Product Categories', 'maintenance_assets' => 'Maintenance Assets',
            'vehicles' => 'Vehicles',
        ];
    }
    /** Guard — throw if table not allowed for archival. */
    public static function checkTable(string $table): void {
        if (!array_key_exists($table, self::allTables())) {
            throw new RuntimeException(__('Table not available for archival.'));
        }
    }

    /** List [childTable, childCol, onDelete] rows where someone references the given $table with $id. */
    public static function incomingRefs(string $table, int $id): array {
        self::checkTable($table);
        $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $refs = [];
        if ($driver === 'sqlite') {
            $tables = $pdo = null; // unused
            $schemaTables = array_column(db_all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name');
            foreach ($schemaTables as $child) {
                $fks = db_all("PRAGMA foreign_key_list('" . addslashes($child) . "')");
                foreach ($fks as $fk) {
                    if (($fk['table'] ?? '') === $table && isset($fk['from'], $fk['on_delete'])) {
                        $n = (int)db_val("SELECT COUNT(*) FROM " . self::q($child) . " WHERE " . self::q((string)$fk['from']) . " = ?", [$id]);
                        if ($n > 0) $refs[] = ['table' => $child, 'col' => $fk['from'], 'count' => $n, 'on_delete' => $fk['on_delete']];
                    }
                }
            }
        } else {
            $children = db_all("SELECT TABLE_NAME, COLUMN_NAME, DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.REFERENCED_TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = ?", [$table]);
            foreach ($children as $c) {
                $n = (int)db_val("SELECT COUNT(*) FROM " . self::q($c['TABLE_NAME']) . " WHERE " . self::q($c['COLUMN_NAME']) . " = ?", [$id]);
                if ($n > 0) $refs[] = ['table' => $c['TABLE_NAME'], 'col' => $c['COLUMN_NAME'], 'count' => $n, 'on_delete' => $c['DELETE_RULE']];
            }
        }
        return $refs;
    }

    public static function hasIncomingRefs(string $table, int $id): bool {
        return self::incomingRefs($table, $id) !== [];
    }

    /** Set is_archived = 1 (with is_active auto-flip for consistency with non-recoverable views). */
    public static function archive(string $table, int $id, int $adminId, string $reason): void {
        self::checkTable($table);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException(__('A reason is required to archive a record.'));
        $row = db_one('SELECT id FROM ' . self::q($table) . ' WHERE id = ?', [$id]);
        if (!$row) throw new RuntimeException(__('Record not found.'));
        db_exec('UPDATE ' . self::q($table) . ' SET is_archived = 1, is_active = 0 WHERE id = ?', [$id]);
        audit('archive', $table, $id, 'Archived by user ' . $adminId . ' — ' . $reason);
    }

    public static function restore(string $table, int $id, int $adminId, string $reason): void {
        self::checkTable($table);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException(__('A reason is required to restore a record.'));
        $row = db_one('SELECT id FROM ' . self::q($table) . ' WHERE id = ?', [$id]);
        if (!$row) throw new RuntimeException(__('Record not found.'));
        db_exec('UPDATE ' . self::q($table) . ' SET is_archived = 0, is_active = 1 WHERE id = ?', [$id]);
        audit('restore', $table, $id, 'Restored by user ' . $adminId . ' — ' . $reason);
    }

    /** Safe delete — only if zero incoming FK references. Otherwise throw with a reason summary. */
    public static function delete(string $table, int $id, int $adminId, string $reason, string $mode = 'hard'): void {
        self::checkTable($table);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException(__('A reason is required to delete a record.'));
        $row = db_one('SELECT id FROM ' . self::q($table) . ' WHERE id = ?', [$id]);
        if (!$row) throw new RuntimeException(__('Record not found.'));
        $refs = self::incomingRefs($table, $id);
        if ($refs) {
            $detail = implode(', ', array_map(fn($r) => $r['table'] . ' ×' . $r['count'], $refs));
            throw new RuntimeException(__('Cannot delete: linked by ') . $detail . __(' — archive it instead.'));
        }
        db_exec('DELETE FROM ' . self::q($table) . ' WHERE id = ?', [$id]);
        audit('delete', $table, null, 'Deleted ' . $table . ' #' . $id . ' by user ' . $adminId . ' — ' . $reason);
    }

    /** Wrap table/col names in backticks (MySQL/SQLite compatible explicit quoting). */
    private static function q(string $name): string {
        if (!preg_match('/^\w+$/', $name)) throw new RuntimeException('Invalid identifier.');
        return '`' . $name . '`';
    }

    /** List of one summary group for the archive admin page. */
    public static function summary(): array {
        $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $out = [];
        foreach (self::allTables() as $key => $label) {
            try {
                $active = (int)db_val('SELECT COUNT(*) FROM ' . self::q($key) . ' WHERE COALESCE(is_archived, 0) = 0');
                $archived = (int)db_val('SELECT COUNT(*) FROM ' . self::q($key) . ' WHERE COALESCE(is_archived, 0) = 1');
                $out[] = ['key' => $key, 'label' => $label, 'active' => $active, 'archived' => $archived];
            } catch (Throwable $e) {
                $out[] = ['key' => $key, 'label' => $label, 'active' => 0, 'archived' => 0, 'error' => $e->getMessage()];
            }
        }
        return $out;
    }

    public static function archivedRows(string $table): array {
        self::checkTable($table);
        return db_all('SELECT * FROM ' . self::q($table) . ' WHERE COALESCE(is_archived, 0) = 1 ORDER BY id DESC');
    }
}
