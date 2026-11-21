<?php
/**
 * Base seed: roles, permissions, chart of accounts, fiscal year, units,
 * main branch/warehouse, default cash & bank accounts, expense categories.
 * Idempotent — safe to run more than once. Used by installer and tests.
 *
 * Usage (CLI): php database/seed.php   [requires env vars DB_*] or configured config.php
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require __DIR__ . '/../app/core/Database.php';
    require __DIR__ . '/../app/core/helpers.php';
    $GLOBALS['CONFIG'] = file_exists(__DIR__ . '/../config/config.php') ? require __DIR__ . '/../config/config.php' : require __DIR__ . '/../config/config.sample.php';
    seed_base(Database::connect());
    echo "Base seed complete.\n";
}

function seed_base(PDO $pdo): void {
    $tx = $pdo->inTransaction() ? null : true;
    if ($tx) $pdo->beginTransaction();
    try {
        seed_permissions($pdo);
        seed_roles($pdo);
        seed_accounts($pdo);
        seed_fiscal_year($pdo);
        seed_units($pdo);
        seed_branch_warehouse($pdo);
        seed_cash_bank($pdo);
        seed_expense_categories($pdo);
        seed_bonus_schemes($pdo);
        seed_locations($pdo);
        if ($tx) $pdo->commit();
    } catch (Throwable $e) {
        if ($tx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Seed Pakistan provinces and cities (idempotent). */
function seed_locations(PDO $pdo): void {
    $file = __DIR__ . '/pakistan_locations.php';
    if (!is_file($file)) return;
    $data = require $file;
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $insP = $isSqlite
        ? $pdo->prepare('INSERT OR IGNORE INTO pk_provinces (name, sort_order, is_active) VALUES (?,?,1)')
        : $pdo->prepare('INSERT IGNORE INTO pk_provinces (name, sort_order, is_active) VALUES (?,?,1)');
    $order = 0;
    foreach ($data as $province => $cities) {
        $insP->execute([$province, $order++]);
    }
    $provIds = [];
    foreach ($pdo->query('SELECT id, name FROM pk_provinces') as $r) $provIds[$r['name']] = (int)$r['id'];
    $insC = $isSqlite
        ? $pdo->prepare('INSERT OR IGNORE INTO pk_cities (province_id, name, is_active) VALUES (?,?,1)')
        : $pdo->prepare('INSERT IGNORE INTO pk_cities (province_id, name, is_active) VALUES (?,?,1)');
    foreach ($data as $province => $cities) {
        $pid = $provIds[$province] ?? null;
        if (!$pid) continue;
        foreach ($cities as $city) $insC->execute([$pid, $city]);
    }
}

