-- ==========================================================
-- Chishti Tobacco Company (CTC) ERP — Database Schema (SQLite)
-- Auto-generated full schema for Production & Deployment
-- ==========================================================

CREATE TABLE approval_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_type VARCHAR(20) NOT NULL,     -- payment|transfer|expense|payroll|advance|loan|bonus|discount|refund|other
  reference_type VARCHAR(40) NOT NULL,   -- payments|expenses|payroll|employee_advances|employee_loans|sales_invoices|bank_transfers|salesman_issues
  reference_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  requested_by INT NOT NULL,
  requested_at DATETIME NOT NULL,
  approver_id INT NULL,
  decided_at DATETIME NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending|approved|rejected
  decision_note VARCHAR(255) NULL,
  created_at DATETIME NULL,
  UNIQUE (reference_type, reference_id, request_type)
);

CREATE TABLE areas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  province_id INT NULL,
  city_id INT NULL,
  name VARCHAR(120) NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT uq_area_city UNIQUE (city_id, name),
  CONSTRAINT fk_area_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE SET NULL,
  CONSTRAINT fk_area_city FOREIGN KEY (city_id) REFERENCES pk_cities(id) ON DELETE CASCADE
);

CREATE TABLE attachments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entity VARCHAR(60) NOT NULL,
  entity_id VARCHAR(60) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE attendance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'present', -- present|absent|leave|half_day|off
  hours DECIMAL(10,2) NOT NULL DEFAULT 8.00,
  CONSTRAINT fk_att_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(60) NOT NULL,
  entity_id VARCHAR(60) NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bank_transfers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  transfer_no VARCHAR(30) NOT NULL UNIQUE,
  from_account_id INT NOT NULL,
  to_account_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending|posted|rejected|reversed
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bt_from FOREIGN KEY (from_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_bt_to FOREIGN KEY (to_account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE bills_of_materials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INT NOT NULL,                -- finished good produced
  name VARCHAR(120) NOT NULL,
  output_qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  is_active INTEGER NOT NULL DEFAULT 1,
  CONSTRAINT fk_bom_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE bom_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  bom_id INT NOT NULL,
  material_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  CONSTRAINT fk_boml_b FOREIGN KEY (bom_id) REFERENCES bills_of_materials(id) ON DELETE CASCADE,
  CONSTRAINT fk_boml_m FOREIGN KEY (material_id) REFERENCES products(id)
);

CREATE TABLE bonus_schemes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  kind VARCHAR(20) NOT NULL DEFAULT 'salesman',  -- salesman|customer|purchase|promotion|special
  description VARCHAR(255) NULL,
  requires_approval TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bonuses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  bonus_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  period_month TEXT NOT NULL,                       -- YYYY-MM
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',       -- pending|paid
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bon_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
);

CREATE TABLE branches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  is_main INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE cash_bank_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(10) NOT NULL,               -- cash|bank
  bank_name VARCHAR(120) NULL,
  account_no VARCHAR(60) NULL,
  iban VARCHAR(60) NULL,
  branch VARCHAR(120) NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  gl_account_id INT NULL,                  -- linked chart of accounts row
  is_active INTEGER NOT NULL DEFAULT 1
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE chart_of_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(12) NOT NULL,                -- asset|liability|equity|income|expense
  parent_id INT NULL,
  is_system INTEGER NOT NULL DEFAULT 0,
  allow_posting INTEGER NOT NULL DEFAULT 1,
  system_key VARCHAR(40) NULL UNIQUE,       -- AR|AP|CASH|... used by auto-posting engine
  is_active INTEGER NOT NULL DEFAULT 1,
  CONSTRAINT fk_coa_parent FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL
);

CREATE TABLE cheques (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cheque_no VARCHAR(40) NOT NULL,
  bank_account_id INT NOT NULL,
  direction VARCHAR(8) NOT NULL DEFAULT 'received', -- received|issued
  party_type VARCHAR(10) NULL,            -- customer|supplier|other
  party_name VARCHAR(150) NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  issue_date DATE NULL,
  due_date DATE NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',   -- pending|cleared|bounced|cancelled
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_chq_ba FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id)
);

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

CREATE TABLE claims (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_no VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  sales_invoice_id INT NULL,
  date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',      -- pending|approved|rejected
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clm_c FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_clm_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
);

CREATE TABLE company_expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        expense_no VARCHAR(30) NOT NULL UNIQUE,
        linked_expense_id INT NULL,
        expense_date DATE NOT NULL,
        category_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        description VARCHAR(255) NULL,
        quantity DECIMAL(18,3) NULL,
        unit VARCHAR(20) NULL,
        rate DECIMAL(18,2) NULL,
        amount DECIMAL(18,2) NOT NULL,
        supplier VARCHAR(150) NULL,
        payment_type VARCHAR(6) NOT NULL,
        account_id INT NULL,
        attachment VARCHAR(255) NULL,
        notes TEXT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'pending',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ce_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
        CONSTRAINT fk_ce_account FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
        CONSTRAINT fk_ce_linked FOREIGN KEY (linked_expense_id) REFERENCES expenses(id) ON DELETE SET NULL
    );

