<?php $title = __('Employees'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="employees">
  <input name="q" placeholder="<?= e(__('Search code / name / designation / phone…')) ?>" value="<?= e($q) ?>" style="min-width:260px"><button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
  <span style="flex:1"></span><?php if (Auth::can('employees.create')): ?><a class="btn btn-primary" href="<?= e(url('employees/new')) ?>"><?= __('+ New Employee') ?></a><?php endif; ?>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th></th><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('Designation') ?></th><th><?= __('Department') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Salary') ?></th><th><?= __('Payment') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $e): ?><tr><td><?php if (!empty($e['photo_path'])): ?><img src="<?= e(photo_data_uri($e['photo_path'])) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else: ?><div class="emp-avatar" style="width:40px;height:40px;font-size:17px"><?= e(mb_strtoupper(mb_substr($e['name'], 0, 1))) ?></div><?php endif; ?></td><td class="mono"><?= e($e['code']) ?></td><td><?= e($e['name']) ?></td><td><?= e($e['designation'] ?? '—') ?></td><td><?= e($e['department'] ?? '—') ?></td>
<td><?= e($e['salary_type']) ?></td><td class="num"><?= money($e['salary_rate'], false) ?></td>
<td><span class="badge <?= ($e['payment_method'] ?? 'cash') === 'bank' ? 'blue' : 'amber' ?>"><?= strtoupper(e($e['payment_method'] ?? 'cash')) ?></span></td>
<td><span class="badge <?= $e['is_active'] ? 'green' : 'gray' ?>"><?= $e['is_active'] ? 'Active' : 'Off' ?></span></td>
<td class="btn-group"><a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$e['id'] . '/ledger')) ?>"><?= __('Ledger') ?></a><a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$e['id'] . '/edit')) ?>"><?= __('Edit') ?></a></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="10" class="muted"><?= __('No employees found.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'employees', ['q' => $q]) ?></div>
