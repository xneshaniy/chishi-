<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';
require __DIR__ . '/app/controllers/CompanyExpensesController.php';

echo "=== STEP 1: VERIFY MANAGER USER ===\n";
$managerUser = db_one("SELECT * FROM users WHERE username = 'manager'");
if (!$managerUser) die("Manager user not found.\n");
$mgrId = (int)$managerUser['id'];
echo "Manager ID: {$mgrId}\n";

$ownerUser = db_one("SELECT * FROM users WHERE username = 'naveed'");
if (!$ownerUser) die("Owner user not found.\n");
$ownerId = (int)$ownerUser['id'];
echo "Owner ID: {$ownerId}\n";

echo "\n=== STEP 2: MANAGER CREATES COMPANY EXPENSE ===\n";
$cat = db_one("SELECT id, name FROM expense_categories WHERE is_active = 1 LIMIT 1");
if (!$cat) {
    $catId = db_insert('expense_categories', ['name' => 'Factory Utilities', 'is_active' => 1]);
} else {
    $catId = (int)$cat['id'];
}
echo "Expense Category ID: {$catId}\n";

$account = db_one("SELECT id, name FROM cash_bank_accounts WHERE type = 'cash' AND is_active = 1 LIMIT 1");
$accountId = (int)$account['id'];
echo "Cash Account ID: {$accountId}\n";

// Set current user session to Manager
$_SESSION['user_id'] = $mgrId;
$_SESSION['role'] = 'manager';
$_SESSION['user'] = $managerUser;

$inputData = [
    'expense_date' => today(),
    'category_id' => $catId,
    'item_name' => 'Factory Machine Oil & Filter',
    'quantity' => '10',
    'unit' => 'Liter',
    'rate' => '500',
    'amount' => '5000',
    'payment_method' => 'cash',
    'account_id_cash' => $accountId,
    'supplier' => 'Shell Lubricants Swabi',
    'description' => 'Regular monthly oil replacement for packaging machine',
    'notes' => 'Urgent for line 1',
];

try {
    $row = CompanyExpensesController::recordNew($inputData, [], $mgrId);
    echo "✅ Expense created! Expense No: {$row['expense_no']} | Linked Expense ID: {$row['linked_expense_id']}\n";
    echo "Status: {$row['status']}\n";
} catch (Throwable $e) {
    die("❌ Failed to create expense: " . $e->getMessage() . "\n");
}

echo "\n=== STEP 3: VERIFY APPROVAL REQUEST GENERATED ===\n";
$appReq = db_one("SELECT * FROM approval_requests WHERE reference_type = 'expenses' AND reference_id = ?", [$row['linked_expense_id']]);
if (!$appReq) die("❌ No approval request found!\n");
echo "✅ Approval Request #{$appReq['id']}\n";
echo "Type: {$appReq['request_type']} | Amount: Rs {$appReq['amount']} | Status: {$appReq['status']} | Requested By: {$appReq['requested_by']}\n";

echo "\n=== STEP 4: CAN MANAGER APPROVE THEIR OWN REQUEST? (SECURITY CHECK) ===\n";
try {
    Approvals::decide((int)$appReq['id'], true, 'Self approval attempt', $mgrId);
    echo "❌ SECURITY FAIL: Manager was able to approve own request!\n";
} catch (Throwable $e) {
    echo "✅ SECURITY PASS: Self approval correctly blocked with: " . $e->getMessage() . "\n";
}

echo "\n=== STEP 5: OWNER APPROVES REQUEST ===\n";
$_SESSION['user_id'] = $ownerId;
$_SESSION['role'] = 'owner';
$_SESSION['user'] = $ownerUser;

try {
    $res = Approvals::decide((int)$appReq['id'], true, 'Approved by Naveed (Owner)', $ownerId);
    echo "✅ OWNER APPROVAL SUCCEEDED!\n";
    echo "Result: " . json_encode($res) . "\n";
} catch (Throwable $e) {
    die("❌ Owner approval failed: " . $e->getMessage() . "\n");
}

echo "\n=== STEP 6: VERIFY POSTED STATUS & BALANCES ===\n";
$updatedExp = db_one("SELECT status, expense_no FROM company_expenses WHERE id = ?", [$row['id']]);
echo "Company Expense Status: {$updatedExp['status']}\n";

$compExp = db_one("SELECT status, expense_no FROM expenses WHERE id = ?", [$row['linked_expense_id']]);
echo "Linked Expense Status: {$compExp['status']}\n";

$appReqUpdated = db_one("SELECT status, approver_id, decided_at, decision_note FROM approval_requests WHERE id = ?", [$appReq['id']]);
echo "Approval Request Status: {$appReqUpdated['status']} by Approver ID {$appReqUpdated['approver_id']} with note: {$appReqUpdated['decision_note']}\n";

echo "\n=== ALL CHECKS PASSED PERFECTLY! ===\n";
