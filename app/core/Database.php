<?php
/**
 * Database — PDO wrapper. Supports MySQL (production / Hostinger) and SQLite (tests).
 * Transaction depth counter so nested service calls share one transaction.
 */
final class Database {
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function connect(?array $cfg = null): PDO {
        if (self::$pdo) return self::$pdo;
        $c = $cfg ?? ($GLOBALS['CONFIG']['db'] ?? null);
        $driver = $c['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $dsn = $c['dsn'] ?? 'sqlite::memory:';
            self::$pdo = new PDO($dsn, null, null, self::opts());
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4');
            $opts = self::opts();
            // PHP 8.4+ moved the constant to Pdo\Mysql namespace; fall back for older PHP
            $initCmdKey = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
                ? \Pdo\Mysql::ATTR_INIT_COMMAND
                : PDO::MYSQL_ATTR_INIT_COMMAND;
            $opts[$initCmdKey] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        }
        return self::$pdo;
    }

    private static function opts(): array {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    /** Override for tests. */
    public static function setPdo(PDO $pdo): void { self::$pdo = $pdo; self::$txDepth = 0; }
    public static function reset(): void { self::$pdo = null; self::$txDepth = 0; }

    /** Run $fn inside one transaction (nesting-safe). Returns $fn's value. */
    public static function transaction(callable $fn) {
        $pdo = self::connect();
        if (self::$txDepth > 0) { self::$txDepth++; try { return $fn(); } finally { self::$txDepth--; } }
        self::$txDepth = 1;
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            self::$txDepth = 0;
            return $result;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            self::$txDepth = 0;
            throw $e;
        }
    }
}

function db(): PDO { return Database::connect(); }
function db_all(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function db_one(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r === false ? null : $r; }
function db_val(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function db_exec(string $sql, array $p = []): int { $s = db()->prepare($sql); $s->execute($p); return $s->rowCount(); }
function db_insert(string $table, array $data): int {
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $s = db()->prepare($sql); $s->execute(array_values($data));
    return (int)db()->lastInsertId();
}