function seed_permissions(PDO $pdo): void {
    $perms = [
        'dashboard.view' => 'dashboard', 'settings.view' => 'settings', 'settings.edit' => 'settings',
        'settings.branches' => 'settings', 'settings.warehouses' => 'settings', 'settings.fiscal' => 'settings',
        'users.view' => 'users', 'users.create' => 'users', 'users.edit' => 'users', 'audit.view' => 'audit',
        'products.view' => 'products', 'products.create' => 'products', 'products.edit' => 'products',
        'inventory.view' => 'inventory', 'inventory.move' => 'inventory', 'inventory.adjust' => 'inventory',
        'inventory.adjust_approve' => 'inventory', 'inventory.transfer' => 'inventory', 'inventory.count' => 'inventory',
    'rm.usage' => 'inventory',
    'personal_loans.view' => 'loans', 'personal_loans.manage' => 'loans',
    'company_expenses.view' => 'expenses', 'company_expenses.create' => 'expenses',
        'production.view' => 'production', 'production.create' => 'production', 'production.release' => 'production',
        'production.complete' => 'production', 'bom.manage' => 'production',
        'customers.view' => 'customers', 'customers.create' => 'customers', 'customers.edit' => 'customers', 'customers.export' => 'customers',
        'suppliers.view' => 'suppliers', 'suppliers.create' => 'suppliers', 'suppliers.edit' => 'suppliers',
        'salesmen.view' => 'salesmen', 'salesmen.create' => 'salesmen', 'salesmen.edit' => 'salesmen', 'salesmen.settle' => 'salesmen',
        'sales.view' => 'sales', 'sales.create' => 'sales', 'sales.edit' => 'sales', 'sales.return' => 'sales',
        'capital.view' => 'capital', 'income.view' => 'income', 'income.create' => 'income',
        'purchase.view' => 'purchase', 'purchase.create' => 'purchase', 'purchase.edit' => 'purchase', 'purchase.receive' => 'purchase',
        'accounts.view' => 'accounting', 'accounts.manage' => 'accounting', 'journal.view' => 'accounting',
        'journal.create' => 'accounting', 'journal.post' => 'accounting', 'journal.reverse' => 'accounting',
        'fiscal.close' => 'accounting', 'reports.view' => 'reports', 'reports.export' => 'reports',
        'cashbank.view' => 'cashbank', 'cashbank.receipt' => 'cashbank', 'cashbank.payment' => 'cashbank',
        'cashbank.transfer' => 'cashbank', 'cheques.manage' => 'cashbank', 'bank.reconcile' => 'cashbank',
        'expenses.view' => 'expenses', 'expenses.create' => 'expenses', 'expenses.approve' => 'expenses',
        'employees.view' => 'payroll', 'employees.create' => 'payroll', 'employees.edit' => 'payroll',
        'attendance.mark' => 'payroll', 'payroll.generate' => 'payroll', 'payroll.pay' => 'payroll',
        'claims.view' => 'claims', 'claims.create' => 'claims', 'claims.approve' => 'claims',
        'bonuses.view' => 'salesmen', 'bonuses.manage' => 'salesmen',
        'vehicles.view' => 'vehicles', 'vehicles.manage' => 'vehicles',
        'maintenance.view' => 'maintenance', 'maintenance.create' => 'maintenance', 'maintenance.approve' => 'maintenance',
        'backup.run' => 'backup',
        // ---------- v2: approvals, loans, bonus, language ----------
        'approvals.view' => 'approvals', 'approvals.decide' => 'approvals',
        'loans.view' => 'payroll', 'loans.manage' => 'payroll',
        'advances.request' => 'payroll', 'advances.approve' => 'payroll',
        'bonus.view' => 'sales', 'bonus.issue' => 'sales', 'bonus.manage' => 'sales',
        'reports.loans' => 'reports', 'reports.salesman_stock' => 'reports',
    ];
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $q = $isSqlite
        ? $pdo->prepare('INSERT OR IGNORE INTO permissions (code, module, description) VALUES (?,?,?)')
        : $pdo->prepare('INSERT IGNORE INTO permissions (code, module, description) VALUES (?,?,?)');
    foreach ($perms as $code => $module) $q->execute([$code, $module, ucfirst(str_replace(['.', '_'], [' ', ' '], $code))]);
}

