<?php
/** Merge claim-goods keys (tests/tr_claim_keys.php) into tests/tr_extra.php which build_lang.php consumes. */
$extraFile = __DIR__ . '/tr_extra.php';
$merged = require $extraFile;
$claim = require __DIR__ . '/tr_claim_keys.php';
foreach (['ur', 'roman'] as $l) {
    $merged[$l] = array_merge($merged[$l], $claim[$l]);
}
file_put_contents($extraFile, "<?php\n/** Extra translation keys — raw-English keyed (merged by build_lang.php). AUTO-MERGED from tr_claim_keys.php; edit tr_claim_keys.php for claim-module strings. */\nreturn " . var_export($merged, true) . ";\n");
echo "merged. ur keys: ", count($merged['ur']), " | roman keys: ", count($merged['roman']), "\n";
