<?php
$routesFile = __DIR__ . '/../app/routes.php';
$content = file_get_contents($routesFile);

if (strpos($content, "'cashbank/daily-position'") === false) {
    $target = "'cashbank/accounts'          => [CashBankController::class, 'accounts', 'cashbank.view'],";
    $replacement = "'cashbank/daily-position'   => [CashBankController::class, 'dailyPosition', 'cashbank.view'],\n    " . $target;
    $content = str_replace($target, $replacement, $content);
    file_put_contents($routesFile, $content);
    echo "Added cashbank/daily-position route\n";
} else {
    echo "Route already exists\n";
}
