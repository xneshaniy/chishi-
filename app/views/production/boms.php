<?php $title = __('Bills of Materials'); ?>
<div class="toolbar"><span class="muted"><?= __('Recipes define what each finished good consumes.') ?></span><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('production/boms/new')) ?>"><?= __('+ New BOM / Recipe') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Recipe') ?></th><th><?= __('Finished Product') ?></th><th class="num"><?= __('Output Qty') ?></th><th class="num"><?= __('Materials') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $b): ?><tr><td><?= e($b['name']) ?></td><td><?= e($b['sku'] . ' — ' . $b['product']) ?></td><td class="num"><?= qty($b['output_qty']) ?></td><td class="num"><?= (int)$b['line_count'] ?></td><td><?= $b['is_active'] ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
