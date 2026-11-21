<?php
$file = __DIR__ . '/../app/views/production/run.php';
$content = file_get_contents($file);

// Replace mixed titles and labels with pure clean translatable keys
$replacements = [
    "__('Daily Production Entry (روزانہ پروڈکشن)')" => "__('Daily Production Entry')",
    "__('تیار مال اسٹاک میں شامل ہوگا اور را مٹیریل کا اسٹاک خود بخود کٹے گا')" => "__('Finished goods will be added to stock and raw materials deducted via BOM')",
    "__('پروڈکشن تاریخ / Date')" => "__('Production Date')",
    "__('نوٹ / Notes (اختیاری)')" => "__('Notes (optional)')",
    "__('مثلاً: مارننگ شفٹ، مشین نمبر 1، بیچ وغیرہ')" => "__('e.g. morning shift, machine 1, batch')",
    "📦 1. تیار پروڈکٹس (Finished Goods to Produce)" => "📦 1. <?= __('Finished Goods to Produce') ?>",
    "__('پیکٹ / کارٹن کی تعداد درج کریں')" => "__('Enter produced quantity in packets / cartons')",
    "__('تیار پروڈکٹ')" => "__('Finished Product')",
    "__('تعداد (Packets)')" => "__('Quantity (Packets)')",
    "__('بیچ نمبر (Batch)')" => "__('Batch Number')",
    "__('لائن نوٹ')" => "__('Line Note')",
    "__('Add Product Line (پروڈکٹ لائن شامل کریں)')" => "__('Add Product Line')",
    "__('کل تیار مال:')" => "__('Total produced:')",
    "⚡ 2. را مٹیریل کٹائی (Raw Material Consumption / Auto-Deduct)" => "⚡ 2. <?= __('Raw Material Consumption / Auto-Deduct') ?>",
    "__('پروڈکشن کے ساتھ ہی متعلقہ را مٹیریل کا اسٹاک کٹ جائے گا')" => "__('Stock of consumed raw materials will be deducted automatically')",
    "__('BOM کے مطابق خود بخود کاٹیں (Auto-Deduct via BOM)')" => "__('Auto-Deduct from stock via BOM')",
    "📋 <?= __('خودبخود متوقع را مٹیریل کا خرچ (Calculated Raw Material Requirement):') ?>" => "📋 <?= __('Calculated Raw Material Requirement:') ?>",
    "__('اوپر پروڈکٹ اور مقدار منتخب کریں، را مٹیریل کا حساب یہاں خودبخود آ جائے گا۔')" => "__('Select product and quantity above to calculate raw material consumption.')",
    "➕ <?= __('اضافی یا الگ را مٹیریل درج کرنا ہو تو یہاں کلک کریں (Manual RM Lines)') ?>" => "➕ <?= __('Click here to add extra or manual raw materials') ?>",
    "__('را مٹیریل')" => "__('Raw Material')",
    "__('مقدار (KG)')" => "__('Quantity (KG)')",
    "__('گودام')" => "__('Warehouse')",
    "__('نوٹ')" => "__('Note')",
    "__('اضافی را مٹیریل شامل کریں')" => "__('Add Extra Raw Material')",
    "💾 <?= __('محفوظ کریں (Save Production & Deduct RM)') ?>" => "💾 <?= __('Save Production & Deduct RM') ?>",
    "__('— پروڈکٹ منتخب کریں —')" => "__('— Select Product —')",
    "__('— را مٹیریل منتخب کریں —')" => "__('— Select Raw Material —')",
    "__('نوٹ (اختیاری)')" => "__('optional note')",
    " کٹے گا</li>'" => " ' + '<?= __('deducted') ?>' + '</li>'",
];

foreach ($replacements as $find => $repl) {
    $content = str_replace($find, $repl, $content);
}

file_put_contents($file, $content);
echo "production/run.php cleaned up with pure i18n keys\n";
