-- ============================================================
-- JUSTI NUMBER ONE ERP — Chishti Tobacco Company
-- MySQL / MariaDB schema v1.0
-- Engine: InnoDB, Charset: utf8mb4
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Company & organisation ----------
CREATE TABLE IF NOT EXISTS company_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'warehouse',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wh_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  INDEX idx_wh_branch (branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fiscal_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fiscal_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fiscal_year_id INT NOT NULL,
  name VARCHAR(30) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  CONSTRAINT fk_fp_fy FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE,
  INDEX idx_fp_dates (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voucher_sequences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fiscal_year_id INT NOT NULL,
  voucher_type VARCHAR(10) NOT NULL,
  current_no INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_seq (fiscal_year_id, voucher_type),
  CONSTRAINT fk_vs_fy FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Security ----------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  branch_id INT NULL,
  salesman_id INT NULL,
  preferred_language VARCHAR(10) NOT NULL DEFAULT 'en',
  deactivated_at DATETIME NULL,
  deactivated_by INT NULL,
  notify_sound TINYINT(1) NOT NULL DEFAULT 0,
  notify_desktop TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  force_password_change TINYINT(1) NOT NULL DEFAULT 0,
  province_id INT NULL,
  city_id INT NULL,
  area_id INT NULL,
  temp_password VARCHAR(100) DEFAULT NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  module VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_ur_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_r FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_r FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_p FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(60) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lh_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(60) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_la (ip, username, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(60) NOT NULL,
  entity_id VARCHAR(60) NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entity (entity, entity_id),
  INDEX idx_audit_user (user_id, created_at),
  INDEX idx_audit_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  idem_key VARCHAR(64) NOT NULL UNIQUE,
  user_id INT NULL,
  route VARCHAR(120) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity VARCHAR(60) NOT NULL,
  entity_id VARCHAR(60) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size_bytes INT NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_att (entity, entity_id)
) ENGINE=InnoDB;

-- ---------- Master data ----------
CREATE TABLE IF NOT EXISTS product_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'finished',
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_packaging (
  product_id INT PRIMARY KEY,
  pack_pieces DECIMAL(10,3) NULL,
  bundle_packs DECIMAL(10,3) NULL,
  packet_bundles DECIMAL(10,3) NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_pack_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  short VARCHAR(10) NOT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  unit_id INT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'finished',
  pack_size VARCHAR(60) NULL,
  purchase_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  issue_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(18,3) NOT NULL DEFAULT 0,
  has_expiry TINYINT(1) NOT NULL DEFAULT 0,
  photo_path VARCHAR(255) NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  INDEX idx_prod_kind (kind),
  INDEX idx_prod_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pk_provinces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(70) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pk_cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  province_id INT NOT NULL,
  name VARCHAR(90) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_pkcity (province_id, name),
  CONSTRAINT fk_pkcity_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  province_id INT NULL,
  city_id INT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_area_city (city_id, name),
  CONSTRAINT fk_area_prov FOREIGN KEY (province_id) REFERENCES pk_provinces(id) ON DELETE SET NULL,
  CONSTRAINT fk_area_city FOREIGN KEY (city_id) REFERENCES pk_cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  alt_phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  area VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  province_id INT NULL,
  city_id INT NULL,
  area_id INT NULL,
  deactivated_at DATETIME NULL,
  deactivated_by INT NULL,
  ntn VARCHAR(40) NULL,
  credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_type VARCHAR(6) NOT NULL DEFAULT 'debit',
  salesman_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cust_salesman (salesman_id),
  INDEX idx_cust_area (area)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesmen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  cnic VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  area VARCHAR(100) NULL,
  commission_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  province_id INT NULL,
  city_id INT NULL,
  area_id INT NULL,
  photo_path VARCHAR(255) NULL,
  cnic_front_path VARCHAR(255) NULL,
  cnic_back_path VARCHAR(255) NULL,
  deactivated_at DATETIME NULL,
  deactivated_by INT NULL,
  warehouse_id INT NULL,
  salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  join_date DATE NULL,
  vehicle_type VARCHAR(60) NULL,
  vehicle_reg VARCHAR(60) NULL,
  vehicle_note VARCHAR(255) NULL,
  left_date DATE DEFAULT NULL,
  departure_reason TEXT DEFAULT NULL,
  CONSTRAINT fk_sm_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE customers ADD CONSTRAINT fk_cust_salesman FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS salesman_customers (
  salesman_id INT NOT NULL,
  customer_id INT NOT NULL,
  PRIMARY KEY (salesman_id, customer_id),
  CONSTRAINT fk_sc_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_sc_c FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  ntn VARCHAR(40) NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  opening_type VARCHAR(6) NOT NULL DEFAULT 'credit',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  cnic VARCHAR(20) NULL,
  phone VARCHAR(40) NULL,
  designation VARCHAR(100) NULL,
  department VARCHAR(100) NULL,
  join_date DATE NULL,
  photo_path VARCHAR(255) NULL,
  cnic_front_path VARCHAR(255) NULL,
  cnic_back_path VARCHAR(255) NULL,
  address TEXT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'employee',
  province_id INT NULL,
  city_id INT NULL,
  area_id INT NULL,
  user_id INT NULL,
  deactivated_at DATETIME NULL,
  deactivated_by INT NULL,
  salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
  salary_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(18,2) NULL,
  payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
  salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  left_date DATE DEFAULT NULL,
  departure_reason TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'present',
  hours DECIMAL(10,2) NOT NULL DEFAULT 8.00,
  UNIQUE KEY uq_att (employee_id, date),
  CONSTRAINT fk_att_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_leaves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days INT NOT NULL DEFAULT 1,
  leave_type VARCHAR(40) NOT NULL DEFAULT 'casual',
  is_paid TINYINT(1) NOT NULL DEFAULT 1,
  reason VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_leaves_emp (employee_id, start_date),
  CONSTRAINT fk_staff_leave FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salary_changes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
  old_rate DECIMAL(18,2) NULL,
  new_rate DECIMAL(18,2) NOT NULL,
  effective_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salary_change FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_bonuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_eb_emp (employee_id, date),
  CONSTRAINT fk_eb_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  approved_by INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_edd_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NULL,
  salesman_id INT NULL,
  date DATE NOT NULL,
  status VARCHAR(8) NOT NULL,
  marked_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_sa_person_date (employee_id, salesman_id, date),
  CONSTRAINT fk_staffatt_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_staffatt_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT chk_sa_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_attendance_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NULL,
  salesman_id INT NULL,
  date DATE NOT NULL,
  old_status VARCHAR(8) NULL,
  new_status VARCHAR(8) NULL,
  changed_by INT NULL,
  note VARCHAR(255) NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_staffatt_audit_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_staffatt_audit_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT chk_saaud_person CHECK ((employee_id IS NOT NULL AND salesman_id IS NULL) OR (employee_id IS NULL AND salesman_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grinding_sends (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grinding_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grinding_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  send_id INT NOT NULL,
  return_date DATE NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gret_send FOREIGN KEY (send_id) REFERENCES grinding_sends(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_advances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  notes VARCHAR(255) NULL,
  payroll_id INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'approved',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  monthly_deduction DECIMAL(18,2) NULL,
  deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  account_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NULL,
  CONSTRAINT fk_adv_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'draft',
  journal_entry_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll (employee_id, period_start, period_end),
  CONSTRAINT fk_pay_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  `date` DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pp_payroll (payroll_id),
  CONSTRAINT fk_pp_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(30) NOT NULL UNIQUE,
  make VARCHAR(60) NULL,
  model VARCHAR(60) NULL,
  year INT NULL,
  driver_name VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  name VARCHAR(150) NULL,
  vehicle_type VARCHAR(30) NULL,
  purchase_date DATE NULL,
  photo_path VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  notes VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_fuel_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  date DATE NOT NULL,
  liters DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  mileage INT NULL,
  station VARCHAR(120) NULL,
  CONSTRAINT fk_fuel_v FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  created_by INT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  vendor VARCHAR(120) NULL,
  cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_maint_v FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  type VARCHAR(20) NOT NULL DEFAULT 'maintenance',
  attachment VARCHAR(255) NULL,
  created_by INT NULL
) ENGINE=InnoDB;

-- ---------- Factory Maintenance & Repairs ----------
CREATE TABLE IF NOT EXISTS maintenance_assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  asset_no VARCHAR(60) NULL UNIQUE,
  department VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ma_name (name),
  INDEX idx_ma_department (department),
  category VARCHAR(80) NOT NULL DEFAULT '',
  purchase_date DATE NULL,
  description TEXT NULL,
  photo_path VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_mr_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL,
  INDEX idx_mr_status (status),
  INDEX idx_mr_department (department),
  INDEX idx_mr_problem_date (problem_date),
  INDEX idx_mr_machine (machine_name(60))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maintenance_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_mp_rec FOREIGN KEY (maintenance_id) REFERENCES maintenance_records(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Inventory ----------

-- ---------- Inventory ----------
CREATE TABLE IF NOT EXISTS stock_balances (
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, warehouse_id),
  CONSTRAINT fk_sb_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_cost_layers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_remaining DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL,
  source_type VARCHAR(40) NULL,
  source_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_layer (product_id, warehouse_id, created_at, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  type VARCHAR(30) NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
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
  CONSTRAINT fk_sm_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
  INDEX idx_mv_prod (product_id, warehouse_id, created_at),
  INDEX idx_mv_ref (ref_type, ref_id),
  INDEX idx_mv_type (type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adjustment_no VARCHAR(30) NOT NULL UNIQUE,
  warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  created_by INT NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sa_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_sa_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_adjustment_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adjustment_id INT NOT NULL,
  product_id INT NOT NULL,
  direction VARCHAR(3) NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sai_a FOREIGN KEY (adjustment_id) REFERENCES stock_adjustments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sai_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------- Factory daily raw-material consumption (deducts RM stock; no double-deduct at production) ----------
CREATE TABLE IF NOT EXISTS factory_issues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  `date` DATE NOT NULL,
  batch_no VARCHAR(60) NULL,
  details VARCHAR(255) NULL,
  production_order_id INT NULL,
  issued_by INT NULL,
  approved_by INT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fi_po FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS factory_issue_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_id INT NOT NULL,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  INDEX idx_fii_p (product_id),
  INDEX idx_fii_i (issue_id),
  CONSTRAINT fk_fii_i FOREIGN KEY (issue_id) REFERENCES factory_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_fii_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rm_daily_usage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usage_date DATE NOT NULL,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  qty_kg DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',
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
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS bills_of_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  output_qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_bom_p FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bom_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bom_id INT NOT NULL,
  material_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  CONSTRAINT fk_boml_b FOREIGN KEY (bom_id) REFERENCES bills_of_materials(id) ON DELETE CASCADE,
  CONSTRAINT fk_boml_m FOREIGN KEY (material_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS production_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  product_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  bom_id INT NULL,
  qty_planned DECIMAL(18,3) NOT NULL,
  qty_produced DECIMAL(18,3) NOT NULL DEFAULT 0,
  waste_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  labor_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  overhead_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  materials_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  batch_no VARCHAR(60) NULL,
  expiry_date DATE NULL,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  CONSTRAINT fk_po_p FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_po_w FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_po_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_no VARCHAR(30) NOT NULL UNIQUE,
  `date` DATE NOT NULL,
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'posted',
  reversed_at DATETIME NULL,
  reversed_by INT NULL,
  reversal_reason VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dp_date (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS daily_production_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  batch_no VARCHAR(60) NULL,
  line_notes VARCHAR(255) NULL,
  INDEX idx_dpi_p (product_id, run_id),
  CONSTRAINT fk_dpi_run FOREIGN KEY (run_id) REFERENCES daily_production(id) ON DELETE CASCADE,
  CONSTRAINT fk_dpi_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_inputs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  production_order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pi_po FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------- Cash & bank ----------
CREATE TABLE IF NOT EXISTS cash_bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(10) NOT NULL,
  bank_name VARCHAR(120) NULL,
  account_no VARCHAR(60) NULL,
  iban VARCHAR(60) NULL,
  branch VARCHAR(120) NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  gl_account_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bank_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_no VARCHAR(30) NOT NULL UNIQUE,
  from_account_id INT NOT NULL,
  to_account_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bt_from FOREIGN KEY (from_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_bt_to FOREIGN KEY (to_account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cheques (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cheque_no VARCHAR(40) NOT NULL,
  bank_account_id INT NOT NULL,
  direction VARCHAR(8) NOT NULL DEFAULT 'received',
  party_type VARCHAR(10) NULL,
  party_name VARCHAR(150) NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  issue_date DATE NULL,
  due_date DATE NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_chq_ba FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
  INDEX idx_chq_status (status),
  INDEX idx_chq_due (due_date)
) ENGINE=InnoDB;

-- ---------- Purchasing ----------
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  order_date DATE NOT NULL,
  expected_date DATE NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pur_po_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  po_id INT NULL,
  supplier_invoice_no VARCHAR(60) NULL,
  date DATE NOT NULL,
  payment_type VARCHAR(6) NOT NULL DEFAULT 'credit',
  cash_account_id INT NULL,
  warehouse_id INT NOT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',
  payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid',
  amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pi_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_pi_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_pi_status (status),
  INDEX idx_pi_date (date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_short VARCHAR(10) NULL,
  qty_kg DECIMAL(18,3) NULL,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_bonus TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pur_i FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------- Sales ----------
CREATE TABLE IF NOT EXISTS sales_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sales_order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_soi_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_soi_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_si_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_si_cust (customer_id, date),
  INDEX idx_si_sm (salesman_id, date),
  INDEX idx_si_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_before_tax DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(18,4) NOT NULL DEFAULT 0,
  cost_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_bonus TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sii_i FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_sii_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(18,4) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sri_r FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_sri_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Salesman stock issues (goods on van/with salesman; NOT sales)
CREATE TABLE IF NOT EXISTS salesman_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salesman_issues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  from_warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',
  issue_type VARCHAR(10) NOT NULL DEFAULT 'transfer',
  total_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_bonus_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  bonus_reason VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  approved_by INT NULL,
  bonus_scheme VARCHAR(100) NULL,
  notes VARCHAR(255) NULL,
  related_issue_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_si2_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id),
  CONSTRAINT fk_si2_wh FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesman_issue_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  paid_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  bonus_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  issue_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  bonus_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  return_condition VARCHAR(10) NULL,
  CONSTRAINT fk_sii2_i FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_sii2_p FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesman_settlements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  sales_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  collected_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_counted DECIMAL(18,2) NOT NULL DEFAULT 0,
  expected_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reference VARCHAR(24) NULL,
  issued_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  additional_issued DECIMAL(18,2) NOT NULL DEFAULT 0,
  returns_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  collections_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  cash_received DECIMAL(18,2) NOT NULL DEFAULT 0,
  bank_received DECIMAL(18,2) NOT NULL DEFAULT 0,
  expenses_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  remaining_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  received_by INT NULL,
  INDEX idx_sett_sm (salesman_id, date),
  CONSTRAINT fk_set_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesman_collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  customer_id INT NULL,
  contact_name VARCHAR(120) NULL,
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
  INDEX idx_scol_sm (salesman_id, date),
  CONSTRAINT fk_scol_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_scol_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_scol_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_scol_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS salesman_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NULL UNIQUE,
  salesman_id INT NOT NULL,
  `date` DATE NOT NULL,
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
  INDEX idx_smp_sm (salesman_id, `date`),
  CONSTRAINT fk_smp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_smp_cash FOREIGN KEY (cash_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_bank FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_smp_user FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Recovery: payment-to-invoice allocation (one payment may settle many invoices) ----------
CREATE TABLE IF NOT EXISTS salesman_payment_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT NOT NULL,
  issue_id INT NOT NULL,
  salesman_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_spa_payment_issue (payment_id, issue_id),
  KEY idx_spa_sm (salesman_id, issue_id),
  CONSTRAINT fk_spa_payment FOREIGN KEY (payment_id) REFERENCES salesman_payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_spa_issue FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_spa_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salesman_salary_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salesman_id INT NOT NULL,
  `date` DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  `period` VARCHAR(10) NULL,                     -- YYYY-MM
  notes VARCHAR(255) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  requested_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ssp_sm (salesman_id, `date`),
  CONSTRAINT fk_ssp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_ssp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesman_salary_advances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salesman_id INT NOT NULL,
  `date` DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  account_id INT NOT NULL,
  method VARCHAR(6) NOT NULL DEFAULT 'cash',
  deducted DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ssa_sm (salesman_id, `date`),
  CONSTRAINT fk_ssa_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_ssa_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salesman_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salesman_id INT NOT NULL,
  `date` DATE NOT NULL,
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
  INDEX idx_se_sm (salesman_id, `date`),
  CONSTRAINT fk_se_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB;

-- ---------- Receipts & payments ----------
CREATE TABLE IF NOT EXISTS receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NOT NULL UNIQUE,
  party_type VARCHAR(10) NOT NULL DEFAULT 'customer',
  customer_id INT NULL,
  contact_name VARCHAR(120) NULL,
  account_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rcpt_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_rcpt_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  INDEX idx_rcpt_cust (customer_id, date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_no VARCHAR(30) NOT NULL UNIQUE,
  party_type VARCHAR(10) NOT NULL,
  party_id INT NULL,
  account_id INT NOT NULL,
  date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  journal_entry_id INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  INDEX idx_pay_party (party_type, party_id),
  INDEX idx_pay_date (date)
) ENGINE=InnoDB;

-- ---------- Expenses ----------
CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  gl_account_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS other_income (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_oi_date (date),
  INDEX idx_oi_status (status),
  CONSTRAINT fk_oi_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_exp_cat FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_exp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id) ON DELETE SET NULL,
  INDEX idx_exp_date (date),
  INDEX idx_exp_status (status),
  person_name VARCHAR(120) NULL,
  qty DECIMAL(18,3) NULL,
  notes TEXT NULL
) ENGINE=InnoDB;

-- ---------- Claims & bonuses ----------
CREATE TABLE IF NOT EXISTS claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_no VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT NULL,
  sales_invoice_id INT NULL,
  date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clm_c FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_clm_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bonuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bonus_no VARCHAR(30) NOT NULL UNIQUE,
  salesman_id INT NOT NULL,
  period_month CHAR(7) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bon_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id)
) ENGINE=InnoDB;

-- ---------- Accounting ----------
CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(12) NOT NULL,
  parent_id INT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  allow_posting TINYINT(1) NOT NULL DEFAULT 1,
  system_key VARCHAR(40) NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_coa_parent FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
  INDEX idx_coa_type (type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_no VARCHAR(30) NOT NULL UNIQUE,
  date DATE NOT NULL,
  fiscal_period_id INT NULL,
  voucher_type VARCHAR(10) NOT NULL DEFAULT 'JV',
  reference_type VARCHAR(40) NULL,
  reference_id INT NULL,
  memo VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'posted',
  reversal_of INT NULL,
  idem_key VARCHAR(64) NULL UNIQUE,
  created_by INT NULL,
  posted_by INT NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_je_fp FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL,
  CONSTRAINT fk_je_rev FOREIGN KEY (reversal_of) REFERENCES journal_entries(id) ON DELETE SET NULL,
  INDEX idx_je_date (date),
  INDEX idx_je_ref (reference_type, reference_id),
  INDEX idx_je_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_jl_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  INDEX idx_jl_account (account_id, entry_id),
  INDEX idx_jl_cust (customer_id),
  INDEX idx_jl_sup (supplier_id)
) ENGINE=InnoDB;


-- ---------- Approvals (outgoing payments require Owner/Admin approval) ----------
CREATE TABLE IF NOT EXISTS approval_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_type VARCHAR(20) NOT NULL,
  reference_type VARCHAR(40) NOT NULL,
  reference_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  requested_by INT NOT NULL,
  requested_at DATETIME NOT NULL,
  approver_id INT NULL,
  decided_at DATETIME NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  decision_note VARCHAR(255) NULL,
  created_at DATETIME NULL,
  UNIQUE KEY uq_approval (reference_type, reference_id, request_type),
  INDEX idx_approval_status (status, requested_at)
) ENGINE=InnoDB;

-- ---------- Salesman credit ledger (goods issued on credit at issue rate) ----------
CREATE TABLE IF NOT EXISTS salesman_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salesman_id INT NOT NULL,
  date DATE NOT NULL,
  doc_type VARCHAR(20) NOT NULL,
  doc_id INT NULL,
  doc_no VARCHAR(30) NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_smr (doc_type, doc_id),
  INDEX idx_smr_salesman (salesman_id, date),
  CONSTRAINT fk_smr_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Employee loans (separate from advances) ----------
CREATE TABLE IF NOT EXISTS employee_loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_no VARCHAR(30) NOT NULL UNIQUE,
  employee_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  installment_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
  installment_amount DECIMAL(18,2) NOT NULL,
  total_installments INT NOT NULL DEFAULT 1,
  recovered DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_installments INT NOT NULL DEFAULT 0,
  outstanding DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  account_id INT NULL,
  journal_entry_id INT NULL,
  requested_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_loan_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_installments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  installment_no INT NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'due',
  payroll_id INT NULL,
  paid_date DATE NULL,
  UNIQUE KEY uq_install (loan_id, installment_no),
  INDEX idx_install_status (loan_id, status),
  CONSTRAINT fk_inst_loan FOREIGN KEY (loan_id) REFERENCES employee_loans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Bonus / free stock schemes ----------
CREATE TABLE IF NOT EXISTS bonus_schemes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  kind VARCHAR(20) NOT NULL DEFAULT 'salesman',
  description VARCHAR(255) NULL,
  requires_approval TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Payroll deduction links (advances / loans per payslip) ----------
CREATE TABLE IF NOT EXISTS payroll_deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  source_type VARCHAR(10) NOT NULL,
  source_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  INDEX idx_pd_payroll (payroll_id),
  INDEX idx_pd_source (source_type, source_id)
) ENGINE=InnoDB;


-- ---------- Notifications (real-time alerts; bell + desktop popups + sound) ----------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(220) NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  dedupe_key VARCHAR(90) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_notif_user_dedupe (user_id, dedupe_key),
  INDEX idx_notif_user_unread (user_id, is_read, created_at)
) ENGINE=InnoDB;


-- ---------- Claim Goods (supplier claim receipts: accepted / damaged / rejected split) ----------
CREATE TABLE IF NOT EXISTS claim_goods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  purchase_invoice_id INT NULL,
  warehouse_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending_approval',
  payment_type VARCHAR(20) NOT NULL DEFAULT 'outstanding',
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
  CONSTRAINT fk_cg_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  INDEX idx_cg_status (status, date),
  INDEX idx_cg_supplier (supplier_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS claim_goods_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  claim_goods_id INT NOT NULL,
  product_id INT NOT NULL,
  good_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  claim_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_claim DECIMAL(18,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_cgi_cg FOREIGN KEY (claim_goods_id) REFERENCES claim_goods(id) ON DELETE CASCADE,
  CONSTRAINT fk_cgi_prod FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_cgi_cg (claim_goods_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS claim_goods_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_cgp_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  INDEX idx_cgp_cg (claim_goods_id)
) ENGINE=InnoDB;


-- ---------- Personal loans (separate, non-accounting) ----------

CREATE TABLE IF NOT EXISTS personal_loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_no VARCHAR(30) NOT NULL UNIQUE,
  person_name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  loan_date DATE NOT NULL,
  original_amount DECIMAL(18,2) NOT NULL,
  expected_date DATE NULL,
  description VARCHAR(255) NULL,
  notes TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  account_id INT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pl_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personal_loan_repayments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  repayment_date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  note VARCHAR(255) NULL,
  account_id INT NULL,
  recorded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_plr_loan FOREIGN KEY (loan_id) REFERENCES personal_loans(id) ON DELETE CASCADE,
  CONSTRAINT fk_plr_user FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_ce_date (expense_date),
  INDEX idx_ce_status (status),
  INDEX idx_ce_cat (category_id),
  CONSTRAINT fk_ce_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_ce_account FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
  CONSTRAINT fk_ce_linked FOREIGN KEY (linked_expense_id) REFERENCES expenses(id) ON DELETE SET NULL
) ENGINE=InnoDB;
-- ---------- Gifts ----------
CREATE TABLE IF NOT EXISTS gifts (
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
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