CREATE TABLE company_settings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at DATETIME NULL
);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  alt_phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  area VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  ntn VARCHAR(40) NULL,
  credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_type VARCHAR(6) NOT NULL DEFAULT 'debit',  -- debit|credit
  salesman_id INT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, province_id INT NULL, city_id INT NULL, area_id INT NULL, deactivated_at DATETIME NULL, deactivated_by INT NULL, is_archived INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT fk_cust_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL
);

CREATE TABLE daily_production (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  run_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  reversed_at DATETIME NULL,
  reversed_by INT NULL,
  reversal_reason VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE daily_production_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  run_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  batch_no VARCHAR(60) NULL,
  line_notes VARCHAR(255) NULL,
  CONSTRAINT fk_dpi_run FOREIGN KEY (run_id) REFERENCES daily_production(id) ON DELETE CASCADE,
  CONSTRAINT fk_dpi_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE daily_production_rm_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id INT NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    qty_kg DECIMAL(18,3) NOT NULL,
    note VARCHAR(255) NULL
);

CREATE TABLE employee_advances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  notes VARCHAR(255) NULL,
  payroll_id INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'approved',  -- pending|approved|rejected|disbursed|cancelled
  approved_by INT NULL,
  approved_at DATETIME NULL,
  monthly_deduction DECIMAL(18,2) NULL,
  deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  account_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NULL,
  CONSTRAINT fk_adv_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE employee_bonuses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
  account_id INT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eb_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE employee_deductions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  approved_by INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_edd_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE employee_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  doc_type VARCHAR(60) NOT NULL DEFAULT 'other',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_empdoc_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE employee_loans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  loan_no VARCHAR(30) NOT NULL UNIQUE,
  employee_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  installment_type VARCHAR(10) NOT NULL DEFAULT 'monthly',  -- weekly|monthly
  installment_amount DECIMAL(18,2) NOT NULL,
  total_installments INT NOT NULL DEFAULT 1,
  recovered DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_installments INT NOT NULL DEFAULT 0,
  outstanding DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',  -- pending|active|completed|rejected|cancelled
  account_id INT NULL,
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE employees (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  cnic VARCHAR(20) NULL,
  phone VARCHAR(40) NULL,
  designation VARCHAR(100) NULL,
  department VARCHAR(100) NULL,
  join_date DATE NULL,
  salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly', -- monthly|daily
  salary_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(18,2) NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
, payment_method VARCHAR(10) NOT NULL DEFAULT 'cash', photo_path TEXT NULL, cnic_front_path TEXT NULL, cnic_back_path TEXT NULL, address TEXT NULL, province_id INT NULL, city_id INT NULL, area_id INT NULL, deactivated_at DATETIME NULL, deactivated_by INT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'employee', user_id INT NULL, is_archived INTEGER NOT NULL DEFAULT 0, salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly', left_date DATE DEFAULT NULL, departure_reason TEXT DEFAULT NULL);

CREATE TABLE expense_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  gl_account_id INT NOT NULL,               -- linked expense account
  is_active INTEGER NOT NULL DEFAULT 1
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  expense_no VARCHAR(30) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  payee VARCHAR(150) NULL,
  description VARCHAR(255) NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',   -- cash|bank|payable
  account_id INT NULL,                                -- cash/bank account (null if payable)
  attachment VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',       -- pending|approved|posted|rejected|reversed
  journal_entry_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, person_name VARCHAR(120) NULL, qty DECIMAL(18,3) NULL, notes TEXT NULL,
  CONSTRAINT fk_exp_cat FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_exp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL
);

CREATE TABLE factory_issue_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INT NOT NULL,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_fii_i FOREIGN KEY (issue_id) REFERENCES factory_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_fii_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE factory_issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  batch_no VARCHAR(60) NULL,
  details VARCHAR(255) NULL,
  production_order_id INT NULL,
  issued_by INT NULL,
  approved_by INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fi_po FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE SET NULL
);

CREATE TABLE fiscal_periods (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  fiscal_year_id INT NOT NULL,
  name VARCHAR(30) NOT NULL,          -- e.g. Jul 2026
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open', -- open|closed
  CONSTRAINT fk_fp_fy FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE
);

CREATE TABLE fiscal_years (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(30) NOT NULL,          -- e.g. FY 2026-27
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 0,
  is_closed INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE gifts (
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
        );

CREATE TABLE grinding_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_id INT NOT NULL,
  grind_date DATE NOT NULL,
  qty_received_kg DECIMAL(18,3) NOT NULL,
  loss_kg DECIMAL(18,3) NOT NULL DEFAULT 0,
  charges DECIMAL(18,2) NOT NULL DEFAULT 0,
  charges_status VARCHAR(10) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gr_send FOREIGN KEY (send_id) REFERENCES grinding_sends(id) ON DELETE CASCADE
);