function seed_roles(PDO $pdo): void {
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $ins = $pdo->prepare($isSqlite ? 'INSERT OR IGNORE INTO roles (code, name, description) VALUES (?,?,?)'
                                   : 'INSERT IGNORE INTO roles (code, name, description) VALUES (?,?,?)');
    $roles = [
        ['owner', 'Owner', 'Full access to everything'],
        ['admin', 'Administrator', 'Full access (day-to-day administration)'],
        ['accountant', 'Accountant', 'Accounting, reports, receipts/payments'],
        ['manager', 'Manager', 'Approvals, sales, purchases, stock'],
        ['storekeeper', 'Storekeeper', 'Inventory & warehouse operations'],
        ['salesman', 'Salesman', 'Own customers, sales, collections'],
    ];
    foreach ($roles as $r) $ins->execute($r);

    $roleIds = [];
    foreach ($pdo->query('SELECT code, id FROM roles') as $r) $roleIds[$r['code']] = (int)$r['id'];
    $permIds = [];
    foreach ($pdo->query('SELECT code, id FROM permissions') as $p) $permIds[$p['code']] = (int)$p['id'];
    $all = array_keys($permIds);
    $matrix = [
        'admin' => $all,
        'accountant' => ['dashboard.view','settings.view','reports.view','reports.export','accounts.view','accounts.manage',
            'journal.view','journal.create','journal.post','journal.reverse','sales.view','sales.create','sales.edit','sales.return','purchase.view','purchase.create','purchase.receive',
            'customers.view','customers.export','suppliers.view','salesmen.view','cashbank.view','cashbank.receipt',
            'cashbank.payment','cashbank.transfer','cheques.manage','bank.reconcile','expenses.view','expenses.create',
            'employees.view','payroll.generate','payroll.pay','claims.view','bonuses.view','inventory.view','products.view','audit.view',
            'approvals.view','loans.view','advances.request','bonus.view','maintenance.view','income.view','income.create'],
        'manager' => ['dashboard.view','settings.view','products.view','products.create','products.edit','inventory.view','inventory.move',
            'inventory.transfer','inventory.count','production.view','production.create','production.release','production.complete','bom.manage','rm.usage',
            'customers.view','customers.create','customers.edit','customers.export','suppliers.view','suppliers.create','suppliers.edit',
            'salesmen.view','salesmen.create','salesmen.edit','salesmen.settle','sales.view','sales.create','sales.edit','sales.return',
            'purchase.view','purchase.create','purchase.edit','purchase.receive','reports.view','reports.export',
            'cashbank.view','cashbank.receipt','cashbank.payment','cashbank.transfer','expenses.view','expenses.create',
            'employees.view','employees.create','employees.edit','attendance.mark','claims.view','claims.create','claims.approve','bonuses.view','bonuses.manage',
            'vehicles.view','vehicles.manage','accounts.view','journal.view','maintenance.view','maintenance.create',
            'approvals.view','loans.view','loans.manage','advances.request','bonus.view','bonus.issue','income.view','income.create'],
        'storekeeper' => ['dashboard.view','products.view','products.create','products.edit','inventory.view','inventory.move',
            'inventory.adjust','inventory.transfer','inventory.count','production.view','production.complete','purchase.view','purchase.receive',
            'purchase.create','sales.view','reports.view','vehicles.view','bonus.view','maintenance.view','maintenance.create'],
        'salesman' => ['dashboard.view','customers.view','sales.view','sales.create','claims.view','claims.create','cashbank.receipt','reports.view'],
    ];
    // owner gets everything via can() shortcut (roles.code=owner), still map all for completeness
    $matrix['owner'] = $all;
    $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
    $insRP = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
    foreach ($matrix as $roleCode => $codes) {
        $rid = $roleIds[$roleCode] ?? null;
        if (!$rid) continue;
        $del->execute([$rid]);
        foreach (array_unique($codes) as $c) if (isset($permIds[$c])) $insRP->execute([$rid, $permIds[$c]]);
    }
}

