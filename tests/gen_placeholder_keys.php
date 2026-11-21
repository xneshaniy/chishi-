<?php
/** Urdu + Roman Urdu for placeholder hints wrapped in __(). e.g. examples keep sample data (user-entered exception). */
$missing = [' Auto (e.g. BATCH-2026-001)',
];
$hand = [
    'Auto (e.g. BATCH-2026-001)' => ['خودکار (جوں کے BATCH-2026-001)', 'Auto (jaise ke BATCH-2026-001)'],
    'Department (optional)' => ['شعبہ (اختیاری)', 'Department (optional)'],
    'Describe the repair work done…' => ['مرمت کے کام کی وضاحت…', 'Marmat ke kaam ki tafseel…'],
    'Machine contains…' => ['مشین میں…', 'Machine mein…'],
    'Optional notes…' => ['اختیاری نوٹس…', 'Optional notes…'],
    'Search asset / department / location…' => ['تلاش کریں: اثاثہ / شعبہ / مقام…', 'Talaash karein: asset / department / location…'],
    'Search batch / details / issue no…' => ['تلاش کریں: بیچ / تفصیل / ایشو نمبر…', 'Talaash karein: batch / details / issue no…'],
    'Search machine / record / vendor…' => ['تلاش کریں: مشین / ریکارڈ / وینڈر…', 'Talaash karein: machine / record / vendor…'],
    'Search order / product…' => ['تلاش کریں: آرڈر / پروڈکٹ…', 'Talaash karein: order / product…'],
    'Search product / supplier / invoice…' => ['تلاش کریں: پروڈکٹ / سپلائر / انوائس…', 'Talaash karein: product / supplier / invoice…'],
    'Their bill no (optional)' => ['ان کا بل نمبر (اختیاری)', 'Unka bill number (optional)'],
    'What happened? e.g. Main bearing worn out, belt broken…' => ['کیا ہوا؟ جوں کے مین بیئرنگ گھس گئی، بیلٹ ٹوٹ گئی…', 'Kya hua? jaise ke main bearing ghis gayi, belt toot gayi…'],
];
$eg = [
    'e.g. JUSTI 10s production run',
    'e.g. Khan Engineering Works',
    'e.g. LEA-1234',
    'e.g. MAC-014',
    'e.g. Main bearing',
    'e.g. Main factory, Line 2',
    'e.g. Packing',
    'e.g. Rickshaw / Motorbike / Van',
    'e.g. company rickshaw',
    'e.g. damaged in transit; short-shipped cartons',
    'e.g. day shift',
    'e.g. day shift, night shift, rework',
];
foreach ($eg as $k) {
    $hand[$k] = ['جوں کے' . substr($k, 5), 'jaise ke ' . substr($k, 5)];
}
// accessibility / image labels
$hand += [
    'Company logo' => ['کمپنی لوگو', 'Company logo'],
    'Logo' => ['لوگو', 'Logo'],
    'Free/bonus goods' => ['مفت/بونس مال', 'Free/bonus goods'],
    'Main navigation' => ['مین نیویگیشن', 'Main Navigation'],
    'Toggle menu' => ['مینو کھولیں/بند کریں', 'Menu khol/band karein'],
    'Language' => ['زبان', 'Zubaan'],
    'English' => ['انگریزی', 'English'],
    'Roman Urdu' => ['رومن اردو', 'Roman Urdu'],
    'note (required to reject)' => ['نوٹ (مسترد کرنے کے لیے ضروری)', 'note (reject ke liye zaroori)'],
    'Approve? The document will be posted now.' => ['منظوری؟ دستاویز ابھی پوسٹ ہو جائے گی۔', 'Approve? Document abhi post ho jayega.'],
    'Reject without posting?' => ['بغیر پوسٹ کیے مسترد؟', 'Bina post kiye reject?'],
    "Close %s? All income/expense accounts roll into Retained Earnings." => ['بند کریں %s؟ تمام آمدنی اور خرچہ اکاؤنٹس Retained Earnings میں چلے جائیں گے۔', 'Band karein %s? Tamam aamdani aur kharchay accounts Retained Earnings mein chale jayenge.'],
    'Post a reversal of this journal? The original will be marked REVERSED and a mirror entry created.' => ['اس جرنل کا الٹ پوسٹ کریں؟ اصل کو REVERSED نشان زد کر دیا جائے گا اور ایک آئینہ اندراج بنے گا۔', 'Is journal ka ulta post karein? Original ko REVERSED mark kar diya jayega aur ek mirror entry banegi.'],
    'Reverse this expense?' => ['اس خرچے کو الٹیں؟', 'Is kharche ko ulta karein?'],
    'Reverse this maintenance record? The expense and its journal will be reversed.' => ['اس مینٹیننس ریکارڈ کو الٹیں؟ خرچہ اور اس کا جرنل الٹ ہو جائے گا۔', 'Is maintenance record ko ulta karein? Akhrajaat aur us ka journal ulta ho jayega.'],
    'Generate draft payroll for all active employees for %s -> %s?' => ['تمام فعال ملازمین کے لیے ڈرافٹ پے رول بنا دیں %s -> %s؟', 'Tamam active mulazmin ke liye draft payroll bana dein %s -> %s?'],
    'Reverse this payslip? The journal(s) will be reversed and deductions released to advances/loans.' => ['اس پے سلپ کو الٹیں؟ جرنل الٹ ہو جائے گا اور کٹوتیاں واپس ہو جائیں گی۔', 'Is payslip ko ulta karein? Journal ulta ho jayega aur katotiyaan wapas ho jayengi.'],
    'Cancel this order?' => ['یہ آرڈر منسوخ کریں؟', 'Ye order mansookh karein?'],
    'Reverse this GRN/invoice? Stock will be deducted and the journal reversed.' => ['اس GRN/انوائس کو الٹیں؟ اسٹاک منہا ہو جائے گا اور جرنل الٹ ہو جائے گا۔', 'Is GRN/invoice ko ulta karein? Stock deduct ho jayega aur journal ulta ho jayega.'],
    'Reverse this invoice? Stock will be restored and a reversal journal posted. This cannot be undone.' => ['اس انوائس کو الٹیں؟ اسٹاک بحال ہو جائے گا اور الٹ اندراج پوسٹ ہو گا۔ یہ واپس نہیں ہو سکتا۔', 'Is invoice ko ulta karein? Stock restore ho jayega aur ulta entry post hogi. Ye wapas nahi ho sakta.'],
    'Reverse this return? Stock will be taken out again and the journal reversed.' => ['اس واپسی کو الٹیں؟ اسٹاک دوبارہ نکل جائے گا اور جرنل الٹ ہو جائے گا۔', 'Is return ko ulta karein? Stock dobara nikal jayega aur journal ulta ho jayega.'],
    'Delete this backup file?' => ['یہ بیک اپ فائل حذف کریں؟', 'Ye backup file delete karein?'],
    'Approve and apply this adjustment? Stock and journals will be posted.' => ['یہ ایڈجسٹمنٹ منظور اور لاگو کریں؟ اسٹاک اور جرنل پوسٹ ہو جائیں گے۔', 'Ye adjustment approve aur apply karein? Stock aur journal post ho jayenge.'],
    'Reject this adjustment?' => ['یہ ایڈجسٹمنٹ مسترد کریں؟', 'Ye adjustment reject karein?'],
    "Toggle this user's access?" => ['اس یوزر کی رسائی ٹوگل کریں؟', "Is user ki access toggle karein?"],
];
$ur = $roman = [];
foreach ($hand as $k => [$u, $r]) { $ur[$k] = $u; $roman[$k] = $r; }
file_put_contents(__DIR__ . '/tr_placeholder_keys.php', "<?php\nreturn [\n 'ur' => " . var_export($ur, true) . ",\n 'roman' => " . var_export($roman, true) . ",\n];\n");
echo count($ur), " placeholder keys written\n";
