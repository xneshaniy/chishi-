<?php $title = __('Production'); ?>

<div class="simple-hero no-print" style="padding:14px 18px">
  <div class="greet" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div>
      <span style="font-size:22px;font-weight:800"><?= __('Production') ?></span><br>
      <small><?= __('Record daily production, track output per product') ?></small>
    </div>
    <div class="btn-group" style="flex-wrap:wrap;gap:6px;margin-left:auto">
      <?php if (Auth::can('products.create')): ?><a class="btn btn-primary" href="<?= e(url('products/new?from=production')) ?>">+ <?= __('Add New Product') ?></a><?php endif; ?>
      <?php if (Auth::can('production.create')): ?><a class="btn btn-dark" href="<?= e(url('production/run')) ?>">+ <?= __('Daily Production') ?></a><?php endif; ?>
      <a class="btn btn-outline" href="<?= e(url('production/report')) ?>"><?= __('Reports') ?></a>
      <a class="btn btn-outline" href="<?= e(url('production/orders')) ?>"><?= __('Production Orders') ?></a>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Today’s Production') ?></div><div class="sc-val"><?= number_format($stats['today'], 0) ?></div><div class="sc-sub"><?= e($today) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('This Month’s Production') ?></div><div class="sc-val"><?= number_format($stats['month'], 0) ?></div><div class="sc-sub"><?= date('F Y') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Products') ?></div><div class="sc-val"><?= (int)$stats['totalProducts'] ?></div><div class="sc-sub"><?= __('active products') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Production') ?></div><div class="sc-val"><?= number_format($stats['total'], 0) ?></div><div class="sc-sub"><?= __('all-time, orders + daily') ?></div></div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="production">
  <input name="q" placeholder="<?= e(__('Search products by name or code…')) ?>" value="<?= e($q) ?>" style="min-width:240px">
  <select class="f" name="kind" style="min-width:150px">
    <?php foreach (['finished' => 'Finished Goods', 'raw' => 'Raw Material', 'packaging' => 'Packaging', 'all' => 'All kinds'] as $k => $lbl): ?>
      <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e(__($lbl)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
</form>

<div class="emp-grid">
  <?php foreach ($products as $p): ?>
    <a class="emp-card" href="<?= e(url('production/' . (int)$p['id'])) ?>">
      <?php if (!empty($p['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($p['photo_path'])) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($p['name']) ?></div>
      <div class="emp-code"><?= e($p['sku']) ?></div>
      <div class="emp-role"><?= e($p['category'] ?: __('Uncategorised')) ?></div>
      <div class="emp-role"><?= __('On hand:') ?> <?= number_format((float)$p['on_hand'], 0) ?> <?= e(($p['kind'] ?? '') === 'finished' ? __('Packets') : ($p['unit_short'] ?? '')) ?></div>
      <div class="emp-role"><?= __('Produced:') ?> <strong><?= number_format((float)$p['produced_total'], 0) ?></strong> <?= e(($p['kind'] ?? '') === 'finished' ? __('Packets') : ($p['unit_short'] ?? '')) ?></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$products): ?>
    <div class="panel" style="grid-column:1/-1"><p class="muted"><?= __('No products match your filter. Try another search or add a new product.') ?></p></div>
  <?php endif; ?>
</div>
