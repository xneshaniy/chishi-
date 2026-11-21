<?php $title = __('Expense Categories'); ?>
<div class="toolbar"><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('expenses/categories/new')) ?>"><?= __('+ New Category') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Category') ?></th><th><?= __('GL Account') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $c): ?><tr><td><?= e($c['name']) ?></td><td><code><?= e($c['code']) ?></code> — <?= e($c['account_name']) ?></td><td><?= $c['is_active'] ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
