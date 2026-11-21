<?php $title = __('Daily Sales'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/daily-sales"><label class="muted"><?= __('Date:') ?></label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
<a class="btn btn-outline" href="<?= e(url('reports/daily-sales', ['date' => $date, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a><button class="btn btn-outline no-print" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button></form>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Invoices') ?></div><div class="val"><?= (int)$totals['n'] ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Gross Sales') ?></div><div class="val"><?= money($totals['t']) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Tax Collected') ?></div><div class="val"><?= money($totals['x']) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('COGS') ?></div><div class="val"><?= money($totals['cost']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Gross Margin') ?></div><div class="val"><?= money($totals['t'] - $totals['cost']) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Collections') ?></div><div class="val"><?= money($collections) ?></div></div>
</div>
<div class="panel print-area"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Invoice') ?></th><th><?= __('Type') ?></th><th><?= __('Customer') ?></th><th><?= __('Salesman') ?></th><th class="num"><?= __('Subtotal') ?></th><th class="num"><?= __('Tax') ?></th><th class="num"><?= __('Total') ?></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><td class="mono"><?= e($r['invoice_no']) ?></td><td><?= e(strtoupper($r['payment_type'])) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['salesman'] ?? '—') ?></td><td class="num"><?= number_format($r['subtotal'] - $r['discount'], 2) ?></td><td class="num"><?= number_format($r['tax'], 2) ?></td><td class="num"><?= number_format($r['total'], 2) ?></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= __('No sales on this date.') ?></td></tr><?php endif; ?></tbody>
<tfoot><tr><th colspan="6"><?= __('TOTAL') ?></th><th class="num"><?= number_format($totals['t'], 2) ?></th></tr></tfoot></table></div></div>