CREATE TABLE grinding_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_id INT NOT NULL,
  return_date DATE NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gret_send FOREIGN KEY (send_id) REFERENCES grinding_sends(id)
);

CREATE TABLE grinding_sends (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  send_no VARCHAR(40) NOT NULL UNIQUE,
  date DATE NOT NULL,
  product_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  party_name VARCHAR(150) NOT NULL,
  charges_per_kg DECIMAL(18,4) NULL,
  vehicle VARCHAR(120) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gs_prod FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE idempotency_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  idem_key VARCHAR(64) NOT NULL UNIQUE,
  user_id INT NULL,
  route VARCHAR(120) NULL,
  created_at DATETIME NOT NULL
);

CREATE TABLE journal_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entry_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  fiscal_period_id INT NULL,
  voucher_type VARCHAR(10) NOT NULL DEFAULT 'JV',
  reference_type VARCHAR(40) NULL,
  reference_id INT NULL,
  memo VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',   -- draft|posted|reversed
  reversal_of INT NULL,
  idem_key VARCHAR(64) NULL UNIQUE,
  created_by INT NULL,
  posted_by INT NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_je_fp FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL,
  CONSTRAINT fk_je_rev FOREIGN KEY (reversal_of) REFERENCES journal_entries(id) ON DELETE SET NULL
);

CREATE TABLE journal_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entry_id INT NOT NULL,
  account_id INT NOT NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  customer_id INT NULL,
  supplier_id INT NULL,
  memo VARCHAR(255) NULL,
  CONSTRAINT fk_jl_e FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_jl_a FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
  CONSTRAINT fk_jl_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_jl_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE loan_installments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  loan_id INT NOT NULL,
  installment_no INT NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'due',  -- due|deducted|paid
  payroll_id INT NULL,
  paid_date DATE NULL,
  UNIQUE (loan_id, installment_no),

  CONSTRAINT fk_inst_loan FOREIGN KEY (loan_id) REFERENCES employee_loans(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(60) NOT NULL,
  success INTEGER NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL
);

CREATE TABLE login_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INT NULL,
  username VARCHAR(60) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL,        -- success|failed|locked
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE maintenance_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(150) NOT NULL,
  asset_no VARCHAR(60) NULL UNIQUE,
  department VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
, is_archived INTEGER NOT NULL DEFAULT 0, category VARCHAR(80) NOT NULL DEFAULT '', purchase_date DATE NULL, description TEXT NULL, photo_path VARCHAR(255) NULL, status VARCHAR(12) NOT NULL DEFAULT 'active');

CREATE TABLE maintenance_parts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  maintenance_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_mp_rec FOREIGN KEY (maintenance_id) REFERENCES maintenance_records(id) ON DELETE CASCADE
);

CREATE TABLE maintenance_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  record_no VARCHAR(30) NOT NULL UNIQUE,
  asset_id INT NULL,
  machine_name VARCHAR(150) NOT NULL,
  asset_no VARCHAR(60) NULL,
  department VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(120) NULL,
  problem TEXT NOT NULL,
  problem_date DATE NOT NULL,
  work_performed TEXT NULL,
  work_date DATE NULL,
  vendor VARCHAR(150) NULL,
  labour_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  parts_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  payment_type VARCHAR(10) NOT NULL DEFAULT 'cash',
  account_id INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  attachment VARCHAR(255) NULL,
  expense_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mr_asset FOREIGN KEY (asset_id) REFERENCES maintenance_assets(id) ON DELETE SET NULL,
  CONSTRAINT fk_mr_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INT NOT NULL,
  type VARCHAR(40) NOT NULL,            -- payment_request|approval|expense|advance|loan|sale|purchase|low_stock|receipt|overdue|accounting|journal|bonus
  title VARCHAR(180) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(220) NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  dedupe_key VARCHAR(90) NULL,          -- per-user deduplication (same user + same event = one notification)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT uq_notif_user_dedupe UNIQUE (user_id, dedupe_key)
);

CREATE TABLE other_income (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  income_no VARCHAR(30) NOT NULL UNIQUE,
  category VARCHAR(60) NOT NULL DEFAULT 'other',
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  payer VARCHAR(150) NULL,
  description VARCHAR(255) NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
  account_id INT NOT NULL,
  attachment VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oi_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL
);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  payment_no VARCHAR(30) NOT NULL UNIQUE,
  party_type VARCHAR(10) NOT NULL,          -- supplier|expense|owner|salary|salesman|other
  party_id INT NULL,                        -- supplier id / expense id / employee id …
  account_id INT NOT NULL,                  -- cash/bank account paid from
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending|posted|rejected|reversed
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE payroll (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  basic DECIMAL(18,2) NOT NULL DEFAULT 0,
  allowances DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime DECIMAL(18,2) NOT NULL DEFAULT 0,
  bonus DECIMAL(18,2) NOT NULL DEFAULT 0,
  advances_deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_deductions DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(10,2) NULL,
  hours_worked DECIMAL(10,2) NULL,
  loan_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'draft',   -- draft|pending_approval|paid|reversed
  journal_entry_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pay_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE payroll_deductions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  payroll_id INT NOT NULL,
  source_type VARCHAR(10) NOT NULL,   -- advance|loan_installment
  source_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL
);

