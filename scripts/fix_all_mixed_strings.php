<?php
// Dictionary of mixed strings to replace across all files
$replacements = [
    // Controllers
    "'Daily Cash Position (آج کا کیش پوزیشن)'" => "'Daily Cash Position'",
    "'Company Records (کمپنی ریکارڈ)'" => "'Company Records'",

    // Layout
    "Dedicated Records (ریکارڈ)" => "Dedicated Records",

    // Warehouse
    "Warehouse Stock Report (گودام اسٹاک رپورٹ)" => "Warehouse Stock Report",
    "Raw Material Stock (ہمارے پاس را مٹیریل کتنا ہے)" => "Raw Material Stock",
    "Factory Finished Products (جو فیکٹری سے بن کر آئے ہیں)" => "Factory Finished Products",
    "Factory Products In Stock (موجودہ فیکٹری اسٹاک)" => "Factory Products In Stock",
    "Total Tracked Products (کل اقسام)" => "Total Tracked Products",
    "Units (تعداد)" => "Units",
    "Items (اشیاء)" => "Items",
    "Status (حیثیت)" => "Status",
    "خام مال (KG / TON) اور فیکٹری سے تیار شدہ پروڈکٹس (Units) کا خالص فزیکل ریکارڈ۔" => "Pure physical stock tracking of raw materials and finished products.",

    // Purchase & Raw
    "ڈیلی یوز / Daily Usage" => "Daily Usage",
    "Add New Raw Material (نیا را میٹریل پروڈکٹ)" => "Add New Raw Material",
    "Products Supplied (فراہم کردہ را میٹریل)" => "Products Supplied",
    "ڈیلی یوز ایڈ کریں / Add Daily Usage" => "Add Daily Usage",
    "تاریخ / Date" => "Date",
    "را مٹیریل / Raw Material" => "Raw Material",
    "گودام / Warehouse" => "Warehouse",
    "نوٹ / Note" => "Note",
    "مقدار (KG)" => "Quantity (KG)",
    "تاریخ-رینج رپورٹ / Date-Range Usage Report" => "Date-Range Usage Report",
    "تاریخ-رینج رپورٹ / Date-Range Report" => "Date-Range Report",

    // Purchase Invoice New
    "Supplier (سپلائر)" => "Supplier",
    "Total Amount (ٹوٹل پرائس)" => "Total Amount",
    "Total Amount (ٹوٹل رقم)" => "Total Amount",
    "Amount Paid (کتنے پیسے دیے)" => "Amount Paid",
    "Remaining (باقی رقم)" => "Remaining Balance",
    "Save Raw Material Purchase (خریداری محفوظ کریں)" => "Save Raw Material Purchase",

    // Personal Loans (Dasti)
    "Loan Date (قرض کی تاریخ)" => "Loan Date",
    "Return Date (واپسی کی تاریخ)" => "Return Date",
    "Filter (فلٹر)" => "Filter",
    "Outstanding (کل بقایا)" => "Total Outstanding",
    "Total Loan Given (کل رقم دی گئی)" => "Total Loan Given",
    "Received (کل واپس ملی)" => "Total Repaid",
    "Overdue Loans (میعاد ختم)" => "Overdue Loans",
    "Dasti Register (قرض / دستی کھاتہ)" => "Personal Loans Register",
    "Person Name (شخص کا نام)" => "Person Name",
    "Expected Return Date (واپسی کی تاریخ)" => "Expected Return Date",
    "Expected Return Date (واپسی کی متوقع تاریخ)" => "Expected Return Date",
    "Loan Amount Given (دی گئی رقم - روپے)" => "Loan Amount Given",
    "Paid From Account (کس اکاؤنٹ سے پیسہ دیا گیا)" => "Paid From Account",
    "Reason (وجہ / تفصیل)" => "Reason / Details",
    "Notes (اضافی نوٹس)" => "Notes (Optional)",
    "Dasti (محفوظ کریں)" => "Save Loan Record",
    "Repayment (رقم واپسی ریکارڈ کریں)" => "Record Repayment",
    "Repayment date (واپسی کی تاریخ)" => "Repayment Date",
    "Amount received (کتنے پیسے واپس کیے)" => "Amount Received",
    "Deposit To Account (بینک یا کیش اکاؤنٹ)" => "Deposit To Account",
    "Payment note (نوٹ)" => "Payment Note",
    "Repayment History (رقم واپسی کا ریکارڈ)" => "Repayment History",

    // Records
    "Staff (ملازمین ریکارڈ)" => "Staff Records",
    "Other Master Archives (دیگر آرکائیوز)" => "Other Archives",
    "Left Date (روانگی کی تاریخ)" => "Left Date",
    "Total Sales (روپے)" => "Total Sales",
    "Dossier (کھاتہ)" => "Dossier",
    "Restore (بحال کریں)" => "Restore"
];

$files = [
    __DIR__ . '/../app/controllers/CashBankController.php',
    __DIR__ . '/../app/controllers/RecordsController.php',
    __DIR__ . '/../app/views/layout.php',
    __DIR__ . '/../app/views/warehouse/index.php',
    __DIR__ . '/../app/views/purchase/raw.php',
    __DIR__ . '/../app/views/purchase/raw_daily_usage_block.php',
    __DIR__ . '/../app/views/raw/usage.php',
    __DIR__ . '/../app/views/purchase/invoice_new.php',
    __DIR__ . '/../app/views/personal_loans/index.php',
    __DIR__ . '/../app/views/personal_loans/new.php',
    __DIR__ . '/../app/views/personal_loans/show.php',
    __DIR__ . '/../app/views/records/index.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $changed = false;
    foreach ($replacements as $search => $replace) {
        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents($file, $content);
        echo "Cleaned mixed strings in: " . basename($file) . "\n";
    }
}
