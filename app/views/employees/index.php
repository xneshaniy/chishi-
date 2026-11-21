<?php $title = __('Employees'); ?>
<div class="simple-hero no-print" style="padding:14px 18px">
  <div class="greet" style="display:flex;gap:16px;align-items:center">
    <div>
      <span style="font-size:22px;font-weight:800"><?= __('Employees') ?></span><br><small><?= count($employees) ?> <?= __('people') ?> · <?= $active ?> <?= __('active') ?></small>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (is_owner_or_admin()): ?><a class="btn btn-outline btn-xl" href="<?= e(url('records', ['tab' => 'employees'])) ?>">📁 <?= __('Records (Archive)') ?></a><?php endif; ?>
    <?php if (Auth::can('employees.create')): ?><a class="btn btn-primary btn-xl" href="<?= e(url('employees/new')) ?>">+ <?= __('Add Employee') ?></a><?php endif; ?>
  </div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="employees">
  <input name="q" placeholder="<?= e(__('Search name / ID / CNIC / designation…')) ?>" value="<?= e($q) ?>" style="min-width:260px">
  <select class="f" name="JobTitle" style="min-width:180px">
    <option value=""><?= __('All job titles') ?></option>
    <?php foreach ($jobTitles as $t): ?><option value="<?= e($t) ?>" <?= $jobTitle === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
  </select>
  <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="show_inactive" value="1" <?= !empty($showInactive) ? 'checked' : '' ?>><?= __('Show inactive') ?></label>
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
</form>

<?php if (!empty($counts) && $jobTitle === '' && $q === ''): ?>
  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));margin-bottom:12px">
    <?php foreach (array_slice($counts, 0, 8) as $c): ?>
      <a class="stat-chip" href="<?= e(url('employees?JobTitle=' . urlencode($c['designation']))) ?>" style="text-decoration:none;color:inherit">
        <div class="sc-lbl"><?= e($c['designation']) ?></div>
        <div class="sc-val"><?= (int)$c['n'] ?></div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="emp-grid">
  <?php foreach ($employees as $e): ?>
    <a class="emp-card" href="<?= e(url('employees/' . (int)$e['id'])) ?>">
      <?php if (!empty($e['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($e['photo_path'])) ?>" alt="<?= e($e['name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($e['name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($e['name']) ?></div>
      <div class="emp-code"><?= e($e['code']) ?></div>
      <div class="emp-role"><?= e($e['designation'] ?: __('Employee')) ?></div>
      <?php if (!empty($e['phone'])): ?><div class="emp-role">📞 <?= e($e['phone']) ?></div><?php endif; ?>
      <div class="emp-role"><span class="badge <?= $e['is_active'] ? 'green' : 'red' ?>"><?= $e['is_active'] ? __('Active') : __('Inactive') ?></span></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$employees): ?><div class="panel"><p class="muted"><?= __('No employees yet. Click “Add Employee” to register one.') ?></p></div><?php endif; ?>
</div>
