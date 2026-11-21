<?php $title = __('Physical Stock Count'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="stock/counts">
  <label class="muted"><?= __('Warehouse:') ?></label>
  <select name="warehouse_id" onchange="this.form.submit()"><option value="0"><?= __('— select —') ?></option><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select>
</form>
<?php if ($wh && $products): ?>
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="warehouse_id" value="<?= (int)$wh ?>">
<h2><?= __('Count Stock') ?><span class="muted" style="font-size:12px"><?= __('enter the') ?><em><?= __('counted') ?></em><?= __('quantity for each product — variances are queued as a pending adjustment') ?></span></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('SKU') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('System Qty') ?></th><th class="num"><?= __('Avg Cost') ?></th><th class="num"><?= __('Counted Qty') ?></th></tr></thead>
<tbody><?php foreach ($products as $p): ?>
<tr><td class="mono"><?= e($p['sku']) ?></td><td><?= e($p['name']) ?></td><td class="num"><?= qty($p['system_qty']) ?></td><td class="num"><?= money($p['avg_cost'], false) ?></td>
<td><input class="f" type="number" step="0.001" min="0" name="counted[<?= (int)$p['id'] ?>]" placeholder="<?= e(__('leave blank to skip')) ?>"></td></tr>
<?php endforeach; ?></tbody></table></div>
<button class="btn btn-primary mt" type="submit"><?= __('Record Count → Create Adjustment') ?></button>
</form>
<?php elseif ($wh): ?><div class="alert alert-info"><?= __('No products in this warehouse.') ?></div><?php endif; ?>
