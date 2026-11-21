<?php $title = __('Salary'); ?>
<div class="simple-hero no-print" style="padding:14px 18px">
  <div class="greet" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div>
      <span style="font-size:22px;font-weight:800"><?= __('Salary') ?></span><br><small><?= __('Combined list of every person in the ERP (salesmen, labour, employees)') ?></small>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total People') ?></div><div class="sc-val"><?= (int)$totalPeople ?></div><div class="sc-sub"><?= __('shown in list') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Earned') ?></div><div class="sc-val"><?= money($totalEarned) ?></div><div class="sc-sub"><?= $month ? e(date_display($month . '-01')) : __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Paid') ?></div><div class="sc-val"><?= money($totalPaid) ?></div><div class="sc-sub"><?= __('approved payments only') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Salary Remaining') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($totalRemaining) ?></div><div class="sc-sub"><?= __('outstanding') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($advancesOut) ?></div><div class="sc-sub"><?= __('salary advances given') ?></div></div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="salary">
  <input name="q" placeholder="<?= e(__('Search by name / ID / phone…')) ?>" value="<?= e($q) ?>" style="min-width:220px">
  <select class="f" name="kind" style="min-width:150px">
    <option value="all" <?= $kind === 'all' ? 'selected' : '' ?>><?= __('All types') ?></option>
    <option value="salesman" <?= $kind === 'salesman' ? 'selected' : '' ?>><?= __('Salesman') ?></option>
    <option value="labour" <?= $kind === 'labour' ? 'selected' : '' ?>><?= __('Labour') ?></option>
    <option value="employee" <?= $kind === 'employee' ? 'selected' : '' ?>><?= __('Employee') ?></option>
  </select>
  <select class="f" name="designation" style="min-width:160px">
    <option value=""><?= __('All designations') ?></option>
    <?php foreach ($designations as $d): ?><option value="<?= e($d) ?>" <?= $designation === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
  </select>
  <select class="f" name="status" style="min-width:160px">
    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= __('All statuses') ?></option>
    <option value="settled" <?= $status === 'settled' ? 'selected' : '' ?>><?= __('Fully paid') ?></option>
    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>><?= __('Partially paid') ?></option>
    <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>><?= __('Unpaid') ?></option>
    <option value="no_salary" <?= $status === 'no_salary' ? 'selected' : '' ?>><?= __('No salary set') ?></option>
  </select>
  <input type="month" class="f" name="month" value="<?= e($month ?? '') ?>" title="<?= e(__('Scope the earned/paid figures to one month')) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salary')) ?>"><?= __('Reset') ?></a>
</form>

<div class="emp-grid">
  <?php foreach ($rows as $r): ?>
    <a class="emp-card" href="<?= e(url('salary/' . $r['person_type'] . '/' . (int)$r['id'] . ($month ? '?month=' . urlencode($month) : ''))) ?>">
      <?php if (!empty($r['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($r['photo_path'])) ?>" alt="<?= e($r['name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($r['name']) ?></div>
      <div class="emp-code"><?= e($r['code']) ?></div>
      <div class="emp-role"><span class="badge <?= $r['kind'] === 'salesman' ? 'blue' : ($r['kind'] === 'labour' ? 'amber' : 'green') ?>"><?= e(strtoupper(PeopleDirectory::kindLabel($r['kind']))) ?></span> <?= e($r['designation']) ?></div>
      <?php if (!empty($r['phone'])): ?><div class="emp-role">📞 <?= e($r['phone']) ?></div><?php endif; ?>
      <div class="emp-role"><?= __('Salary:') ?> <strong><?= money($r['rate']) ?></strong> / <?= ($r['salary_frequency'] ?? $r['salary_type'] ?? 'monthly') === 'weekly' ? __('weekly') : __('monthly') ?></div>
      <div class="emp-role"><?= __('Paid:') ?> <?= money($r['paid']) ?> · <span style="color:<?= $r['remaining'] > 0 ? '#8f1d13' : '#1d6530' ?>"><?= __('Remaining:') ?> <?= money(max(0, $r['remaining'])) ?></span></div>
      <div class="emp-role"><span class="badge <?= $r['status'] === 'settled' ? 'green' : ($r['status'] === 'partial' ? 'amber' : ($r['status'] === 'unpaid' ? 'red' : 'gray')) ?>"><?= e(strtoupper(str_replace('_', ' ', $r['status']))) ?></span></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel" style="grid-column:1/-1"><p class="muted"><?= __('No matching person found. Adjust the filters.') ?></p></div><?php endif; ?>
</div>
