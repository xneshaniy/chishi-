<?php $title = __('Stock Valuation'); ?>
<div class="toolbar"><span class="muted"><?= __('Valued at') ?><?= e(cfg('costing_method')) ?><?= __('cost.') ?></span><span style="flex:1"></span><a class="btn btn-outline" href="<?= e(url('reports/stock-value', ['export' => 'csv'])) ?>"><?= __('CSV') ?></a><button class="btn btn-outline no-print" onclick="window.print()"><?= __('🖨 Print') ?></button></div>
<div class="two-col">
<div class="panel print-area"><h2><?= __('By Warehouse') ?></h2><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Warehouse') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Value') ?></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?> <span class="muted"><?= e($r['code']) ?></span></td><td><?= e($r['type']) ?></td><td class="num"><?= qty($r['qty']) ?></td><td class="num"><?= number_format($r['value'], 2) ?></td></tr><?php endforeach; ?></tbody>
<tfoot><tr><th colspan="3"><?= __('TOTAL STOCK VALUE') ?></th><th class="num"><?= number_format($grand, 2) ?></th></tr></tfoot></table></div></div>
<div class="panel"><h2><?= __('By Kind') ?></h2><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Kind') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Value') ?></th></tr></thead>
<tbody><?php foreach ($byKind as $r): ?><tr><td><span class="badge gray"><?= e($r['kind']) ?></span></td><td class="num"><?= qty($r['qty']) ?></td><td class="num"><?= number_format($r['value'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p class="help mt">FiFo layers (if FIFO is configured) are rebuilt from receipts; weighted-average cost is maintained continuously in stock_balances.avg_cost. Salesman stocks count as inventory assets until sold.</p></div>
</div>