CREATE TABLE payroll_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  payroll_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pp_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE permissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(80) NOT NULL UNIQUE,   -- module.action e.g. sales.create
  module VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL
);

CREATE TABLE personal_loan_repayments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_id INT NOT NULL,
        repayment_date DATE NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        note VARCHAR(255) NULL,
        recorded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, account_id INTEGER DEFAULT NULL,
        CONSTRAINT fk_plr_loan FOREIGN KEY (loan_id) REFERENCES personal_loans(id) ON DELETE CASCADE,
        CONSTRAINT fk_plr_user FOREIGN KEY (recorded_by) REFERENCES users(id)
    );

CREATE TABLE personal_loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_no VARCHAR(30) NOT NULL UNIQUE,
        person_name VARCHAR(150) NOT NULL,
        phone VARCHAR(40) NULL,
        loan_date DATE NOT NULL,
        original_amount DECIMAL(18,2) NOT NULL,
        expected_date DATE NULL,
        description VARCHAR(255) NULL,
        notes TEXT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, account_id INTEGER DEFAULT NULL,
        CONSTRAINT fk_pl_user FOREIGN KEY (created_by) REFERENCES users(id)
    );

CREATE TABLE pk_cities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  province_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  CONSTRAINT fk_pkcity_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE CASCADE,
  CONSTRAINT uq_pkcity UNIQUE (province_id, name)
);

CREATE TABLE pk_provinces (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(60) NOT NULL UNIQUE,
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE product_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'finished'  -- finished|raw|packaging
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE product_packaging (
  product_id INT PRIMARY KEY,
  pack_pieces DECIMAL(10,3) NULL,
  bundle_packs DECIMAL(10,3) NULL,
  packet_bundles DECIMAL(10,3) NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_pack_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE production_inputs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  production_order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pi_po FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE production_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,              -- finished goods destination
  bom_id INT NULL,
  qty_planned DECIMAL(18,3) NOT NULL,
  qty_produced DECIMAL(18,3) NOT NULL DEFAULT 0,
  waste_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  labor_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  overhead_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  materials_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',  -- draft|released|completed|cancelled
  batch_no VARCHAR(60) NULL,
  expiry_date DATE NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL, notes VARCHAR(255) NULL,
  CONSTRAINT fk_po_p FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_po_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sku VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  unit_id INT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'finished',  -- finished|raw|packaging
  pack_size VARCHAR(60) NULL,
  purchase_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  issue_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(18,3) NOT NULL DEFAULT 0,
  has_expiry INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, photo_path VARCHAR(255) NULL, description TEXT NULL, is_archived INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
);

CREATE TABLE purchase_invoices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  po_id INT NULL,
  supplier_invoice_no VARCHAR(60) NULL,
  date DATE NOT NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'credit',  -- cash|credit
  warehouse_id INT NOT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',       -- posted|reversed
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid', amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0, cash_account_id INT NULL,
  CONSTRAINT fk_pi_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_pi_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE purchase_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_bonus TINYINT(1) NOT NULL DEFAULT 0, unit_short VARCHAR(10) NULL, qty_kg DECIMAL(18,3) NULL,
  CONSTRAINT fk_pur_i FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE purchase_order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  po_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE purchase_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  po_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  order_date DATE NOT NULL,
  expected_date DATE NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',     -- draft|approved|received|cancelled
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pur_po_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE receipts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  receipt_no VARCHAR(30) NOT NULL UNIQUE,
  party_type VARCHAR(10) NOT NULL DEFAULT 'customer',  -- customer
  customer_id INT NULL,
  account_id INT NOT NULL,                             -- cash_bank_accounts
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, contact_name VARCHAR(120) NULL,
  CONSTRAINT fk_rcpt_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_rcpt_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE rm_daily_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usage_date DATE NOT NULL,
        product_id INT NOT NULL,
        warehouse_id INT NOT NULL,
        qty_kg DECIMAL(18,3) NOT NULL,
        unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
        total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'posted',
        recorded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, production_run_id INT NULL, cancel_reason VARCHAR(255) NULL, cancelled_by INT NULL,
        CONSTRAINT fk_rdu_prod FOREIGN KEY (product_id) REFERENCES products(id),
        CONSTRAINT fk_rdu_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        CONSTRAINT fk_rdu_user FOREIGN KEY (recorded_by) REFERENCES users(id)
    );

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_r FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_p FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(40) NOT NULL UNIQUE,   -- owner|admin|accountant|manager|storekeeper|salesman
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL
);

