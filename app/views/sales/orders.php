<?php $title = __('Sales Orders'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="sales/orders">
  <select name="status" onchange="this.form.submit()"><option value=""><?= __('All') ?></option><?php foreach (['draft','confirmed','delivered','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
  <span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('sales/orders/new')) ?>"><?= __('+ New Order') ?></a>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Order') ?></th><th><?= __('Date') ?></th><th><?= __('Customer') ?></th><th><?= __('Salesman') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $o): ?><tr><td class="mono"><?= e($o['so_no']) ?></td><td><?= date_display($o['order_date']) ?></td><td><?= e($o['customer']) ?></td><td><?= e($o['salesman'] ?? '—') ?></td><td class="num"><?= money($o['total']) ?></td>
<td><span class="badge <?= ['draft'=>'gray','confirmed'=>'green','delivered'=>'blue','cancelled'=>'red'][$o['status']] ?>"><?= e(strtoupper($o['status'])) ?></span></td>
<td><?php if ($o['status'] === 'draft'): ?><form method="post" action="<?= e(url('sales/orders/' . (int)$o['id'] . '/confirm')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-dark btn-sm"><?= __('Confirm') ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="7" class="muted"><?= __('No orders.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'sales/orders', ['status' => $status]) ?></div>
