<?php
$files = [
    __DIR__ . '/../app/views/personal_loans/index.php',
    __DIR__ . '/../app/views/warehouse/index.php'
];

$replacements = [
    "Account (اکاؤنٹ)" => "Account",
    "Given Amount (رقم دی)" => "Given Amount",
    "Repaid (واپس کی)" => "Repaid Amount",
    "Repay (رقم واپس لیں)" => "Repay",
    "Repayment (رقم واپسی)" => "Repayment",
    "Repayment Date (واپسی کی تاریخ)" => "Repayment Date",
    "Amount Received (کتنے پیسے واپس کیے)" => "Amount Received",
    "Deposit To Account (کس بینک / کیش اکاؤنٹ میں جمع ہوئے)" => "Deposit To Account",
    "Payment Note (نوٹ / تفصیل)" => "Payment Note",
    "Save Repayment (رقم وصول کریں)" => "Save Repayment",
    "Product Name (فیکٹری پروڈکٹ کا نام)" => "Product Name",
    "In Stock Quantity (موجودہ اسٹاک)" => "In Stock Quantity",
    "Factory Produced (کل پیداوار)" => "Factory Produced",
    "خام مال (KG / TON)" => "Raw Material (KG / TON)",
    "اور فیکٹری سے تیار شدہ پروڈکٹس (Units)" => "and Finished Factory Products (Units)"
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    file_put_contents($file, $content);
    echo "Cleaned " . basename($file) . "\n";
}
