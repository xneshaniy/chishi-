<?php
/** Build app/lang.php from key-bound dictionaries + extras + current keys. */
$used = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$urDict = require __DIR__ . '/dict_ur_generated.php';
$romanDict = require __DIR__ . '/dict_roman_clean.php'; // fully hand-curated (no placeholders)
$extra = require __DIR__ . '/tr_extra.php';
$extraRaw = require __DIR__ . '/tr_raw_keys.php';
$extraMaint = require __DIR__ . '/tr_maintenance_keys.php';
$extraPayroll = require __DIR__ . '/tr_payroll_keys.php';
$extraRm = require __DIR__ . '/tr_rm_keys.php';
$extraSimple = require __DIR__ . '/tr_simple_keys.php';
$extraSalesmanAccount = require __DIR__ . '/tr_salesman_account_keys.php';
$extraHardcoded = require __DIR__ . '/tr_hardcoded_keys.php';
$extraFlash = require __DIR__ . '/tr_flash_keys.php';
$extraRomanFull = require __DIR__ . '/tr_roman_full.php';
foreach (['ur','roman'] as $lg) foreach ($extraFlash[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraNotif = require __DIR__ . '/tr_notif_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraNotif[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraThrow = require __DIR__ . '/tr_throw_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraThrow[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraStaff = require __DIR__ . '/tr_staff_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraStaff[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraJampur = require __DIR__ . '/tr_jampur_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraJampur[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSalesmanProfile = require __DIR__ . '/tr_salesman_profile_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSalesmanProfile[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraCapitalIncome = require __DIR__ . '/tr_capital_income_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraCapitalIncome[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraLabour = require __DIR__ . '/tr_labour_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraLabour[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraPhoto = require __DIR__ . '/tr_photo_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraPhoto[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraTeam = require __DIR__ . '/tr_team_keys.php';
$extraEmployees = require __DIR__ . '/tr_employees_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraEmployees[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSmPay = require __DIR__ . '/tr_salesman_payments_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSmPay[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraProduction = require __DIR__ . '/tr_production_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraProduction[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraReporting = require __DIR__ . '/tr_reporting_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraReporting[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSalaryAtt = require __DIR__ . '/tr_salary_attendance_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSalaryAtt[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraCash = require __DIR__ . '/tr_cash_tracking_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraCash[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraAdv = require __DIR__ . '/tr_adv_claims_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraAdv[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraHisab = require __DIR__ . '/tr_hisab_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraHisab[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraDaily = require __DIR__ . '/tr_daily_overview_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraDaily[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraAN = require __DIR__ . '/tr_approvals_notifications_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraAN[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraArchive = require __DIR__ . '/tr_archive_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraArchive[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraPA = require __DIR__ . '/tr_payment_approval_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraPA[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraM = require __DIR__ . '/tr_manager_approval_attendance_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraM[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraR = require __DIR__ . '/tr_registration_frequency_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraR[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraRM = require __DIR__ . '/tr_raw_material_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraRM[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraPL = require __DIR__ . '/tr_personal_loans_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraPL[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraEG = require __DIR__ . '/tr_expense_gifts_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraEG[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraCE = require __DIR__ . '/tr_company_expenses_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraCE[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSB = require __DIR__ . '/tr_sidebar_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSB[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraWI = require __DIR__ . '/tr_walkin_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraWI[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraST = require __DIR__ . '/tr_status_toggle_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraST[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraCS = require __DIR__ . '/tr_company_stock_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraCS[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraAL = require __DIR__ . '/tr_approval_label_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraAL[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraPK = require __DIR__ . '/tr_pkg_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraPK[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraWH = require __DIR__ . '/tr_warehouse_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraWH[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraUS = require __DIR__ . '/tr_users_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraUS[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSW = require __DIR__ . '/tr_single_wh_keys.php';
$extraISS = require __DIR__ . '/tr_salesman_issue_keys.php';foreach (['ur','roman'] as $lg) foreach ($extraISS[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraSW[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSMInv = require __DIR__ . '/tr_salesman_invoice_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSMInv[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSG = require __DIR__ . '/tr_stock_guard_keys.php';
$extraIB = require __DIR__ . '/tr_invoice_bonus_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraIB[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraSG[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraMine = require __DIR__ . '/tr_approvals_mine_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraMine[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraSD = require __DIR__ . '/tr_salesman_dash_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraSD[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraTeam[$lg] as $k => $v) $extra[$lg][$k] = $v;
$extraPlaceholder = require __DIR__ . '/tr_placeholder_keys.php';
foreach (['ur','roman'] as $lg) foreach ($extraPlaceholder[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraSalesmanAccount[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraHardcoded[$lg] as $k => $v) $extra[$lg][$k] = $v;
// tr_roman_full is a flat English ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ RomanUrdu map (no Urdu half)
foreach ($extraRomanFull as $k => $v) $extra['roman'][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraSimple[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraRaw[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraMaint[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraPayroll[$lg] as $k => $v) $extra[$lg][$k] = $v;
foreach (['ur','roman'] as $lg) foreach ($extraRm[$lg] as $k => $v) $extra[$lg][$k] = $v;

$current = require __DIR__ . '/../app/lang.php';
$en = [];
foreach ($used as $key) $en[$key] = $key;
$en = array_merge($en, array_combine(array_keys($extra['ur']), array_keys($extra['ur'])));
$en = array_merge($en, $current['en']);

$ur = $urDict + $extra['ur'] + $current['ur'];
$roman = $romanDict + $extra['roman'] + $current['roman'];

// sanity: every used key must exist in en/ur/roman
foreach (['en' => $en, 'ur' => $ur, 'roman' => $roman] as $lang => $dict) {
    $missing = array_diff($used, array_keys($dict));
    if ($missing) { echo "MISSING in $lang:\n  ", implode("\n  ", $missing), "\n"; exit(1); }
}
foreach (['en' => &$en, 'ur' => &$ur, 'roman' => &$roman] as $lang => &$arr) ksort($arr);
$export = fn(array $arr) => var_export($arr, true);

$out = <<<PHP
<?php
/**
 * UI language strings ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â JUSTI NUMBER ONE ERP (Chishti Tobacco Company)
 * en / ÃƒÆ’Ã‹Å“Ãƒâ€šÃ‚Â§ÃƒÆ’Ã‹Å“Ãƒâ€šÃ‚Â±ÃƒÆ’Ã‹Å“Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â€žÂ¢Ãƒâ€¹Ã¢â‚¬Â  / Roman Urdu. English text is the key (identity fallback in en).
 * AUTO-GENERATED by tests/build_lang.php from:
 *   en      ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ English source strings (identity)
 *   ur/roman ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ tests/rebind.php output (tests/dict_*_generated.php) + tests/tr_extra.php
 */
return [
    'en' => {$export($en)},
    'ur' => {$export($ur)},
    'roman' => {$export($roman)},
];
PHP;
$out = str_replace("\r\n", "\n", $out);
file_put_contents(__DIR__ . '/../app/lang.php', $out);
echo "app/lang.php: ", count($en), " en / ", count($ur), " ur / ", count($roman), " roman keys\n";
