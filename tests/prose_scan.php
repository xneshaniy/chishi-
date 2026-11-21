<?php
$files = ['app/views/maintenance/index.php','app/views/maintenance/view.php','app/views/maintenance/report.php','app/views/maintenance/assets.php','app/views/maintenance/asset_form.php','app/views/cashbank/payment_view.php','app/views/cashbank/receipt_view.php','app/views/expenses/view.php','app/views/partials/doc_head.php','app/views/purchase/invoice_view.php','app/views/maintenance/new.php'];
foreach ($files as $file) {
    $s = file_get_contents($file);
    // match text directly between > and < using a delimiter that avoids the close-tag sequence
    preg_match_all('/>([^<>{}$?]{3,80})</', $s, $m);
    $sus = [];
    foreach ($m[1] as $t) {
        $t = trim(html_entity_decode($t));
        if (!preg_match('/[A-Za-z]/', $t)) continue;
        if (preg_match('/__\(|e\(|money\(|qty\(|date_display|dt_display|json_encode|strtoupper|ucfirst|mb_substr|number_format/', $t)) continue;
        $sus[] = $t;
    }
    if ($sus) {
        echo "=== $file\n";
        foreach (array_unique($sus) as $t) echo "  [" . $t . "]\n";
    }
}
