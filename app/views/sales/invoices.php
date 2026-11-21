<?php $title = __('Sales Invoices'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="sales/invoices">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="q" placeholder="<?= e(__('Invoice / salesman…')) ?>" value="<?= e($q) ?>">
  <select name="status"><option value=""><?= __('All statuses') ?></option><option value="posted" <?= $status==='posted'?'selected':'' ?>><?= __('Posted') ?></option><option value="reversed" <?= $status==='reversed'?'selected':'' ?>><?= __('Reversed') ?></option></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('sales/invoices', ['from' => $from, 'to' => $to, 'q' => $q, 'status' => $status, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('sales.create')): ?><a class="btn btn-primary" href="<?= e(url('sales/invoices/new')) ?>"><?= __('+ New Invoice') ?></a><?php endif; ?>
</form>
<div class="alert alert-info"><?= __('Period total:') ?><strong><?= money($totals['t']) ?></strong> (<?= $p['total'] ?><?= __('invoices, tax') ?><?= money($totals['x']) ?>)</div>
<div class="panel print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Sales Invoices') ?></span><hr></div><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Invoice') ?></th><th><?= __('Date') ?></th><th><?= __('Customer') ?></th><th><?= __('Salesman') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $i): ?><tr>
<td><a class="mono" href="<?= e(url('sales/invoices/' . (int)$i['id'])) ?>"><?= e($i['invoice_no']) ?></a></td><td><?= date_display($i['date']) ?></td><td><?= e($i['customer']) ?></td><td><?= e($i['salesman'] ?? '—') ?></td>
<td><span class="badge <?= $i['payment_type'] === 'cash' ? 'green' : 'blue' ?>"><?= e(strtoupper($i['payment_type'])) ?></span></td>
<td class="num"><?= money($i['total']) ?></td>
<td><span class="badge <?= $i['status'] === 'posted' ? 'green' : 'red' ?>"><?= e(strtoupper($i['status'])) ?></span></td>
<td><a class="btn btn-outline btn-sm" href="<?= e(url('sales/invoices/' . (int)$i['id'])) ?>"><?= __('View') ?></a></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="8" class="muted"><?= __('No invoices in range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'sales/invoices', ['from' => $from, 'to' => $to, 'q' => $q, 'status' => $status]) ?></div>
