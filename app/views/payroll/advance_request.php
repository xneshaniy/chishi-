<?php $title = __('Request Advance') . ' — ' . e($employee['name']); ?>
<div class="two-col">
<div class="panel">
  <h2><?= __('Employee') ?></h2>
  <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
    <?php if (!empty($employee['photo_path'])): ?><img class="emp-photo" src="<?= e(photo_data_uri($employee['photo_path'])) ?>" alt="<?= e($employee['name']) ?>" style="width:72px;height:72px;margin:0"><?php else: ?><div class="emp-avatar" style="width:72px;height:72px;font-size:26px;margin:0"><?= e(mb_strtoupper(mb_substr($employee['name'], 0, 1))) ?></div><?php endif; ?>
    <div><strong style="font-size:16px"><?= e($employee['name']) ?></strong><br><span class="muted"><?= e($employee['code']) ?> · <?= e($employee['designation'] ?? '—') ?></span></div>
  </div>
  <p class="muted" style="font-size:12px;margin-bottom:4px"><?= e($employee['designation'] ?? '—') ?> · <?= e($employee['department'] ?? '—') ?></p>
  <p class="muted" style="font-size:12px"><?= __('Join Date:') ?><?= date_display($employee['join_date']) ?><?= __('Salary:') ?><?= strtoupper(e($employee['salary_type'])) ?><?= __('Rs') ?><?= number_format($employee['salary_rate'], 2) ?>/<?= strtoupper(e($employee['salary_type'] === 'contract' ? 'fixed' : substr($employee['salary_type'], 0, 3))) ?></p>
  <p class="help"><?= __('Advance outstanding:') ?><strong><?= money($advanceOutstanding) ?></strong></p>
  <div class="btn-group mt"><a class="btn btn-outline" href="<?= e(url('employees')) ?>"><?= __('Back to Employees') ?></a><a class="btn btn-outline" href="<?= e(url('employees/' . (int)$employee['id'] . '/ledger')) ?>"><?= __('Salary Ledger') ?></a></div>
</div>
<div>
  <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/advance')) ?>" class="panel"><?= csrf_field() ?>
    <h2><?= __('Request Salary Advance') ?></h2>
    <p class="help"><?= __('Owner/Admin must approve before the advance is paid out. Deducts automatically from the next salary (full remaining, or cap with a monthly deduction).') ?></p>
    <div class="form-grid">
      <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
      <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
      <div><label class="f"><?= __('Pay Out From *') ?></label><select class="f" name="account_id" required><option value=""><?= __('— select —') ?></option><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
      <div><label class="f"><?= __('Monthly Deduction Cap (Rs) — optional') ?></label><input class="f" type="number" step="0.01" min="0" name="monthly_deduction" placeholder="<?= e(__('blank = deduct full remaining')) ?>"></div>
    </div>
    <label class="f mt"><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. medical emergency')) ?>">
    <?php if ($advanceMax > 0): ?><p class="help"><?= __('Maximum advance allowed for non-admin requesters:') ?><strong><?= money($advanceMax) ?></strong></p><?php endif; ?>
    <button class="btn btn-outline mt" type="submit"><?= __('Request Advance (goes to Admin)') ?></button>
  </form>
</div>
</div>
<?php partial('payroll/_advance_ledger', ['employee' => $employee, 'advances' => $advances, 'advanceMax' => $advanceMax]); ?>
