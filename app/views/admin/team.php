<?php $title = __('Management Team'); ?>
<div class="toolbar" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
  <form method="get" style="display:flex;gap:8px;align-items:center"><input type="hidden" name="r" value="admin/team"><input class="f" name="q" placeholder="<?= e(__('Search name / username / designation / role…')) ?>" value="<?= e($q) ?>" style="min-width:260px"><button class="btn btn-primary" type="submit"><?= __('Search') ?></button></form>
  <span style="flex:1"></span>
  <a class="btn btn-primary" href="<?= e(url('admin/team/new')) ?>">+ <?= __('New Accountant / Manager') ?></a>
  <a class="btn btn-outline" href="<?= e(url('users')) ?>"><?= __('Users & Roles') ?></a>
</div>

<?php if ($unlinked): ?>
<div class="alert alert-warn" style="margin-bottom:12px">
  <strong><?= __('Unlinked management accounts') ?></strong> — <?= __('these users have the Accountant or Manager role but no linked profile yet (no salary/attendance). Open the edit form to link them.') ?>
  <ul style="margin:8px 0 0 18px;padding:0"><?php foreach ($unlinked as $u): ?><li><a href="<?= e(url('admin/team/new')) ?>"><strong><?= e($u['full_name']) ?></strong></a> (<?= e($u['username']) ?>) — <?= e(ucfirst($u['role_code'])) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="emp-grid">
  <?php foreach ($profiles as $p): ?>
    <a class="emp-card" href="<?= e(url('admin/team/' . (int)$p['employee_id'])) ?>">
      <?php if (!empty($p['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($p['photo_path'])) ?>" alt="<?= e($p['full_name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($p['full_name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($p['full_name']) ?></div>
      <div class="emp-code"><?= e($p['code'] ?: '—') ?></div>
      <?php if (!empty($p['designation'])): ?><div class="emp-role"><?= e($p['designation']) ?></div><?php endif; ?>
      <div class="emp-role"><span class="badge <?= $p['role_code'] === 'accountant' ? 'blue' : 'amber' ?>"><?= e(strtoupper($p['role_code'])) ?></span> <span class="badge <?= ($p['user_active'] && $p['emp_active']) ? 'green' : 'red' ?>"><?= ($p['user_active'] && $p['emp_active']) ? __('Active') : __('Inactive') ?></span></div>
      <?php if ($p['salary_rate'] > 0): ?><div class="emp-purpose" style="text-align:center;color:#666;font-size:12px"><strong><?= $p['salary_type'] === 'monthly' ? __('Monthly') : __('Daily') ?> <?= money($p['salary_rate']) ?></strong></div><?php else: ?><div class="emp-purpose" style="text-align:center;color:#888;font-size:12px"><?= __('No salary set') ?></div><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php if (!$profiles): ?>
    <div class="panel"><p class="muted"><?= __('No management profiles yet. Create the first Accountant or Manager.') ?></p></div>
  <?php endif; ?>
</div>
