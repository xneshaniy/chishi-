<?php
/** Wrap remaining raw prose in the new claim views in __() calls (same conservative filter as apply_translations.php). */
$views = ['goods.php', 'goods_new.php', 'goods_view.php', 'receipt.php', 'index.php'];
$keys = [];
function looksLikeProse(string $t): bool {
    $t = trim($t);
    if ($t === '' || mb_strlen($t) > 150) return false;
    if (!preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $t)) return false;
    if (preg_match('/[\${}\[\]|`]|->|=>|\\\\|function\s|const\s|window\.|document\.|className|querySelector|getElementById|addEventListener|JSON\.|<\?php/', $t)) return false;
    $punct = preg_match_all('/[^A-Za-z0-9\s\x{0600}-\x{06FF}]/u', $t);
    if ($punct > mb_strlen($t) * 0.45) return false;
    return true;
}
foreach ($views as $v) {
    $f = __DIR__ . '/../app/views/claims/' . $v;
    if (!file_exists($f)) continue;
    $s = file_get_contents($f);
    $orig = $s;
    $out = preg_replace_callback('/>([^<>{}$]{2,150})</su', function ($m) use (&$keys) {
        $text = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if (!looksLikeProse($text)) return $m[0];
        if (preg_match('/__\(|\be\(|money\(|qty\(|date_display|dt_display/', $m[0])) return $m[0]; // already interpolated
        $keys[$text] = true;
        return '><?= __(' . var_export($text, true) . ') ?><';
    }, $s);
    if ($out !== $orig) file_put_contents($f, $out);
}
$k2 = file_get_contents(__DIR__ . '/used_keys.txt');
file_put_contents(__DIR__ . '/used_keys.txt', $k2 . "\n" . implode("\n", array_keys($keys)));
file_put_contents(__DIR__ . '/lang_keys.txt', implode("\n", array_keys($keys)));
echo "New keys from claim views: ", count($keys), "\n";
foreach (array_keys($keys) as $k) echo "  $k\n";