CREATE TABLE salary_changes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
  old_rate DECIMAL(18,2) NULL,
  new_rate DECIMAL(18,2) NOT NULL,
  effective_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salary_change FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE sales_invoice_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,   -- qty*price - discount + tax
  net_before_tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(18,4) NOT NULL DEFAULT 0,
  cost_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_bonus INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT fk_sii_i FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_sii_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE sales_invoices (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          invoice_no VARCHAR(30) NOT NULL UNIQUE,
          customer_id INT NULL,
          salesman_id INT NULL,
          warehouse_id INT NOT NULL,
          date DATE NOT NULL,
          due_date DATE NULL,
          payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
          cash_account_id INT NULL,
          subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
          discount DECIMAL(18,2) NOT NULL DEFAULT 0,
          tax DECIMAL(18,2) NOT NULL DEFAULT 0,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          cost_total DECIMAL(18,2) NOT NULL DEFAULT 0,
          authorized_by INT NULL,
          bonus_scheme VARCHAR(100) NULL,
          status VARCHAR(10) NOT NULL DEFAULT 'posted',
          journal_entry_id INT NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_si_c FOREIGN KEY (customer_id) REFERENCES customers(id),
          CONSTRAINT fk_si_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL,
          CONSTRAINT fk_si_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        );

CREATE TABLE sales_order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sales_order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_soi_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_soi_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE sales_orders (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          so_no VARCHAR(30) NOT NULL UNIQUE,
          customer_id INT NULL,
          salesman_id INT NULL,
          order_date DATE NOT NULL,
          delivery_date DATE NULL,
          status VARCHAR(12) NOT NULL DEFAULT 'draft',
          subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          notes VARCHAR(255) NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_so_c FOREIGN KEY (customer_id) REFERENCES customers(id),
          CONSTRAINT fk_so_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL
        );

CREATE TABLE sales_return_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  return_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(18,4) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sri_r FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_sri_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE sales_returns (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          return_no VARCHAR(30) NOT NULL UNIQUE,
          sales_invoice_id INT NOT NULL,
          customer_id INT NULL,
          date DATE NOT NULL,
          reason VARCHAR(255) NULL,
          total DECIMAL(18,2) NOT NULL DEFAULT 0,
          status VARCHAR(10) NOT NULL DEFAULT 'posted',
          journal_entry_id INT NULL,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_sr_si FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id),
          CONSTRAINT fk_sr_c FOREIGN KEY (customer_id) REFERENCES customers(id)
        );

CREATE TABLE salesman_collections (
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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, contact_name VARCHAR(120) NULL,
  CONSTRAINT fk_scol_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_scol_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_scol_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_scol_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
);

CREATE TABLE salesman_customers (
  salesman_id INT NOT NULL,
  customer_id INT NOT NULL,
  PRIMARY KEY (salesman_id, customer_id),
  CONSTRAINT fk_sc_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_sc_c FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE salesman_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  doc_type VARCHAR(60) NOT NULL DEFAULT 'other',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sdoc_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
);

CREATE TABLE "salesman_expenses" (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          salesman_id INT NOT NULL,
          date DATE NOT NULL,
          expense_type VARCHAR(20) NOT NULL,
          amount DECIMAL(18,2) NOT NULL,
          description VARCHAR(255) NOT NULL,
          payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
          account_id INT NULL,
          paid_from VARCHAR(10) NOT NULL DEFAULT 'company',
          attachment VARCHAR(255) NULL,
          notes VARCHAR(255) NULL,
          status VARCHAR(12) NOT NULL DEFAULT 'pending',
          journal_entry_id INT NULL,
          requested_by INT NULL,
          approved_by INT NULL,
          approved_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_se_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT fk_se_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
        );

CREATE TABLE salesman_issue_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  paid_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  bonus_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  issue_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  bonus_cost DECIMAL(18,2) NOT NULL DEFAULT 0, return_condition VARCHAR(10) NULL,
  CONSTRAINT fk_sii2_i FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_sii2_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE salesman_issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  from_warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',      -- pending_approval|posted|reversed
  issue_type VARCHAR(10) NOT NULL DEFAULT 'transfer',  -- transfer|credit_issue
  total_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_bonus_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  approved_by INT NULL,   bonus_scheme VARCHAR(100) NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, bonus_reason VARCHAR(255) NULL, related_issue_id INT NULL,
  CONSTRAINT fk_si2_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id),
  CONSTRAINT fk_si2_wh FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE salesman_ledger (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  doc_type VARCHAR(20) NOT NULL,     -- issue|payment|return|adjustment
  doc_id INT NULL,
  doc_no VARCHAR(30) NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (doc_type, doc_id),

  CONSTRAINT fk_smr_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
);

