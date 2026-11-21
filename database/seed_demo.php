<?php
/**
 * DEMO DATA (development / evaluation only!)
 * Inserts obviously-labelled DEMO customers, suppliers, products and a salesman
 * so the UI can be explored without entering real data.
 * NEVER run this on the production database.
 *
 * CLI: php database/seed_demo.php   (uses config/config.php or the SQLite dev DB)
 */
if (php_sapi_name() === 'cli') {
    require __DIR__ . '/../app/core/Database.php';
    require __DIR__ . '/../app/core/helpers.php';
    $cfgPath = __DIR__ . '/../config/config.php';
    $GLOBALS['CONFIG'] = file_exists($cfgPath) ? require $cfgPath : ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite:' . __DIR__ . '/../storage/dev.sqlite']];
    $pdo = Database::connect();
    seed_demo($pdo);
    echo "Demo data inserted (all records are prefixed DEMO-).\n";
}

function seed_demo(PDO $pdo): void {
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $insIgnore = fn(string $table, array $data) => 0; // placeholder, replaced below

    // Units lookup
    $packUnit = (int)$pdo->query("SELECT id FROM units WHERE short = 'CTN'")->fetchColumn();
    $kgUnit = (int)$pdo->query("SELECT id FROM units WHERE short = 'KG'")->fetchColumn();
    $pcsUnit = (int)$pdo->query("SELECT id FROM units WHERE short = 'PCS'")->fetchColumn();

    // Category
    $catFind = $pdo->prepare("SELECT id FROM product_categories WHERE name = ?");
    $catIns = $pdo->prepare('INSERT INTO product_categories (name, kind) VALUES (?,?)');
    foreach ([['DEMO — Cigarettes', 'finished'], ['DEMO — Raw Tobacco', 'raw'], ['DEMO — Packaging', 'packaging']] as [$n, $k]) {
        $catFind->execute([$n]);
        if (!$catFind->fetchColumn()) $catIns->execute([$n, $k]);
    }
    $catFin = (int)$pdo->query("SELECT id FROM product_categories WHERE name = 'DEMO — Cigarettes'")->fetchColumn();
    $catRaw = (int)$pdo->query("SELECT id FROM product_categories WHERE name = 'DEMO — Raw Tobacco'")->fetchColumn();
    $catPkg = (int)$pdo->query("SELECT id FROM product_categories WHERE name = 'DEMO — Packaging'")->fetchColumn();

    $pFind = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
    $pIns = $pdo->prepare('INSERT INTO products (sku, name, category_id, unit_id, kind, pack_size, purchase_price, sale_price, tax_rate, reorder_level, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)');
    $now = date('Y-m-d H:i:s');
    $products = [
        ['DEMO-FG-10', 'DEMO JUSTI Number One 10s (Carton)', $catFin, $packUnit, 'finished', '50 cartons/case', 15000, 22500, 18, 20, $now],
        ['DEMO-FG-20', 'DEMO JUSTI Number One 20s (Carton)', $catFin, $packUnit, 'finished', '50 cartons/case', 28000, 42000, 18, 20, $now],
        ['DEMO-RAW-VIR', 'DEMO Virginia Tobacco Leaf (kg)', $catRaw, $kgUnit, 'raw', '—', 850, 0, 0, 50, $now],
        ['DEMO-RAW-BUR', 'DEMO Burley Tobacco Leaf (kg)', $catRaw, $kgUnit, 'raw', '—', 780, 0, 0, 50, $now],
        ['DEMO-PKG-BO', 'DEMO Bonded Papers (ream)', $catPkg, $pcsUnit, 'packaging', '5000/ream', 3200, 0, 0, 100, $now],
        ['DEMO-PKG-FOIL', 'DEMO Inner Foil (roll)', $catPkg, $pcsUnit, 'packaging', '10,000/roll', 1450, 0, 0, 100, $now],
    ];
    foreach ($products as $p) { $pFind->execute([$p[0]]); if (!$pFind->fetchColumn()) $pIns->execute($p); }
    // Demo pricing rules: salesman issue rate below shop price, minimum sell price set
    $pdo->exec("UPDATE products SET issue_rate = 20000, min_price = 21000 WHERE sku = 'DEMO-FG-10'");
    $pdo->exec("UPDATE products SET issue_rate = 38000, min_price = 39000 WHERE sku = 'DEMO-FG-20'");

    // Suppliers
    $sFind = $pdo->prepare('SELECT id FROM suppliers WHERE code = ?');
    $sIns = $pdo->prepare('INSERT INTO suppliers (code, name, phone, city, opening_balance, opening_type, is_active, created_at) VALUES (?,?,?,?,?,?,1,?)');
    foreach ([['DEMO-SUP-001', 'DEMO Tobacco Leaf Traders', '0300-1112233', 'Lahore', 0, 'credit'], ['DEMO-SUP-002', 'DEMO Packaging Solutions', '0301-4445566', 'Karachi', 0, 'credit']] as $s) {
        $sFind->execute([$s[0]]); if (!$sFind->fetchColumn()) $sIns->execute([...$s, $now]);
    }

    // Salesman + warehouse
    $smFind = $pdo->prepare('SELECT id FROM salesmen WHERE code = ?');
    $smFind->execute(['DEMO-SM-001']);
    if (!$smFind->fetchColumn()) {
        $smId = $pdo->prepare('INSERT INTO salesmen (code, name, phone, area, commission_percent, salary, is_active, created_at) VALUES (?,?,?,?,?,?,1,?)');
        $smId->execute(['DEMO-SM-001', 'DEMO Salesman One', '0302-7778899', 'Main City', 2.5, 45000, $now]);
        $smId = (int)$pdo->lastInsertId();
        $wIns = $pdo->prepare('INSERT INTO warehouses (branch_id, code, name, type, is_default, is_active, created_at) VALUES ((SELECT id FROM branches WHERE code=\'MAIN\'), ?, ?, \'salesman\', 0, 1, ?)');
        $wCode = 'WH-DEMO-SM-001';
        $wFind = $pdo->prepare('SELECT id FROM warehouses WHERE code = ?');
        $wFind->execute([$wCode]);
        if (!$wFind->fetchColumn()) $wIns->execute([$wCode, 'DEMO Salesman One — Van', $now]);
        $whId = (int)$pdo->query("SELECT id FROM warehouses WHERE code = 'WH-DEMO-SM-001'")->fetchColumn();
        $pdo->prepare('UPDATE salesmen SET warehouse_id = ? WHERE id = ?')->execute([$whId, $smId]);
    }

    // Customers
    $cFind = $pdo->prepare('SELECT id FROM customers WHERE code = ?');
    $cIns = $pdo->prepare('INSERT INTO customers (code, name, phone, area, city, credit_limit, opening_balance, opening_type, salesman_id, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,(SELECT id FROM salesmen WHERE code=\'DEMO-SM-001\'),1,?)');
    foreach ([['DEMO-CUS-001', 'DEMO Super Traders Gawalmandi', '0303-1213141', 'Gawalmandi', 'Lahore', 500000, 0, 'debit'], ['DEMO-CUS-002', 'DEMO Al-Madina Store', '0304-1516171', 'Choona Mandi', 'Lahore', 300000, 0, 'debit'], ['DEMO-CUS-003', 'DEMO City Mart', '0305-1819202', 'Main Bazar', 'Lahore', 200000, 0, 'debit']] as $c) {
        $cFind->execute([$c[0]]); if (!$cFind->fetchColumn()) $cIns->execute([...$c, $now]);
    }
    $smId = (int)$pdo->query("SELECT id FROM salesmen WHERE code='DEMO-SM-001'")->fetchColumn();
    $scIns = $pdo->prepare($isSqlite ? 'INSERT OR IGNORE INTO salesman_customers (salesman_id, customer_id) VALUES (?, (SELECT id FROM customers WHERE code = ?))' : 'INSERT IGNORE INTO salesman_customers (salesman_id, customer_id) VALUES (?, (SELECT id FROM customers WHERE code = ?))');
    foreach (['DEMO-CUS-001', 'DEMO-CUS-002', 'DEMO-CUS-003'] as $c) $scIns->execute([$smId, $c]);

    // Opening stock for demo finished goods — receive via the real engine so journals and balances stay consistent
    $whMain = (int)$pdo->query("SELECT id FROM warehouses WHERE code = 'WH-MAIN'")->fetchColumn();
    $fg10 = (int)$pdo->query("SELECT id FROM products WHERE sku = 'DEMO-FG-10'")->fetchColumn();
    if (!class_exists('Inventory')) { require_once __DIR__ . '/../app/services/Inventory.php'; }
    if (!class_exists('Accounting')) { require_once __DIR__ . '/../app/services/Accounting.php'; }
    if ((int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'demo_seed'")->fetchColumn() === 0) {
        $total = 0.0;
        foreach ([[40, 15000], [30, 15500]] as [$qtyPart, $cost]) {
            // Direct SQL receive to avoid double-calling inside transactions started by callers
            $mv = $pdo->prepare('INSERT INTO stock_movements (product_id, warehouse_id, type, qty, unit_cost, total_cost, balance_qty, ref_type, notes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $prev = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = $fg10 AND warehouse_id = $whMain")->fetchColumn();
            $mv->execute([$fg10, $whMain, 'opening', $qtyPart, $cost, round($qtyPart * $cost, 2), $prev + $qtyPart, 'demo_seed', 'DEMO opening stock', date('Y-m-d H:i:s')]);
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sb = $pdo->prepare($isSqlite
                ? 'INSERT INTO stock_balances (product_id, warehouse_id, qty, avg_cost) VALUES (?,?,?,?) ON CONFLICT(product_id, warehouse_id) DO UPDATE SET avg_cost = ((stock_balances.avg_cost * stock_balances.qty) + (excluded.avg_cost * excluded.qty)) / (stock_balances.qty + excluded.qty), qty = stock_balances.qty + excluded.qty'
                : 'INSERT INTO stock_balances (product_id, warehouse_id, qty, avg_cost) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE avg_cost = ((avg_cost * qty) + (VALUES(avg_cost) * VALUES(qty))) / (qty + VALUES(qty)), qty = qty + VALUES(qty)');
            $sb->execute([$fg10, $whMain, $qtyPart, $cost]);
            $total += $qtyPart * $cost;
        }
        // Balance the opening stock in the GL: Dr Inventory / Cr Owner Capital
        $invAcc = $pdo->query("SELECT id FROM chart_of_accounts WHERE system_key='INVENTORY'")->fetchColumn();
        $capAcc = $pdo->query("SELECT id FROM chart_of_accounts WHERE system_key='CAPITAL'")->fetchColumn();
        $pdo->prepare('INSERT INTO journal_entries (entry_no, date, fiscal_period_id, voucher_type, reference_type, reference_id, memo, status, created_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute(['OB-DEMO-00001', date('Y-m-d'), null, 'OB', 'demo_seed', null, 'DEMO opening stock — DEMO JUSTI Number One 10s', 'posted', date('Y-m-d H:i:s')]);
        $entryId = (int)$pdo->lastInsertId();
        $ld = $pdo->prepare('INSERT INTO journal_lines (entry_id, account_id, debit, credit, memo) VALUES (?,?,?,?,?)');
        $ld->execute([$entryId, $invAcc, $total, 0, 'DEMO opening stock value']);
        $ld->execute([$entryId, $capAcc, 0, $total, 'DEMO opening stock — contra']);
    }
    echo "Demo products, suppliers, salesman, customers and opening stock ready.\n";
}
