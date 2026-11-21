<?php $title = 'Maintenance Assets'; ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="maintenance/assets">
  <input name="q" placeholder="<?= e(__('Search asset / department / location…')) ?>" value="<?= e($q) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('maintenance.create')): ?><a class="btn btn-primary" href="<?= e(url('maintenance/assets/new')) ?>"><?= __('+ New Asset') ?></a><?php endif; ?>
</form>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Assets on Register') ?></div><div class="val"><?= (int)$totRows['assets'] ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Total Repair Cost (All Time)') ?></div><div class="val"><?= money($totRows['total']) ?></div></div>
</div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Asset') ?></th><th><?= __('Asset No') ?></th><th><?= __('Department') ?></th><th><?= __('Location') ?></th><th class="num"><?= __('Repair Jobs') ?></th><th class="num"><?= __('Total Repair Cost') ?></th><th class="num"><?= __('Posted Cost') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['name']) ?></strong><?php if (!empty($r['category'])): ?><br><span class="muted" style="font-size:11px"><?= e($r['category']) ?></span><?php endif; ?></td><td class="mono"><?= e($r['asset_no'] ?? '—') ?></td><td><?= e($r['department']) ?></td><td><?= e($r['location'] ?? '—') ?></td><td class="num"><?= (int)$r['jobs'] ?></td><td class="num"><?= money($r['repair_cost']) ?></td><td class="num"><?= money($r['posted_cost']) ?></td><td><?php $st = $r['status'] ?? 'active'; ?><span class="badge <?= $st === 'active' ? 'green' : ($st === 'retired' ? 'red' : 'gray') ?>"><?= e(strtoupper($st)) ?></span></td><td><?php if (Auth::can('maintenance.create')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('maintenance/assets/' . (int)$r['id'] . '/edit')) ?>"><?= __('Edit') ?></a> <?php endif; ?><a class="btn btn-outline btn-sm" href="<?= e(url('maintenance/assets/' . (int)$r['id'])) ?>"><?= __('History') ?></a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="muted"><?= __('No assets registered yet.') ?></td></tr><?php endif; ?></tbody></table></div></div>
