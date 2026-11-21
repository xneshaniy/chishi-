<?php
/** Add claim_goods, claim_goods_items, claim_goods_payments to both schema files. */
$mysql = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');
$lite  = file_get_contents(__DIR__ . '/../database/schema.sqlite.sql');

$mysqlTables = <<<SQL

-- ---------- Claim Goods (supplier claim receipts: accepted / damaged / rejected split) ----------
CREATE TABLE claim_goods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  purchase_invoice_id INT NULL,
  warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending_approval',  -- pending_approval|posted|rejected|reversed
  payment_type VARCHAR(10) NOT NULL DEFAULT 'outstanding',  -- cash|outstanding
  pay_account_id INT NULL,
  good_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  claim_amount DECIMAL(18,2) NOT NULL DEFAULT 0,          -- eligible claim payable under approved terms (accepted goods only)
  cash_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cg_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_cg_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_cg_status (status, date),
  INDEX idx_cg_supplier (supplier_id)
) ENGINE=InnoDB;

CREATE TABLE claim_goods_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_goods_id INT NOT NULL,
  product_id INT NOT NULL,
  good_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  claim_rate DECIMAL(18,2) NOT NULL DEFAULT 0,   -- per accepted unit (approved claim terms)
  line_claim DECIMAL(18,2) NOT NULL DEFAULT 0,   -- good_qty × claim_rate
  CONSTRAINT fk_cgi_cg FOREIGN KEY (claim_goods_id) REFERENCES claim_goods(id) ON DELETE CASCADE,
  CONSTRAINT fk_cgi_prod FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_cgi_cg (claim_goods_id)
) ENGINE=InnoDB;

CREATE TABLE claim_goods_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_goods_id INT NOT NULL,
  payment_no VARCHAR(30) NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending|posted|rejected
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cgp_cg FOREIGN KEY (claim_goods_id) REFERENCES claim_goods(id) ON DELETE CASCADE,
  CONSTRAINT fk_cgp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  INDEX idx_cgp_cg (claim_goods_id)
) ENGINE=InnoDB;

SQL;

$sqliteTables = <<<SQL

-- ---------- Claim Goods (supplier claim receipts: accepted / damaged / rejected split) ----------
CREATE TABLE claim_goods (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  purchase_invoice_id INT NULL,
  warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending_approval',
  payment_type VARCHAR(10) NOT NULL DEFAULT 'outstanding',
  pay_account_id INT NULL,
  good_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  claim_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cg_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_cg_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE claim_goods_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_goods_id INT NOT NULL,
  product_id INT NOT NULL,
  good_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  claim_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_claim DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_cgi_cg FOREIGN KEY (claim_goods_id) REFERENCES claim_goods(id) ON DELETE CASCADE,
  CONSTRAINT fk_cgi_prod FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE claim_goods_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_goods_id INT NOT NULL,
  payment_no VARCHAR(30) NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cgp_cg FOREIGN KEY (claim_goods_id) REFERENCES claim_goods(id) ON DELETE CASCADE,
  CONSTRAINT fk_cgp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

SQL;

$anchor = 'SET FOREIGN_KEY_CHECKS = 1;';
if (strpos($mysql, $anchor) === false) { fwrite(STDERR, "mysql anchor miss\n"); exit(1); }
$mysql = str_replace($anchor, $mysqlTables . "\n" . $anchor, $mysql);

$sqliteAnchor = "\n-- indexes (sqlite)\n";
if (strpos($lite, $sqliteAnchor) === false) { fwrite(STDERR, "sqlite anchor miss\n"); exit(1); }
$lite = str_replace($sqliteAnchor, "\n" . $sqliteTables . $sqliteAnchor, $lite);
$lite .= "CREATE INDEX IF NOT EXISTS idx_cg_status ON claim_goods (status, date);\n";
$lite .= "CREATE INDEX IF NOT EXISTS idx_cg_supplier ON claim_goods (supplier_id);\n";
$lite .= "CREATE INDEX IF NOT EXISTS idx_cgi_cg ON claim_goods_items (claim_goods_id);\n";
$lite .= "CREATE INDEX IF NOT EXISTS idx_cgp_cg ON claim_goods_payments (claim_goods_id);\n";

file_put_contents(__DIR__ . '/../database/schema.mysql.sql', $mysql);
file_put_contents(__DIR__ . '/../database/schema.sqlite.sql', $lite);
echo "claim goods tables added\n";
