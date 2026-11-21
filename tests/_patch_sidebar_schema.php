<?php
/** Patch both fresh-install schemas with the sidebar+expense columns. */
$patches = [
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
$giftTable = [
    'sqlite' => "CREATE TABLE gifts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  gift_no VARCHAR(30) NOT NULL UNIQUE,
  gift_date DATE NOT NULL,
  recipient_name VARCHAR(150) NOT NULL,
  recipient_type VARCHAR(30) NOT NULL,
  gift_type VARCHAR(10) NOT NULL,
  amount DECIMAL(18,2) NULL,
  product_id INT NULL,
  qty DECIMAL(18,3) NULL,
  qty_unit VARCHAR(20) NULL,
  qty_pieces DECIMAL(18,3) NULL,
  reason VARCHAR(255) NULL,
  description TEXT NULL,
  notes TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  expense_id INT NULL,
  recorded_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gift_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_gift_exp FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL
);\n",
    'mysql'  => "CREATE TABLE gifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gift_no VARCHAR(30) NOT NULL UNIQUE,
  gift_date DATE NOT NULL,
  recipient_name VARCHAR(150) NOT NULL,
  recipient_type VARCHAR(30) NOT NULL,
  gift_type VARCHAR(10) NOT NULL,
  amount DECIMAL(18,2) NULL,
  product_id INT NULL,
  qty DECIMAL(18,3) NULL,
  qty_unit VARCHAR(20) NULL,
  qty_pieces DECIMAL(18,3) NULL,
  reason VARCHAR(255) NULL,
  description TEXT NULL,
  notes TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  expense_id INT NULL,
  recorded_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gift_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_gift_exp FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL
) ENGINE=InnoDB;\n",
];

foreach (['database/schema.sqlite.sql' => 'sqlite', 'database/schema.mysql.sql' => 'mysql'] as $path => $dialect) {
    $s = file_get_contents($path);
    foreach ($patches as $table => $cols) {
        $pat = '/CREATE TABLE\s+' . preg_quote($table, '/') . '\s*\(\s*\n(.*?)\);\s*\n/s';
        if (!preg_match($pat, $s, $m, PREG_OFFSET_CAPTURE)) {
            echo "SKIP (no match): $table in $path\n";
            continue;
        }
        $body = $m[1][0];
        // already has all cols?
        $add = '';
        foreach ($cols as $col) {
            $name = preg_replace('/\s+.*/', '', trim($col));
            if (strpos($body, $name) === false) $add .= "  " . trim($col) . "\n";
        }
        if ($add !== '') {
            $body2 = rtrim($body) . "\n" . rtrim($add, "\n") . "\n";
            $s = substr($s, 0, $m[1][1]) . $body2 . substr($s, $m[1][1] + strlen($body));
            echo "patched: $table (+" . str_word_count($add) . ")\n";
        } else {
            echo "OK (already complete): $table\n";
        }
    }
    if (strpos($s, 'CREATE TABLE gifts') === false) {
        $s = rtrim($s, "\n") . "\n\n-- ---------- Gifts ----------\n" . $giftTable[$dialect] . "\n";
        echo "appended to $path\n";
    } else {
        echo "OK gifts present in $path\n";
    }
    file_put_contents($path, $s);
}
