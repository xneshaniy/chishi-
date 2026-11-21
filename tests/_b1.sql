CREATE TABLE expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  expense_no VARCHAR(30) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  payee VARCHAR(150) NULL,
  description VARCHAR(255) NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',   
  account_id INT NULL,                                
  attachment VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',       
  journal_entry_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_exp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL,
  person_name VARCHAR(120) NULL,
  qty DECIMAL(18,3) NULL
);