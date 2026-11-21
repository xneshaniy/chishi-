<?php
$f = 'C:\Users\Haseeb\Desktop\chishti\app\views\sales\invoice_new.php';
$lines = Get-Content -LiteralPath $f;
$lines[9]  = '<h3 style="font-size:13px;margin:14px 0 6px"><?= __(\'Items\') ?></h3>';
$lines[10] = '<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __(\'Product\') ?></th><th><?= __(\'Qty\') ?></th><th><?= __(\'Price\') ?></th><th class="num"><?= __(\'Line Total\') ?></th><th></th></tr></thead><tbody id="itemsBody">';
$lines[11] = '<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __(\'— select product —\') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][0]" value="1" required></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="items[price][0]" value="0.00" required></td><td class="line-total num">0.00</td><td></td></tr>';
[IO.File]::WriteAllText($f, ($lines -join "`r`n"));
Write-Output "done";