CREATE TABLE salesman_payment_allocations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_id INT NOT NULL,
        issue_id INT NOT NULL,
        salesman_id INT NOT NULL,
        amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (payment_id, issue_id),
        CONSTRAINT fk_spa_payment FOREIGN KEY (payment_id) REFERENCES salesman_payments(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_issue FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
    );

CREATE TABLE salesman_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  receipt_no VARCHAR(30) NULL UNIQUE,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  total_amount DECIMAL(18,2) NOT NULL,
  cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_account_id INT NULL,
  bank_account_id INT NULL,
  bank_reference VARCHAR(60) NULL,
  payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
  notes VARCHAR(255) NULL,
  received_by INT NULL,
  journal_entry_id INT NULL,
  idem_key VARCHAR(80) NULL UNIQUE,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_smp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_smp_cash FOREIGN KEY (cash_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_bank FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_user FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE salesman_salary_advances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',   -- pending|paid|rejected|reversed
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE salesman_salary_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  period VARCHAR(10) NULL,             -- YYYY-MM the payment is for
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',  -- pending|paid|rejected|reversed
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
);

CREATE TABLE salesman_settlements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  sales_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  collected_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_counted DECIMAL(18,2) NOT NULL DEFAULT 0,
  expected_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance DECIMAL(18,2) NOT NULL DEFAULT 0,         -- negative = shortage
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, reference VARCHAR(24) NULL, issued_value DECIMAL(18,2) NOT NULL DEFAULT 0, additional_issued DECIMAL(18,2) NOT NULL DEFAULT 0, returns_value DECIMAL(18,2) NOT NULL DEFAULT 0, collections_total DECIMAL(18,2) NOT NULL DEFAULT 0, cash_received DECIMAL(18,2) NOT NULL DEFAULT 0, bank_received DECIMAL(18,2) NOT NULL DEFAULT 0, expenses_total DECIMAL(18,2) NOT NULL DEFAULT 0, remaining_payable DECIMAL(18,2) NOT NULL DEFAULT 0, received_by INT NULL,
  CONSTRAINT fk_set_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
);

CREATE TABLE salesmen (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  cnic VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  area VARCHAR(100) NULL,
  commission_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  warehouse_id INT NULL,              -- stock location dedicated to this salesman
  salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, join_date DATE NULL, vehicle_type VARCHAR(60) NULL, vehicle_reg VARCHAR(60) NULL, vehicle_note VARCHAR(255) NULL, cnic_front_path TEXT NULL, cnic_back_path TEXT NULL, province_id INT NULL, city_id INT NULL, area_id INT NULL, deactivated_at DATETIME NULL, deactivated_by INT NULL, photo_path VARCHAR(255) NULL, is_archived INTEGER NOT NULL DEFAULT 0, salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly', left_date DATE DEFAULT NULL, departure_reason TEXT DEFAULT NULL,
  CONSTRAINT fk_sm_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
);

CREATE TABLE "staff_attendance" (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      employee_id INT NULL,
      salesman_id INT NULL,
      date DATE NOT NULL,
      status VARCHAR(8) NOT NULL,
      marked_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      CONSTRAINT fk_staffatt_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
      CONSTRAINT fk_staffatt_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
      CONSTRAINT chk_sa_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
    );

CREATE TABLE "staff_attendance_audit" (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          employee_id INT NULL,
          salesman_id INT NULL,
          date DATE NOT NULL,
          old_status VARCHAR(8) NULL,
          new_status VARCHAR(8) NULL,
          changed_by INT NULL,
          note VARCHAR(255) NULL,
          changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_audit_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          CONSTRAINT fk_audit_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT chk_saaud_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
        );

CREATE TABLE staff_leaves (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days INT NOT NULL DEFAULT 1,
  leave_type VARCHAR(40) NOT NULL DEFAULT 'casual',
  is_paid INTEGER NOT NULL DEFAULT 1,
  reason VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_staff_leave FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE stock_adjustment_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  adjustment_id INT NOT NULL,
  product_id INT NOT NULL,
  direction VARCHAR(3) NOT NULL,          -- in|out
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sai_a FOREIGN KEY (adjustment_id) REFERENCES stock_adjustments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sai_p FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE stock_adjustments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  adjustment_no VARCHAR(30) NOT NULL UNIQUE,
  warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',  -- pending|approved|rejected
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sa_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE stock_balances (
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, warehouse_id),
  CONSTRAINT fk_sb_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
);

CREATE TABLE stock_cost_layers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_remaining DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL,
  source_type VARCHAR(40) NULL,
  source_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE stock_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  type VARCHAR(30) NOT NULL,  -- opening|purchase|sale|sale_return|issue|receipt|transfer_in|transfer_out|adjustment_in|adjustment_out|production_in|production_consumption|damaged|expired|salesman_issue_out
  qty DECIMAL(18,3) NOT NULL,        -- signed: + in / - out
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance_qty DECIMAL(18,3) NULL,
  ref_type VARCHAR(40) NULL,
  ref_id INT NULL,
  batch_no VARCHAR(60) NULL,
  expiry_date DATE NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
);

