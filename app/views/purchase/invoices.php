<?php $title = __('Purchase Invoices / GRN'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="purchase/invoices">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="q" placeholder="<?= e(__('Invoice / supplier / their ref…')) ?>" value="<?= e($q) ?>">
  <select name="status"><option value=""><?= __('All') ?></option><option value="posted" <?= $status==='posted'?'selected':'' ?>><?= __('Posted') ?></option><option value="reversed" <?= $status==='reversed'?'selected':'' ?>><?= __('Reversed') ?></option></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('purchase.receive')): ?><a class="btn btn-primary" href="<?= e(url('purchase/invoices/new')) ?>"><?= __('+ Receive Goods (GRN)') ?></a><?php endif; ?>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="alert alert-info no-print"><?= __('Period purchases:') ?><strong><?= money($totals['t']) ?></strong></div>
<div class="panel print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Purchase Invoices / GRN') ?> · <?= date_display($from) ?> – <?= date_display($to) ?></span><hr></div><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Invoice') ?></th><th><?= __('Date') ?></th><th><?= __('Supplier') ?></th><th><?= __('Their Ref') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $i): ?><tr><td class="mono"><?= e($i['invoice_no']) ?></td><td><?= date_display($i['date']) ?></td><td><?= e($i['supplier']) ?></td><td><?= e($i['supplier_invoice_no'] ?? '—') ?></td>
<td><span class="badge <?= $i['payment_type'] === 'cash' ? 'green' : 'blue' ?>"><?= e(strtoupper($i['payment_type'])) ?></span></td><td class="num"><?= money($i['total']) ?></td>
<td><span class="badge <?= $i['status'] === 'posted' ? 'green' : 'red' ?>"><?= e(strtoupper($i['status'])) ?></span></td>
<td><?php if ($i['status'] === 'posted' && Auth::can('journal.reverse')): ?><form method="post" action="<?= e(url('purchase/invoices/' . (int)$i['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Reverse this GRN/invoice? Stock will be deducted and the journal reversed.')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Purchase reversal"><button class="btn btn-danger btn-sm" type="submit"><?= __('Reverse') ?></button></form><?php endif; ?> <a class="btn btn-outline btn-sm" href="<?= e(url('purchase/invoices/' . (int)$i['id'])) ?>"><?= __('View/Print') ?></a></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="8" class="muted"><?= __('No purchases in range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'purchase/invoices', ['from' => $from, 'to' => $to, 'q' => $q, 'status' => $status]) ?></div>
