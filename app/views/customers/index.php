<?php $title = __('Customers'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="customers">
  <input name="q" placeholder="<?= e(__('Search code / name / phone / area…')) ?>" value="<?= e($q) ?>" style="min-width:260px">
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('customers.create')): ?><a class="btn btn-primary" href="<?= e(url('customers/new')) ?>"><?= __('+ New Customer') ?></a><?php endif; ?>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl">
<thead><tr><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('Phone') ?></th><th><?= __('Area') ?></th><th><?= __('Salesman') ?></th><th class="num"><?= __('Credit Limit') ?></th><th class="num"><?= __('Balance') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $c): ?>
  <?php
    $signed = ($c['opening_type'] === 'debit' ? (float)$c['opening_balance'] : -(float)$c['opening_balance']) + (float)$c['gl_balance'];
  ?>
  <tr><td class="mono"><?= e($c['code']) ?></td><td><?= e($c['name']) ?></td><td><?= e($c['phone']) ?></td><td><?= e($c['area'] ?? '—') ?></td><td><?= e($c['salesman'] ?? '—') ?></td>
      <td class="num"><?= money($c['credit_limit'], false) ?></td>
      <td class="num" style="color:<?= $signed > 0 ? 'var(--red)' : 'inherit' ?>"><?= money(abs($signed), false) ?><?= $signed > 0 ? ' Dr' : ($signed < 0 ? ' Cr' : '') ?></td>
      <td class="btn-group"><a class="btn btn-outline btn-sm" href="<?= e(url('customers/' . (int)$c['id'] . '/statement')) ?>"><?= __('Statement') ?></a><?php if (Auth::can('customers.edit')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('customers/' . (int)$c['id'] . '/edit')) ?>"><?= __('Edit') ?></a><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$p['rows']): ?><tr><td colspan="8" class="muted"><?= __('No customers found.') ?></td></tr><?php endif; ?></tbody>
</table></div><?= pager_html($p, 'customers', ['q' => $q]) ?></div>
