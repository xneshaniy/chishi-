<?php
$l = require __DIR__ . '/../app/lang.php';
echo 'en count: ', count($l['en']), "\n";
echo 'Claim Goods in en: ', isset($l['en']['Claim Goods']) ? 'yes => ' . $l['en']['Claim Goods'] : 'no', "\n";
echo "Claim Goods in ur: ", isset($l['ur']['Claim Goods']) ? 'yes => ' . $l['ur']['Claim Goods'] : 'no', "\n";
echo "Claim Goods in roman: ", isset($l['roman']['Claim Goods']) ? 'yes => ' . $l['roman']['Claim Goods'] : 'no', "\n";
echo "Customer Claims in en: ", isset($l['en']['Customer Claims']) ? 'yes' : 'no', "\n";
echo "Action in en: ", isset($l['en']['Action']) ? 'yes' : 'no', "\n";
