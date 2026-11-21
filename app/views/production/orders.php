<?php $title = __('Production Orders'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="production/orders">
  <select name="status" onchange="this.form.submit()"><option value=""><?= __('All statuses') ?></option><?php foreach (['draft','released','completed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
  <span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('production/new')) ?>"><?= __('+ New Production Order') ?></a>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="panel print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Production Orders') ?> · <?= date_display(today()) ?></span><hr></div><div class="table-wrap"><table class="tbl">
<thead><tr><th><?= __('Order') ?></th><th><?= __('Product') ?></th><th><?= __('Warehouse') ?></th><th class="num"><?= __('Planned (Packets)') ?></th><th class="num"><?= __('Produced (Packets)') ?></th><th class="num"><?= __('Material Cost') ?></th><th class="num"><?= __('Labor+OH') ?></th><th class="num"><?= __('Total Cost') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $o): ?>
<tr><td class="mono"><?= e($o['order_no']) ?></td><td><?= e($o['sku'] . ' — ' . $o['product']) ?></td><td><?= e($o['warehouse']) ?></td>
<td class="num"><?= qty($o['qty_planned']) ?></td><td class="num"><?= qty($o['qty_produced']) ?></td>
<td class="num"><?= money($o['materials_cost'], false) ?></td><td class="num"><?= money($o['labor_cost'] + $o['overhead_cost'], false) ?></td><td class="num"><?= money($o['total_cost'], false) ?></td>
<td><span class="badge <?= ['draft'=>'gray','released'=>'amber','completed'=>'green','cancelled'=>'red'][$o['status']] ?>"><?= e(strtoupper($o['status'])) ?></span></td>
<td class="btn-group">
<?php if ($o['status'] === 'draft' && Auth::can('production.release')): ?><form method="post" action="<?= e(url('production/' . (int)$o['id'] . '/release')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-dark btn-sm"><?= __('Release') ?></button></form><?php endif; ?>
<?php if ($o['status'] === 'released' && Auth::can('production.complete')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('production/' . (int)$o['id'] . '/complete')) ?>"><?= __('Complete') ?></a><?php endif; ?>
<?php if (in_array($o['status'], ['draft','released'], true) && Auth::can('production.create')): ?><form method="post" action="<?= e(url('production/' . (int)$o['id'] . '/cancel')) ?>" style="display:inline" data-confirm="<?= e(__('Cancel this order?')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm"><?= __('Cancel') ?></button></form><?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php if (!$p['rows']): ?><tr><td colspan="10" class="muted"><?= __('No production orders yet.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'production/orders', ['status' => $status]) ?></div>
