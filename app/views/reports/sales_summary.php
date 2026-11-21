<?php $title = __('Sales Summary'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/sales-summary">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <select name="group"><?php foreach (['product' => 'By Product', 'salesman' => 'By Salesman', 'area' => 'By Area', 'customer' => 'By Customer'] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Compute') ?></button>
  <a class="btn btn-outline" href="<?= e(url('reports/sales-summary', ['from' => $from, 'to' => $to, 'group' => $group, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<div class="panel print-area"><h2><?= __('Sales Summary by') ?><?= e(ucfirst($group)) ?> — <?= e(date_display($from)) ?> → <?= e(date_display($to)) ?></h2>
<?php if ($group === 'product'): ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('SKU') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Qty Sold (Packets)') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th><th class="num"><?= __('Sales') ?></th><th class="num"><?= __('Cost') ?></th><th class="num"><?= __('Margin') ?></th></tr></thead>
<tbody><?php $ts = 0; $tc = 0; foreach ($rows as $r): $margin = (float)$r['total'] - (float)$r['cost']; $ts += (float)$r['total']; $tc += (float)$r['cost']; ?>
<tr><td class="mono"><?= e($r['label']) ?></td><td><?= e($r['name']) ?></td><td class="num"><?= qty($r['qty']) ?></td><td class="num"><?= qty($r['bonus_qty']) ?></td><td class="num"><?= number_format($r['total'], 2) ?></td><td class="num"><?= number_format($r['cost'], 2) ?></td><td class="num"><?= number_format($margin, 2) ?></td></tr>
<?php endforeach; ?></tbody><tfoot><tr><th colspan="4"><?= __('TOTAL') ?></th><th class="num"><?= number_format($ts, 2) ?></th><th class="num"><?= number_format($tc, 2) ?></th><th class="num"><?= number_format($ts - $tc, 2) ?></th></tr></tfoot></table></div>
<?php else: ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Group') ?></th><th class="num"><?= __('Invoices') ?></th><th class="num"><?= __('Sales') ?></th><th class="num"><?= __('Cost') ?></th><th class="num"><?= __('Margin') ?></th></tr></thead>
<tbody><?php $ts = 0; $tc = 0; foreach ($rows as $r): $margin = (float)$r['total'] - (float)$r['cost']; $ts += (float)$r['total']; $tc += (float)$r['cost']; ?>
<tr><td><?= e($r['label']) ?></td><td class="num"><?= (int)$r['invoices'] ?></td><td class="num"><?= number_format($r['total'], 2) ?></td><td class="num"><?= number_format($r['cost'], 2) ?></td><td class="num"><?= number_format($margin, 2) ?></td></tr>
<?php endforeach; ?></tbody><tfoot><tr><th><?= __('TOTAL') ?></th><th></th><th class="num"><?= number_format($ts, 2) ?></th><th class="num"><?= number_format($tc, 2) ?></th><th class="num"><?= number_format($ts - $tc, 2) ?></th></tr></tfoot></table></div>
<?php endif; ?>
<p class="help"><?= __('Cost reflects actual issue cost tracked at sale time (weighted-average / FIFO per settings). Margin = sales − COGS.') ?></p></div>
