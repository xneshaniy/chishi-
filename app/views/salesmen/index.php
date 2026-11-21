<?php $title = __('Salesmen'); ?>
<div class="section-hero" style="display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap">
  <div>
    <h1><?= __('Salesmen') ?></h1>
    <p><?= count($rows) ?> <?= __('salesmen') ?> · <?= __('Click a card to open the full profile with tabs.') ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (is_owner_or_admin()): ?><a class="btn btn-outline" href="<?= e(url('records', ['tab' => 'salesmen'])) ?>">📁 <?= __('Records (Archive)') ?></a><?php endif; ?>
    <?php if (Auth::can('salesmen.create')): ?><a class="btn btn-primary" href="<?= e(url('salesmen/new')) ?>">+ <?= __('Add Salesman') ?></a><?php endif; ?>
  </div>
</div>
<form method="get" class="toolbar" style="margin-bottom:12px"><input type="hidden" name="r" value="salesmen">
  <input name="q" placeholder="<?= e(__('Search name / ID / phone / CNIC / area')) ?>" value="<?= e($q ?? '') ?>" style="min-width:260px">
  <select class="f" name="province_id" style="min-width:150px"><option value=""><?= __('All provinces') ?></option>
    <?php foreach (location_provinces() as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($provinceId ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
  </select>
  <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="show_inactive" value="1" <?= !empty($showInactive) ? 'checked' : '' ?>><?= __('Show inactive') ?></label>
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('bonuses.view')): ?><a class="btn btn-outline" href="<?= e(url('salesmen/bonuses')) ?>"><?= __('Bonuses') ?></a><?php endif; ?>
</form>

<div class="emp-grid">
  <?php foreach ($rows as $s): ?>
    <a class="emp-card" href="<?= e(url('salesmen/' . (int)$s['id'] . '/account')) ?>">
      <?php if (!empty($s['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($s['photo_path'])) ?>" alt="<?= e($s['name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($s['name']) ?></div>
      <div class="emp-code"><?= e($s['code']) ?></div>
      <?php if (!empty($s['phone'])): ?><div class="emp-role">📞 <?= e($s['phone']) ?></div><?php endif; ?>
      <?php if (!empty($s['location_text'])): ?><div class="emp-role"><?= e($s['location_text']) ?></div><?php endif; ?>
      <div class="emp-role"><span class="badge <?= $s['is_active'] ? 'green' : 'amber' ?>"><?= $s['is_active'] ? __('Active') : __('Inactive') ?></span></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel"><p class="muted"><?= __('No salesmen found.') ?></p></div><?php endif; ?>
</div>
