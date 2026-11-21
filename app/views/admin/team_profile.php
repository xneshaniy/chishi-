<?php
$remaining = max(0, round((float)$totals['earned'] - (float)$totals['paid'], 2));
$imgSrc = !empty($p['photo_path']) ? photo_data_uri($p['photo_path']) : null;
?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:12px">
  <div class="greet" style="display:flex;gap:16px;align-items:center">
    <?php if ($imgSrc): ?><img class="profile-photo" style="width:96px;height:96px;border-radius:50%;object-fit:cover" src="<?= e($imgSrc) ?>" alt="<?= e($p['full_name']) ?>"><?php else: ?><div class="profile-avatar" style="width:96px;height:96px;border-radius:50%;font-size:38px"><?= e(mb_strtoupper(mb_substr($p['full_name'], 0, 1))) ?></div><?php endif; ?>
    <div>
      <span style="font-size:24px;font-weight:800"><?= e($p['full_name']) ?></span><br><small><?= e($p['code'] ?? '—') ?> · <?= e($p['designation'] ?? '—') ?><?= $p['department'] ? ' · ' . e($p['department']) : '' ?> · <?= __('Joined') ?> <?= e($p['join_date'] ? date_display($p['join_date']) : '—') ?> · <?= __('Phone') ?> <?= e($p['phone'] ?: '—') ?></small><br><span class="badge <?= ($p['user_active'] && $p['emp_active']) ? 'green' : 'red' ?>"><?= ($p['user_active'] && $p['emp_active']) ? __('Active') : __('Inactive') ?></span> <span class="badge <?= ($p['role_code'] ?? '') === 'accountant' ? 'blue' : 'amber' ?>"><?= e(strtoupper($p['role_code'])) ?></span><?php if (!empty($p['force_password_change'])): ?> <span class="badge gray"><?= __('Password change required') ?></span><?php endif; ?></div>
  </div>
  <div class="btn-group no-print" style="flex-wrap:wrap;gap:6px">
    <a class="btn btn-primary" href="<?= e(url('admin/team/' . (int)$p['employee_id'] . '/edit')) ?>"><?= __('Edit Profile') ?></a>
    <form method="post" action="<?= e(url('admin/team/' . (int)$p['employee_id'] . '/toggle')) ?>" data-confirm="<?= e(($p['user_active'] && $p['emp_active']) ? __('Deactivate this account? The user will not be able to log in or be paid until reactivated.') : __('Reactivate this account?')) ?>" style="display:contents">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['employee_id'] ?>">
      <button class="btn btn-outline" type="submit" style="color:var(--red);border-color:#f2b8b5"><?= ($p['user_active'] && $p['emp_active']) ? __('Deactivate Account') : __('Reactivate Account') ?></button>
    </form>
    <a class="btn btn-outline" href="<?= e(url('employees/' . (int)$p['employee_id'] . '/advance')) ?>"><?= __('Give Advance') ?></a>
    <a class="btn btn-outline" href="<?= e(url('employees/' . (int)$p['employee_id'] . '/ledger')) ?>"><?= __('Salary Ledger') ?></a>
    <a class="btn btn-outline" href="<?= e(url('payroll')) ?>"><?= __('Payroll Run') ?></a>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(4,1fr);margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Earned') ?></div><div class="sc-val"><?= money($totals['earned']) ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Paid') ?></div><div class="sc-val"><?= money($totals['paid']) ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Salary Remaining') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($remaining) ?></div><div class="sc-sub"><?= ($p['salary_type'] === 'monthly' ? __('Monthly') : __('Daily')) . ' ' . money($p['salary_rate']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($totals['outstanding']) ?></div><div class="sc-sub"><?= __('of') ?> <?= money($totals['advanced']) ?> <?= __('given') ?></div></div>
</div>

<div class="two-col" style="margin-top:4px">
  <div class="panel">
    <h2><?= __('Set Salary') ?></h2>
    <p class="help"><?= __('Current salary: ') ?><strong><?= $p['salary_type'] === 'monthly' ? __('Monthly') : __('Daily') ?> <?= money($p['salary_rate']) ?></strong></p>
    <form method="post" action="<?= e(url('admin/team/' . (int)$p['employee_id'] . '/salary')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['employee_id'] ?>">
      <div><label class="f"><?= __('Salary Type') ?></label><select class="f" name="salary_type"><option value="monthly" <?= $p['salary_type'] === 'monthly' ? 'selected' : '' ?>><?= __('Monthly') ?></option><option value="daily" <?= $p['salary_type'] === 'daily' ? 'selected' : '' ?>><?= __('Daily') ?></option></select></div>
      <div><label class="f"><?= __('Rate (Rs)') ?> *</label><input class="f" type="number" step="0.01" min="0.01" name="salary_rate" required value="<?= e($p['salary_rate']) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Effective From') ?></label><input class="f" type="date" name="effective_date" value="<?= e(today()) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Note') ?></label><input class="f" name="note" placeholder="<?= e(__('e.g. annual increment')) ?>"></div>
      <div class="span-2"><button class="btn btn-primary" type="submit"><?= __('Save Salary') ?></button></div>
    </form>
  </div>
  <div class="panel">
    <h2><?= __('Salary History') ?></h2>
    <?php if (!$salaryChanges): ?>
      <p class="muted"><?= __('No salary changes yet.') ?></p>
    <?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Effective') ?></th><th class="num"><?= __('From') ?></th><th class="num"><?= __('To') ?></th><th><?= __('Type') ?></th></tr></thead>
        <tbody><?php foreach ($salaryChanges as $sc): ?><tr><td><?= date_display($sc['effective_date']) ?></td><td class="num"><?= money($sc['old_rate'] ?? 0) ?></td><td class="num"><?= money($sc['new_rate']) ?></td><td><?= e($sc['salary_type']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <h2><?= __('Recent Salary Periods') ?></h2>
  <?php if (!$recentPayslips): ?>
    <p class="muted"><?= __('No payslips yet. Generate them from Payroll Run — payment is approval-gated as usual.') ?></p>
  <?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Period') ?></th><th class="num"><?= __('Net Pay') ?></th><th class="num"><?= __('Paid') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th></tr></thead>
      <tbody><?php foreach ($recentPayslips as $r): ?><tr><td><?= date_display($r['period_start']) ?> → <?= date_display($r['period_end']) ?></td><td class="num"><?= money($r['net_pay']) ?></td><td class="num"><?= money($r['amount_paid']) ?></td><td class="num"><?= money(max(0, $r['net_pay'] - $r['amount_paid'])) ?></td><td><span class="badge <?= $r['status'] === 'paid' ? 'green' : ($r['status'] === 'partial' ? 'amber' : 'gray') ?>"><?= e(strtoupper($r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <p class="help" style="margin-top:10px"><?= __('Payments are pending Owner/Admin approval until decided in Admin Console → Approvals.') ?></p>
</div>