/** Chart of accounts with system keys used by the auto-posting engine. */
function seed_accounts(PDO $pdo): void {
    $coa = [
        // [code, name, type, parent_code, is_system, allow_posting, system_key]
        ['1000','Assets','asset',null,1,0,null],
        ['1100','Current Assets','asset','1000',1,0,null],
        ['1110','Cash in Hand','asset','1100',1,1,'CASH'],
        ['1120','Bank','asset','1100',1,1,'BANK'],
        ['1130','Inventory','asset','1100',1,1,'INVENTORY'],
        ['1140','Accounts Receivable','asset','1100',1,1,'AR'],
        ['1150','Input Sales Tax','asset','1100',1,1,'TAX_IN'],
        ['1160','Employee Advances','asset','1100',1,1,'EMP_ADV'],
        ['1170','Employee Loans','asset','1100',1,1,'EMP_LOAN'],
        ['1180','Salesman Receivable','asset','1100',1,1,'SM_RECEIVABLE'],
        ['1200','Fixed Assets','asset','1000',1,0,null],
        ['1210','Plant & Machinery','asset','1200',0,1,null],
        ['1220','Vehicles','asset','1200',0,1,null],
        ['1230','Furniture & Fixtures','asset','1200',0,1,null],
        ['2000','Liabilities','liability',null,1,0,null],
        ['2100','Current Liabilities','liability','2000',1,0,null],
        ['2110','Accounts Payable','liability','2100',1,1,'AP'],
        ['2120','Sales Tax Payable','liability','2100',1,1,'TAX_OUT'],
        ['2130','Salaries Payable','liability','2100',1,1,'SAL_PAYABLE'],
        ['2140','Accrued Expenses','liability','2100',0,1,null],
        ['2200','Long Term Liabilities','liability','2000',1,0,null],
        ['2210','Bank Loans','liability','2200',0,1,null],
        ['3000','Equity','equity',null,1,0,null],
        ['3100','Owner Capital','equity','3000',1,1,'CAPITAL'],
        ['3200','Owner Drawings','equity','3000',1,1,'DRAWINGS'],
        ['3300','Retained Earnings','equity','3000',1,1,'RE'],
        ['4000','Income','income',null,1,0,null],
        ['4100','Sales Revenue','income','4000',1,1,'SALES'],
        ['4200','Sales Returns','income','4000',1,1,'SALES_RETURNS'],
        ['4300','Other Income','income','4000',0,1,'OTHER_INCOME'],
        ['4400','Discount Received','income','4000',0,1,'DISC_RECEIVED'],
        ['5000','Expenses','expense',null,1,0,null],
        ['5100','Cost of Goods Sold','expense','5000',1,1,'COGS'],
        ['5200','Discount Allowed','expense','5000',0,1,'DISC_GIVEN'],
        ['5300','Free Goods / Promotion','expense','5000',1,1,'PROMO'],
        ['5400','Factory Expenses','expense','5000',1,0,'FACTORY_EXP'],
        ['5410','Electricity (Factory)','expense','5400',0,1,null],
        ['5420','Gas (Factory)','expense','5400',0,1,null],
        ['5430','Factory Rent','expense','5400',0,1,null],
        ['5440','Repairs & Maintenance','expense','5400',0,1,null],
        ['5450','Wages & Labor','expense','5400',0,1,null],
        ['5500','Sales & Distribution','expense','5000',1,0,'SALES_EXP'],
        ['5510','Fuel & Diesel','expense','5500',0,1,null],
        ['5520','Driver & Salesman Allowance','expense','5500',0,1,null],
        ['5530','Freight / Transport','expense','5500',0,1,null],
        ['5540','Vehicle Repairs','expense','5500',0,1,null],
        ['5550','Sales Commission','expense','5500',0,1,'COMMISSION'],
        ['5600','Administrative Expenses','expense','5000',1,0,'ADMIN_EXP'],
        ['5610','Salaries & Wages','expense','5600',0,1,null],
        ['5620','Office Expenses','expense','5600',0,1,null],
        ['5630','Telephone & Internet','expense','5600',0,1,null],
        ['5640','Utilities (Office)','expense','5600',0,1,null],
        ['5650','Professional Fees','expense','5600',0,1,null],
        ['5710','Salesman Expenses','expense','5700',0,1,'SM_EXP'],
['5720','Salesman Salary','expense','5700',0,1,'SM_SALARY'],
['5700','Other Expenses','expense','5000',0,1,'OTHER_EXP'],
    ];
    $codeToId = [];
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    // insert parents first (order above ensures parents precede children)
    $findSystem = $pdo->prepare('SELECT id FROM chart_of_accounts WHERE system_key = ?');
    $findCode = $pdo->prepare('SELECT id FROM chart_of_accounts WHERE code = ?');
    $ins = $pdo->prepare('INSERT INTO chart_of_accounts (code, name, type, parent_id, is_system, allow_posting, system_key, is_active) VALUES (?,?,?,?,?,?,?,1)');
    foreach ($coa as [$code, $name, $type, $parent, $isSystem, $allow, $key]) {
        // resolve parent
        $parentId = null;
        if ($parent && isset($codeToId[$parent])) $parentId = $codeToId[$parent];
        // exists already?
        $sel = $key ? $findSystem : $findCode;
        $sel->execute([$key ?: $code]);
        $id = $sel->fetchColumn();
        if ($id) { $codeToId[$code] = (int)$id; continue; }
        $ins->execute([$code, $name, $type, $parentId, $isSystem, $allow, $key]);
        $codeToId[$code] = (int)$pdo->lastInsertId();
    }
}

