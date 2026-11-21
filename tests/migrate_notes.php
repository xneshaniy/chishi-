<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function colExists(PDO $pdo, string $t, string $c): bool {
    foreach ($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC) as $r) if ($r['name'] === $c) return true;
    return false;
}
if (!colExists($pdo, 'production_orders', 'notes')) {
    $pdo->exec("ALTER TABLE production_orders ADD COLUMN notes VARCHAR(255) NULL");
    echo "production_orders.notes added\n";
} else echo "exists\n";
