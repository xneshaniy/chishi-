<?php
$p = 'database/schema.mysql.sql';
$s = file_get_contents($p);
$targets = [
    'maintenance_assets' => [
        "  category VARCHAR(80) NOT NULL DEFAULT '',",
        "  purchase_date DATE NULL,",
        "  description TEXT NULL,",
        "  photo_path VARCHAR(255) NULL,",
        "  status VARCHAR(12) NOT NULL DEFAULT 'active',",
    ],
    'vehicles' => [
        "  name VARCHAR(150) NULL,",
        "  vehicle_type VARCHAR(30) NULL,",
        "  purchase_date DATE NULL,",
        "  photo_path VARCHAR(255) NULL,",
        "  status VARCHAR(12) NOT NULL DEFAULT 'active',",
        "  notes VARCHAR(255) NULL,",
    ],
    'vehicle_fuel_logs' => [
        "  created_by INT NULL,",
    ],
    'vehicle_maintenance' => [
        "  type VARCHAR(20) NOT NULL DEFAULT 'maintenance',",
        "  attachment VARCHAR(255) NULL,",
        "  created_by INT NULL,",
    ],
    'expenses' => [
        "  person_name VARCHAR(120) NULL,",
        "  qty DECIMAL(18,3) NULL,",
        "  notes TEXT NULL,",
    ],
];
foreach ($targets as $table => $cols) {
    $pat = '/(CREATE TABLE\s+' . preg_quote($table, '/') . '\s*\(\s*\n)(.*?)(\) ENGINE=InnoDB;)/s';
    if (!preg_match($pat, $s, $m, PREG_OFFSET_CAPTURE)) { echo "NO MATCH: $table\n"; continue; }
    $head = $m[1][0]; $body = $m[2][0]; $tail = $m[3][0];
    $add = '';
    foreach ($cols as $col) {
        $name = preg_replace('/\s+.*/', '', trim($col));
        if (strpos($body, $name) === false) $add .= rtrim($col) . "\n";
    }
    $newBody = rtrim($body) . ($add !== '' ? "\n" . rtrim($add, "\n") . "\n" : "\n");
    $s = substr($s, 0, $m[1][1]) . $head . $newBody . $tail . substr($s, $m[3][1] + strlen($m[3][0]));
    echo "patched: $table\n";
}
file_put_contents($p, $s);