function seed_bonus_schemes(PDO $pdo): void {
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $ins = $pdo->prepare($isSqlite ? 'INSERT OR IGNORE INTO bonus_schemes (name, kind, description, requires_approval, is_active) VALUES (?,?,?,?,1)'
                                   : 'INSERT IGNORE INTO bonus_schemes (name, kind, description, requires_approval, is_active) VALUES (?,?,?,?,1)');
    // Reference configuration only — no transactions. Restricted schemes always need Owner/Admin approval.
    foreach ([
        ['Salesman Bonus', 'salesman', 'Free stock given to salesman over and above the paid quantity', 0],
        ['Customer 10+1 Free', 'customer', 'Buy 10 cartons, get 1 free', 0],
        ['Purchase Bonus', 'purchase', 'Free goods received from supplier with a purchase', 0],
        ['Festive Promotion', 'promotion', 'Seasonal free-goods promotion for shopkeepers', 1],
        ['Special Approved Issue', 'special', 'Any other bonus that requires Owner/Admin approval', 1],
    ] as $s) $ins->execute($s);
}

function seed_fiscal_year(PDO $pdo): void {
    $y = (int)date('Y');
    $m = (int)date('n');
    $startYear = $m >= 7 ? $y : $y - 1;       // Pakistani FY: July–June
    $name = 'FY ' . $startYear . '-' . substr((string)($startYear + 1), 2);
    $start = "$startYear-07-01";
    $end = ($startYear + 1) . '-06-30';
    $find = $pdo->prepare('SELECT id FROM fiscal_years WHERE name = ?');
    $find->execute([$name]);
    $fyId = $find->fetchColumn();
    if (!$fyId) {
        $pdo->prepare('INSERT INTO fiscal_years (name, start_date, end_date, is_active, is_closed) VALUES (?,?,?,1,0)')->execute([$name, $start, $end]);
        $fyId = (int)$pdo->lastInsertId();
        $names = ['Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun'];
        for ($i = 0; $i < 12; $i++) {
            $pm = 7 + $i; $py = $startYear;
            if ($pm > 12) { $pm -= 12; $py += 1; }
            $pStart = sprintf('%04d-%02d-01', $py, $pm);
            $pEnd = date('Y-m-t', strtotime($pStart));
            $pdo->prepare('INSERT INTO fiscal_periods (fiscal_year_id, name, start_date, end_date, status) VALUES (?,?,?,?,\'open\')')
                ->execute([$fyId, $names[$i] . ' ' . $py, $pStart, $pEnd]);
        }
    }
    $pdo->prepare('UPDATE fiscal_years SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END')->execute([$fyId]);
}

function seed_units(PDO $pdo): void {
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $find = $pdo->prepare('SELECT COUNT(*) FROM units WHERE short = ?');
    $ins = $pdo->prepare($isSqlite ? 'INSERT INTO units (name, short) VALUES (?,?)'
                                   : 'INSERT INTO units (name, short) VALUES (?,?)');
    foreach ([['Piece','PCS'],['Carton','CTN'],['Box','BOX'],['Kilogram','KG'],['Gram','GM'],['Litre','LTR'],['Metre','MTR'],['Ton','TON'],['Maund','MAN'],['Bag','BAG'],['Pair','PR'],['Packet','PKT'],['Bundle','BUNDLE']] as $u) { $find->execute([$u[1]]); if (!(int)$find->fetchColumn()) $ins->execute($u); }
}

