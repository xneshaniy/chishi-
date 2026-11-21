<?php
$file = __DIR__ . '/../database/schema.mysql.sql';
$content = file_get_contents($file);

$target = "  status VARCHAR(10) NOT NULL DEFAULT 'posted',
  recorded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rdu_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_rdu_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT fk_rdu_user FOREIGN KEY (recorded_by) REFERENCES users(id),
  UNIQUE KEY uq_rm_usage_active (product_id, usage_date, warehouse_id)
) ENGINE=InnoDB;";

$replacement = "  status VARCHAR(10) NOT NULL DEFAULT 'posted',
  recorded_by INT NULL,
  production_run_id INT NULL,
  cancel_reason VARCHAR(255) NULL,
  cancelled_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rdu_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_rdu_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT fk_rdu_user FOREIGN KEY (recorded_by) REFERENCES users(id),
  KEY idx_rm_usage_date (usage_date, product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_production_rm_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  note VARCHAR(255) NULL,
  INDEX idx_prod_rm_run (run_id),
  INDEX idx_prod_rm_prod (product_id)
) ENGINE=InnoDB;";

// Normalize line endings for replacement
$contentNorm = str_replace("\r\n", "\n", $content);
$targetNorm = str_replace("\r\n", "\n", $target);
$replacementNorm = str_replace("\r\n", "\n", $replacement);

if (strpos($contentNorm, $targetNorm) !== false) {
    $newContent = str_replace($targetNorm, $replacementNorm, $contentNorm);
    file_put_contents($file, $newContent);
    echo "schema.mysql.sql updated successfully!\n";
} else {
    echo "Target string not found!\n";
}
