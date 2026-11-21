<?php $title = __('Product Categories'); ?>
<div class="two-col">
  <form method="post" class="panel"><?= csrf_field() ?>
    <h2><?= __('Add Category') ?></h2>
    <label class="f"><?= __('Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. Cigarettes, Mouth Freshener…')) ?>">
    <label class="f mt"><?= __('Kind') ?></label><select class="f" name="kind"><option value="finished"><?= __('Finished Goods') ?></option><option value="raw"><?= __('Raw Materials') ?></option><option value="packaging"><?= __('Packaging') ?></option></select>
    <button class="btn btn-primary mt" type="submit"><?= __('Add') ?></button>
  </form>
  <div class="panel"><h2><?= __('Categories') ?></h2>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Name') ?></th><th><?= __('Kind') ?></th><th class="num"><?= __('Products') ?></th></tr></thead>
      <tbody><?php foreach ($rows as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= e($c['kind']) ?></td><td class="num"><?= (int)$c['product_count'] ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
</div>