CREATE TABLE supplier_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    default_rate DECIMAL(18,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(supplier_id, product_id)
);

CREATE TABLE suppliers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  ntn VARCHAR(40) NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_type VARCHAR(6) NOT NULL DEFAULT 'credit', -- supplier opening is normally payable (credit)
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE units (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(40) NOT NULL,
  short VARCHAR(10) NOT NULL
, is_archived INTEGER NOT NULL DEFAULT 0);

CREATE TABLE user_permissions (
        user_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, permission_id)
    );

CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_ur_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_r FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username VARCHAR(60) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  branch_id INT NULL,
  salesman_id INT NULL,
  preferred_language VARCHAR(10) NOT NULL DEFAULT 'en',
  notify_sound INTEGER NOT NULL DEFAULT 1,
  notify_desktop INTEGER NOT NULL DEFAULT 1,
  is_active INTEGER NOT NULL DEFAULT 1,
  force_password_change INTEGER NOT NULL DEFAULT 0,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, province_id INT NULL, city_id INT NULL, area_id INT NULL, deactivated_at DATETIME NULL, deactivated_by INT NULL, temp_password VARCHAR(100) DEFAULT NULL,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

CREATE TABLE vehicle_fuel_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  vehicle_id INT NOT NULL,
  date DATE NOT NULL,
  liters DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  mileage INT NULL,
  station VARCHAR(120) NULL, created_by INT NULL,
  CONSTRAINT fk_fuel_v FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE vehicle_maintenance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  vehicle_id INT NOT NULL,
  date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  vendor VARCHAR(120) NULL,
  cost DECIMAL(18,2) NOT NULL DEFAULT 0, type VARCHAR(20) NOT NULL DEFAULT 'maintenance', attachment VARCHAR(255) NULL, created_by INT NULL,
  CONSTRAINT fk_maint_v FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE vehicles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reg_no VARCHAR(30) NOT NULL UNIQUE,
  make VARCHAR(60) NULL,
  model VARCHAR(60) NULL,
  year INT NULL,
  driver_name VARCHAR(120) NULL,
  is_active INTEGER NOT NULL DEFAULT 1
, is_archived INTEGER NOT NULL DEFAULT 0, name VARCHAR(150) NULL, vehicle_type VARCHAR(30) NULL, purchase_date DATE NULL, photo_path VARCHAR(255) NULL, status VARCHAR(12) NOT NULL DEFAULT 'active', notes VARCHAR(255) NULL);

CREATE TABLE voucher_sequences (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  fiscal_year_id INT NOT NULL,
  voucher_type VARCHAR(10) NOT NULL,
  current_no INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_vs_fy FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE
);

CREATE TABLE warehouses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  branch_id INT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'warehouse', -- factory|central|warehouse|salesman|vehicle
  is_default INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, is_archived INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT fk_wh_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

CREATE INDEX idx_approval_status ON approval_requests (status, requested_at);

CREATE INDEX idx_attachments_idx_att ON attachments (entity, entity_id);

CREATE INDEX idx_audit_logs_idx_audit_entity ON audit_logs (entity, entity_id);

CREATE INDEX idx_audit_logs_idx_audit_time ON audit_logs (created_at);

CREATE INDEX idx_audit_logs_idx_audit_user ON audit_logs (user_id, created_at);

CREATE INDEX idx_cg_status ON claim_goods (status, date);

CREATE INDEX idx_cg_supplier ON claim_goods (supplier_id);

CREATE INDEX idx_cgi_cg ON claim_goods_items (claim_goods_id);

CREATE INDEX idx_cgp_cg ON claim_goods_payments (claim_goods_id);

CREATE INDEX idx_chart_of_accounts_idx_coa_type ON chart_of_accounts (type);

CREATE INDEX idx_cheques_idx_chq_due ON cheques (due_date);

CREATE INDEX idx_cheques_idx_chq_status ON cheques (status);

CREATE INDEX idx_customers_idx_cust_area ON customers (area);

CREATE INDEX idx_customers_idx_cust_salesman ON customers (salesman_id);

CREATE INDEX idx_dp_date ON daily_production (date);

CREATE INDEX idx_dpi_p ON daily_production_items (product_id, run_id);

CREATE INDEX idx_eb_emp ON employee_bonuses (employee_id, date);

CREATE INDEX idx_employees_user_id ON employees (user_id);

CREATE INDEX idx_expenses_idx_exp_date ON expenses (date);

CREATE INDEX idx_expenses_idx_exp_status ON expenses (status);

CREATE INDEX idx_fii_i ON factory_issue_items (issue_id);

CREATE INDEX idx_fii_p ON factory_issue_items (product_id);

