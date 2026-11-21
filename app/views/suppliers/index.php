<?php $title = __('Suppliers'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="suppliers">
  <input name="q" placeholder="<?= e(__('Search code / name / phone…')) ?>" value="<?= e($q) ?>" style="min-width:240px">
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('suppliers.create')): ?><a class="btn btn-primary" href="<?= e(url('suppliers/new')) ?>"><?= __('+ New Supplier') ?></a><?php endif; ?>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl">
<thead><tr><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('Phone') ?></th><th><?= __('City') ?></th><th class="num"><?= __('Balance') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $s): ?>
  <?php $signed = ($s['opening_type'] === 'credit' ? (float)$s['opening_balance'] : -(float)$s['opening_balance']) + (float)$s['gl_balance']; ?>
  <tr><td class="mono"><?= e($s['code']) ?></td><td><?= e($s['name']) ?></td><td><?= e($s['phone']) ?></td><td><?= e($s['city']) ?></td>
      <td class="num" style="color:<?= $signed > 0 ? 'var(--red)' : 'inherit' ?>"><?= money(abs($signed), false) ?><?= $signed > 0 ? ' Cr (payable)' : ($signed < 0 ? ' Dr' : '') ?></td>
      <td class="btn-group"><a class="btn btn-outline btn-sm" href="<?= e(url('suppliers/' . (int)$s['id'] . '/statement')) ?>"><?= __('Statement') ?></a><?php if (Auth::can('suppliers.edit')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('suppliers/' . (int)$s['id'] . '/edit')) ?>"><?= __('Edit') ?></a><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$p['rows']): ?><tr><td colspan="6" class="muted"><?= __('No suppliers found.') ?></td></tr><?php endif; ?></tbody>
</table></div><?= pager_html($p, 'suppliers', ['q' => $q]) ?></div>