function seed_branch_warehouse(PDO $pdo): void {
    $bFind = $pdo->prepare('SELECT id FROM branches WHERE code = ?');
    $bFind->execute(['MAIN']);
    $branchId = $bFind->fetchColumn();
    if (!$branchId) {
        $pdo->prepare("INSERT INTO branches (code, name, address, city, is_main, is_active) VALUES ('MAIN','Main Factory & Head Office','Chishti Tobacco Company, Main Factory','Pakistan',1,1)")->execute();
        $branchId = (int)$pdo->lastInsertId();
    }
    foreach ([['WH-MAIN','Main Warehouse','central',1],['WH-FACTORY','Factory Warehouse','factory',0]] as [$code,$name,$type,$def]) {
        $wFind = $pdo->prepare('SELECT id FROM warehouses WHERE code = ?');
        $wFind->execute([$code]);
        if (!$wFind->fetchColumn()) {
            $pdo->prepare('INSERT INTO warehouses (branch_id, code, name, type, is_default, is_active) VALUES (?,?,?,?,?,1)')->execute([$branchId, $code, $name, $type, $def]);
        }
    }
}

function seed_cash_bank(PDO $pdo): void {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM cash_bank_accounts')->fetchColumn();
    if ($count > 0) return;
    $cashGl = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE system_key='CASH'")->fetchColumn();
    $bankGl = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE system_key='BANK'")->fetchColumn();
    $pdo->prepare('INSERT INTO cash_bank_accounts (name, type, bank_name, opening_balance, gl_account_id, is_active) VALUES (?,?,?,?,?,1)')
        ->execute(['Cash in Hand', 'cash', null, 0, $cashGl]);
    $pdo->prepare('INSERT INTO cash_bank_accounts (name, type, bank_name, opening_balance, gl_account_id, is_active) VALUES (?,?,?,?,?,1)')
        ->execute(['Main Bank Account', 'bank', 'Main Bank', 0, $bankGl]);
}

function seed_expense_categories(PDO $pdo): void {
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $find = $pdo->prepare('SELECT id FROM expense_categories WHERE name = ?');
    $ins = $pdo->prepare('INSERT INTO expense_categories (name, gl_account_id, is_active) VALUES (?,?,1)');
    $map = [
        'Electricity (Factory)' => '5410', 'Gas (Factory)' => '5420', 'Factory Rent' => '5430',
        'Repairs & Maintenance' => '5440', 'Factory Maintenance & Repairs' => '5440',
        'Wages & Labor' => '5450', 'Fuel & Diesel' => '5510',
        'Driver & Salesman Allowance' => '5520', 'Freight / Transport' => '5530', 'Vehicle Repairs' => '5540',
        'Sales Commission' => '5550', 'Salaries & Wages' => '5610', 'Office Expenses' => '5620',
        'Telephone & Internet' => '5630', 'Utilities (Office)' => '5640', 'Professional Fees' => '5650',
        'Other Expenses' => '5700', 'Discount Allowed' => '5200',
'Salesman Expenses' => '5710', 'Salesman Salary' => '5720',
        // Company Expenses — factory & office operating expenses (mapped to closest GL)
        'Plastic Bags' => '5700', 'Printing & Printing Material' => '5700',
        'Electricity Bills' => '5640', 'Water Bills' => '5640', 'Gas Bills' => '5420',
        'Internet & Telephone Bills' => '5630',
        'Company Vehicle Petrol' => '5510', 'Company Vehicle Diesel' => '5510',
        'Office Supplies' => '5620', 'Factory Supplies' => '5700',
        'Packaging Material' => '5700', 'Cleaning Supplies' => '5700', 'Stationery' => '5620',
        'Loading & Unloading' => '5530', 'Transport Expenses' => '5530',
        'Repair & Service Expenses' => '5440', 'Other Company Expenses' => '5700',
    ];
    foreach ($map as $name => $code) {
        $find->execute([$name]);
        if ($find->fetchColumn()) continue;
        $gl = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE code = " . $pdo->quote($code))->fetchColumn();
        if ($gl) $ins->execute([$name, $gl]);
    }
}
