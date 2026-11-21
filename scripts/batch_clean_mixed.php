<?php
$files = [
    __DIR__ . '/../app/views/warehouse/index.php',
    __DIR__ . '/../app/views/purchase/raw.php',
    __DIR__ . '/../app/views/raw/usage.php',
    __DIR__ . '/../app/views/records/index.php',
    __DIR__ . '/../app/views/personal_loans/index.php',
    __DIR__ . '/../app/views/personal_loans/new.php',
    __DIR__ . '/../app/views/personal_loans/show.php',
    __DIR__ . '/../app/views/salesmen/profile.php',
    __DIR__ . '/../app/views/employees/profile.php',
    __DIR__ . '/../app/views/salesmen/index.php',
    __DIR__ . '/../app/views/employees/index.php',
    __DIR__ . '/../app/views/users/form.php',
    __DIR__ . '/../app/views/purchase/invoice_new.php'
];

$cleanDict = [
    // Warehouse
    'Warehouse (ویئر ہاؤس)' => 'Warehouse',
    'Warehouse (ویئر ہاؤس اسٹاک)' => 'Warehouse Stock',
    'Search (تلاش کریں)' => 'Search',
    'Raw Materials Stock (خام مال اسٹاک)' => 'Raw Materials Stock',
    'Factory Products in Stock (تیار فیکٹری مال)' => 'Factory Products in Stock',
    'Total Tracked Products (کل پروڈکٹس)' => 'Total Tracked Products',
    'Raw Material Stock (گودام میں خام مال)' => 'Raw Material Stock',
    'Factory Finished Products (فیکٹری کا تیار مال)' => 'Factory Finished Products',
    'Low Stock (کم اسٹاک)' => 'Low Stock',
    'In Stock (اسٹاک موجود)' => 'In Stock',
    'Empty (خالی)' => 'Empty',
    'TON (ٹن)' => 'TON',
    'KG (کلو)' => 'KG',
    'Packets (پیکٹ)' => 'Packets',
    'Status (حالت)' => 'Status',
    'Print Stock Report (پرنٹ رپورٹ)' => 'Print Stock Report',
    'Reorder Alert (وارننگ)' => 'Reorder Alert',
    'Normal (نارمل)' => 'Normal',
    'Actions (ایکشن)' => 'Actions',
    'Product (پروڈکٹ)' => 'Product',
    'Product / Material (پروڈکٹ)' => 'Product / Material',
    'Stock Value (مالیت)' => 'Stock Value',
    'Total Quantity (کل مقدار)' => 'Total Quantity',
    'Warehouse Name (گودام)' => 'Warehouse Name',
    
    // Purchase & Raw
    'Add New Purchase (خام مال خریداری)' => 'Add New Purchase',
    'Purchase Date (پرچیزنگ ڈیٹ)' => 'Purchase Date',
    'Paid From Account (رقم اکاؤنٹ)' => 'Paid From Account',
    'Raw Material Name (خام مال کا نام)' => 'Raw Material Name',
    'Add New Supplier (نیا سپلائر شامل کریں)' => 'Add New Supplier',
    'Assign to Supplier (سپلائر کو پروڈکٹ تفویض کریں)' => 'Assign to Supplier',
    'Select Supplier (سپلائر منتخب کریں)' => 'Select Supplier',
    'Select Products to Add (پروڈکٹس ایڈ کریں)' => 'Select Products to Add',
    'Daily Raw Material Usage (ڈیلی را مٹیریل یوز)' => 'Daily Raw Material Usage',
    'Save Products (محفوظ کریں)' => 'Save Products',
    'Save (محفوظ کریں)' => 'Save',
    'Cancel (کینسل)' => 'Cancel',
    'Confirm (تصدیق کریں)' => 'Confirm',
    'Close (بند کریں)' => 'Close',
    
    // Records
    'Company Records (کمپنی ریکارڈ)' => 'Company Records',
    'Departed Salesmen (سابقہ سیلزمین)' => 'Departed Salesmen',
    'Departed Staff (سابقہ ملازمین)' => 'Departed Staff',
    'Departed Salesmen (سیلزمین ریکارڈ)' => 'Departed Salesmen',
    'Departed Employees (ملازمین ریکارڈ)' => 'Departed Employees',
    'Records (سابقہ سیلزمین)' => 'Records (Archive)',
    'Records (سابقہ ملازمین)' => 'Records (Archive)',
    'Delete (ملازمت ختم)' => 'Mark Departed / Left',
    'Leaving Date (روانگی کی تاریخ)' => 'Leaving Date',
    'Leaving Reason (چھوڑنے کی وجہ)' => 'Leaving Reason',
    'Restore Staff (بحال کریں)' => 'Restore Staff',
    'Permanently Delete (مکمل ڈیلیٹ)' => 'Permanently Delete',
    
    // Loans
    'All Loans (تمام قرض / دستی)' => 'All Personal Loans (Dasti)',
    'Active (جاری)' => 'Active',
    'Partially Paid (جزوی وصول)' => 'Partially Paid',
    'Overdue (میعاد گزر چکی)' => 'Overdue',
    'Fully Paid (مکمل ادا شدہ)' => 'Fully Paid',
    'Dasti Record (نیا قرض / دستی اندراج)' => 'New Personal Loan Record',
    'Loan Details (قرض کی تفصیلات)' => 'Loan Details',
    'Person Full Name (شخص کا نام)' => 'Person Full Name',
    'Contact Phone (رابطہ نمبر)' => 'Contact Phone',
    'Loan Date (قرض دینے کی تاریخ)' => 'Loan Date',
    'Original Loan (دی گئی رقم)' => 'Original Loan Amount',
    'Total Repaid (کل واپس ملی)' => 'Total Repaid',
    'Remaining (بقایا رقم)' => 'Remaining Balance',
    'Expected Return (واپسی کی تاریخ)' => 'Expected Return Date',
    'Dasti (قرض / دستی)' => 'Dasti (Personal Loans)',
    'Custom Permissions (پرمیشنز)' => 'Custom Permissions'
];

foreach ($files as $filePath) {
    if (!file_exists($filePath)) continue;
    $content = file_get_contents($filePath);
    $changed = false;
    foreach ($cleanDict as $mixed => $pure) {
        if (strpos($content, $mixed) !== false) {
            $content = str_replace($mixed, $pure, $content);
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents($filePath, $content);
        echo "Cleaned: " . basename($filePath) . "\n";
    }
}
