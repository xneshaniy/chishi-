<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PurchaseController.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

register_shutdown_function(function() {
    $flashes = flash_get();
    if ($flashes) {
        echo "Flashes:\n";
        print_r($flashes);
    }
    $inv = db_one("SELECT id, invoice_no, total, amount_paid, payment_status FROM purchase_invoices ORDER BY id DESC LIMIT 1");
    echo "Latest invoice after shutdown: " . json_encode($inv) . "\n";
});

$rawProd = db_one("SELECT id, name FROM products WHERE is_active = 1 AND kind IN ('raw','packaging') ORDER BY id ASC LIMIT 1");
$supplier = db_one("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
$warehouse = db_one("SELECT id, name FROM warehouses WHERE type <> 'salesman' ORDER BY id ASC LIMIT 1");
$account = db_one("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 LIMIT 1");

$_SESSION['csrf'] = 'test-token-123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf' => 'test-token-123',
    'idem_key' => bin2hex(random_bytes(16)),
    'is_raw' => '1',
    'date' => date('Y-m-d'),
    'supplier_id' => (string)$supplier['id'],
    'warehouse_id' => (string)$warehouse['id'],
    'cash_account_id' => (string)$account['id'],
    'amount_paid' => '25000',
    'items' => [
        'product_id' => [(string)$rawProd['id']],
        'qty' => ['200'],
        'unit' => ['KG'],
        'cost' => [''],
        'line_total' => ['40000'],
        'tax_rate' => ['0.00'],
    ],
];

echo "Calling invoiceNew...\n";
PurchaseController::invoiceNew();
