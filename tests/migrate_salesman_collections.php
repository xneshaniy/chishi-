<?php
/** Add salesman_collections to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$exists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='salesman_collections'")->fetchColumn();
if ($exists) { echo "salesman_collections already exists\n"; exit(0); }
$pdo->exec("CREATE TABLE salesman_collections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  customer_id INT NULL,
  date DATE NOT NULL,
  sales_invoice_id INT NULL,
  amount DECIMAL(18,2) NOT NULL,
  cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  reference VARCHAR(60) NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scol_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_scol_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_scol_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_scol_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
)");
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_scol_sm ON salesman_collections (salesman_id, date)');
echo "salesman_collections created\n";
