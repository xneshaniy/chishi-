<?php $title = __('Products'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="products">
  <input name="q" placeholder="<?= e(__('Search SKU / name…')) ?>" value="<?= e($q) ?>" style="min-width:220px">
  <select name="category_id"><option value="0"><?= __('All categories') ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="kind"><option value=""><?= __('All kinds') ?></option><option value="finished" <?= $kind==='finished'?'selected':'' ?>><?= __('Finished Goods') ?></option><option value="raw" <?= $kind==='raw'?'selected':'' ?>><?= __('Raw Materials') ?></option><option value="packaging" <?= $kind==='packaging'?'selected':'' ?>><?= __('Packaging') ?></option></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('products.create')): ?><a class="btn btn-outline" href="<?= e(url('products/categories')) ?>"><?= __('Categories') ?></a><a class="btn btn-primary" href="<?= e(url('products/new')) ?>"><?= __('+ New Product') ?></a><?php endif; ?>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl">
  <thead><tr><th><?= __('SKU') ?></th><th><?= __('Name') ?></th><th><?= __('Category') ?></th><th><?= __('Kind') ?></th><th class="num"><?= __('On Hand') ?></th><th class="num"><?= __('Purchase') ?></th><th class="num"><?= __('Sale') ?></th><th><?= __('Tax %') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td class="mono"><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['category'] ?? '—') ?></td>
        <td><span class="badge gray"><?= e($r['kind']) ?></span></td>
        <td class="num"><?= qty($r['on_hand']) ?> <span class="muted"><?= e($r['unit_short']) ?></span></td>
        <td class="num"><?= money($r['purchase_price']) ?></td><td class="num"><?= money($r['sale_price']) ?></td><td class="num"><?= qty($r['tax_rate']) ?></td>
        <td><span class="badge <?= $r['is_active'] ? 'green' : 'gray' ?>"><?= $r['is_active'] ? 'Active' : 'Off' ?></span></td>
        <td><a class="btn btn-outline btn-sm" href="<?= e(url('products/' . (int)$r['id'] . '/edit')) ?>"><?= __('Edit') ?></a></td></tr>
  <?php endforeach; ?>
  <?php if (!$p['rows']): ?><tr><td colspan="10" class="muted"><?= __('No products found.') ?></td></tr><?php endif; ?></tbody>
</table></div><?= pager_html($p, 'products', ['q' => $q, 'category_id' => $cat, 'kind' => $kind]) ?></div>