CREATE INDEX idx_fiscal_periods_idx_fp_dates ON fiscal_periods (start_date, end_date);

CREATE INDEX idx_journal_entries_idx_je_date ON journal_entries (date);

CREATE INDEX idx_journal_entries_idx_je_ref ON journal_entries (reference_type, reference_id);

CREATE INDEX idx_journal_entries_idx_je_status ON journal_entries (status);

CREATE INDEX idx_journal_lines_idx_jl_account ON journal_lines (account_id, entry_id);

CREATE INDEX idx_journal_lines_idx_jl_cust ON journal_lines (customer_id);

CREATE INDEX idx_journal_lines_idx_jl_sup ON journal_lines (supplier_id);

CREATE INDEX idx_loan_status ON employee_loans (status);

CREATE INDEX idx_login_attempts_idx_la ON login_attempts (ip, username, attempted_at);

CREATE INDEX idx_login_history_idx_lh_user ON login_history (user_id, created_at);

CREATE INDEX idx_ma_department ON maintenance_assets(department);

CREATE INDEX idx_mr_department ON maintenance_records(department);

CREATE INDEX idx_mr_problem_date ON maintenance_records(problem_date);

CREATE INDEX idx_mr_status ON maintenance_records(status);

CREATE INDEX idx_notif_user_unread ON notifications (user_id, is_read, created_at);

CREATE INDEX idx_payments_idx_pay_date ON payments (date);

CREATE INDEX idx_payments_idx_pay_party ON payments (party_type, party_id);

CREATE INDEX idx_pd_payroll ON payroll_deductions (payroll_id);

CREATE INDEX idx_pd_source ON payroll_deductions (source_type, source_id);

CREATE INDEX idx_pp_payroll ON payroll_payments (payroll_id);

CREATE INDEX idx_production_orders_idx_po_status ON production_orders (status);

CREATE INDEX idx_products_idx_prod_kind ON products (kind);

CREATE INDEX idx_products_idx_prod_name ON products (name);

CREATE INDEX idx_purchase_invoices_idx_pi_date ON purchase_invoices (date);

CREATE INDEX idx_purchase_invoices_idx_pi_status ON purchase_invoices (status);

CREATE INDEX idx_receipts_idx_rcpt_cust ON receipts (customer_id, date);

CREATE INDEX idx_rm_usage_date ON rm_daily_usage (usage_date, product_id, warehouse_id);

CREATE INDEX idx_scol_sm ON salesman_collections (salesman_id, date);

CREATE INDEX idx_se_sm ON salesman_expenses (salesman_id, date);

CREATE INDEX idx_sett_sm ON salesman_settlements (salesman_id, date);

CREATE INDEX idx_smp_sm ON salesman_payments (salesman_id, date);

CREATE INDEX idx_smr_salesman ON salesman_ledger (salesman_id, date);

CREATE INDEX idx_spa_sm ON salesman_payment_allocations (salesman_id, issue_id);

CREATE INDEX idx_ssa_sm ON salesman_salary_advances (salesman_id, date);

CREATE INDEX idx_ssp_sm ON salesman_salary_payments (salesman_id, date);

CREATE INDEX idx_staff_leaves_emp ON staff_leaves (employee_id, start_date);

CREATE INDEX idx_stock_adjustments_idx_sa_status ON stock_adjustments (status);

CREATE INDEX idx_stock_cost_layers_idx_layer ON stock_cost_layers (product_id, warehouse_id, created_at, id);

CREATE INDEX idx_stock_movements_idx_mv_prod ON stock_movements (product_id, warehouse_id, created_at);

CREATE INDEX idx_stock_movements_idx_mv_ref ON stock_movements (ref_type, ref_id);

CREATE INDEX idx_stock_movements_idx_mv_type ON stock_movements (type);

CREATE INDEX idx_warehouses_idx_wh_branch ON warehouses (branch_id);

CREATE UNIQUE INDEX uq_approval_ref ON approval_requests (reference_type, reference_id, request_type);

CREATE UNIQUE INDEX uq_attendance_uq_att ON attendance (employee_id, date);

CREATE UNIQUE INDEX uq_loc_install ON loan_installments (loan_id, installment_no);

CREATE UNIQUE INDEX uq_payroll_uq_payroll ON payroll (employee_id, period_start, period_end);

CREATE UNIQUE INDEX uq_sa_emp_date ON staff_attendance (employee_id, date) WHERE salesman_id IS NULL;

CREATE UNIQUE INDEX uq_sa_sm_date ON staff_attendance (salesman_id, date) WHERE employee_id IS NULL;

CREATE UNIQUE INDEX uq_salesman_settlements_uq_settle ON salesman_settlements (salesman_id, date);

CREATE UNIQUE INDEX uq_smr_doc ON salesman_ledger (doc_type, doc_id);

CREATE UNIQUE INDEX uq_voucher_sequences_uq_seq ON voucher_sequences (fiscal_year_id, voucher_type);

