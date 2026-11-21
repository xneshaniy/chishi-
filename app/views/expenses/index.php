<?php $title = __('Expenses'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="expenses">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="q" placeholder="<?= e(__('Search…')) ?>" value="<?= e($q) ?>">
  <select name="category_id"><option value="0"><?= __('All categories') ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value=""><?= __('All') ?></option><?php foreach (['pending','posted','rejected','reversed'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('expenses', ['from' => $from, 'to' => $to, 'q' => $q, 'category_id' => $cat, 'status' => $status, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <span style="flex:1"></span><a class="btn btn-outline" href="<?= e(url('expenses/categories')) ?>"><?= __('Categories') ?></a>
  <?php if (Auth::can('expenses.create')): ?><a class="btn btn-primary" href="<?= e(url('expenses/new')) ?>"><?= __('+ New Expense') ?></a><?php endif; ?>
</form>
<div class="alert alert-info"><?= __('Period expenses:') ?><strong><?= money($sum) ?></strong></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Category') ?></th><th><?= __('Payee') ?></th><th><?= __('Description') ?></th><th><?= __('Via') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $x): ?><tr><td class="mono"><?= e($x['expense_no']) ?></td><td><?= date_display($x['date']) ?></td><td><?= e($x['category']) ?></td><td><?= e($x['payee'] ?? '—') ?></td><td style="max-width:260px"><?= e($x['description'] ?? '') ?></td>
<td><span class="badge gray"><?= e(strtoupper($x['payment_type'])) ?></span></td><td class="num"><?= money($x['amount']) ?></td>
<td><span class="badge <?= ['pending'=>'amber','posted'=>'green','rejected'=>'red','reversed'=>'red'][$x['status']] ?>"><?= e(strtoupper($x['status'])) ?></span></td>
<td class="btn-group"><a class="btn btn-outline btn-sm" href="<?= e(url('expenses/' . (int)$x['id'])) ?>"><?= __('View/Print') ?></a>
<?php if ($x['status'] === 'pending'): ?>
  <?php if (Auth::can('approvals.decide')): ?>
    <form method="post" action="<?= e(url('expenses/' . (int)$x['id'] . '/post')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= __('Approve & Post') ?></button></form>
  <?php else: ?><span class="badge amber"><?= __('Awaiting Admin approval') ?></span><?php endif; ?>
<?php endif; ?>
<?php if ($x['status'] === 'posted' && Auth::can('journal.reverse')): ?><form method="post" action="<?= e(url('expenses/' . (int)$x['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Reverse this expense?')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Expense reversal"><button class="btn btn-danger btn-sm" type="submit"><?= __('Reverse') ?></button></form><?php endif; ?>
</td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="9" class="muted"><?= __('No expenses in range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'expenses', ['from' => $from, 'to' => $to, 'q' => $q, 'category_id' => $cat, 'status' => $status]) ?></div>
