<?php
/** Wrap raw prose in maintenance/voucher views in __() calls (same conservative filter as RM wrap). */
$views = [
    '../app/views/maintenance/index.php',
    '../app/views/maintenance/new.php',
    '../app/views/maintenance/view.php',
    '../app/views/maintenance/assets.php',
    '../app/views/maintenance/asset_form.php',
    '../app/views/maintenance/report.php',
    '../app/views/cashbank/payment_view.php',
    '../app/views/cashbank/receipt_view.php',
    '../app/views/expenses/view.php',
    '../app/views/purchase/invoice_view.php',
    '../app/views/partials/doc_head.php',
];
$keys = [];
function looksLikeProse(string $t): bool {
    $t = trim($t);
    if ($t === '' || mb_strlen($t) > 150) return false;
    if (!preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $t)) return false;
    if (preg_match('/[\${}\[\]|`]|->|=>|\\\\|function\s|const\s|window\.|document\.|className|querySelector|getElementById|addEventListener|JSON\.|<\?php|__\(/', $t)) return false;
    $punct = preg_match_all('/[^A-Za-z0-9\s\x{0600}-\x{06FF}]/u', $t);
    if ($punct > mb_strlen($t) * 0.45) return false;
    return true;
}
foreach ($views as $v) {
    $f = __DIR__ . '/' . $v;
    if (!file_exists($f)) { echo "skip: $v\n"; continue; }
    $s = file_get_contents($f);
    $orig = $s;
    $out = preg_replace_callback('/>([^<>{}$]{2,150})</su', function ($m) use (&$keys) {
        $text = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if (!looksLikeProse($text)) return $m[0];
        if (preg_match('/__\(|e\(|money\(|qty\(|date_display|dt_display|json_encode/', $m[0])) return $m[0];
        $keys[$text] = true;
        return '><?= __(' . var_export($text, true) . ') ?><';
    }, $s);
    if ($out !== $orig) { file_put_contents($f, $out); echo "wrapped: $f\n"; }
}
$k = implode("\n", array_keys($keys));
file_put_contents(__DIR__ . '/lang_keys2.txt', $k);
echo "New keys: ", count($keys), "\n";
