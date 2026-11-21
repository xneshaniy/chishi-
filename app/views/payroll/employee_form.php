<?php $title = $employee ? 'Edit Employee' : 'New Employee'; ?>
<div class="two-col">
<form method="post" class="panel" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<h2><?= $employee ? 'Edit — ' . e($employee['name']) : 'New Employee' ?></h2>
<?php $pPhoto = $employee['photo_path'] ?? null; $pImg = $pPhoto ? photo_data_uri($pPhoto) : null; ?>
<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin:8px 0 12px">
  <img id="photoPreview" class="profile-photo" alt="<?= e($employee['name'] ?? '+') ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;<?= $pImg ? '' : 'display:none' ?>"<?= $pImg ? ' src="' . e($pImg) . '"' : '' ?>>
  <div class="profile-avatar" id="photoAvatar" style="width:96px;height:96px;border-radius:50%;font-size:38px;<?= $pImg ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($employee['name'] ?? '+', 0, 1))) ?></div>
  <div>
    <label class="f" for="photoInput" style="display:block;font-weight:700;margin-bottom:6px"><?= __('Employee Photo') ?></label>
    <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user">
    <div class="help" style="margin-top:6px"><?= __('JPG / PNG / WebP, max 2 MB.') ?></div>
  </div>
</div>
<div class="form-grid">
  <div><label class="f"><?= __('Code *') ?></label><input class="f" name="code" required value="<?= e($employee['code'] ?? $suggestedCode) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Full Name *') ?></label><input class="f" name="name" required value="<?= e($employee['name'] ?? '') ?>"></div>
  <div><label class="f"><?= __('CNIC') ?></label><input class="f" name="cnic" value="<?= e($employee['cnic'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($employee['phone'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Designation') ?></label><input class="f" name="designation" value="<?= e($employee['designation'] ?? '') ?>" placeholder="<?= e(__('e.g. Machine Operator')) ?>"></div>
  <div><label class="f"><?= __('Department') ?></label><input class="f" name="department" value="<?= e($employee['department'] ?? '') ?>" placeholder="<?= e(__('e.g. Production')) ?>"></div>
  <div><label class="f"><?= __('Join Date') ?></label><input class="f" type="date" name="join_date" value="<?= e($employee['join_date'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Salary Payment Method (Cash/Bank)') ?></label><select class="f" name="payment_method"><option value="cash" <?= ($employee['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>><?= __('Cash') ?></option><option value="bank" <?= ($employee['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>><?= __('Bank') ?></option></select></div>
  <div><label class="f"><?= __('Salary Type') ?></label><select class="f" name="salary_type"><?php foreach (['monthly' => 'Monthly Salary', 'weekly' => 'Weekly Wages', 'daily' => 'Daily Wages', 'hourly' => 'Hourly Wages', 'contract' => 'Fixed Contract'] as $k => $l): ?><option value="<?= $k ?>" <?= ($employee['salary_type'] ?? 'monthly') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Salary Rate (Rs / month, week, day, hour or fixed)') ?><?= $canSalary ? '' : ' <span class="muted">(' . __('Owner/Admin only') . '</span>' ?></label><input class="f" name="salary_rate" type="number" step="0.01" min="0" <?= $canSalary ? 'required' : 'readonly' ?> value="<?= e($employee['salary_rate'] ?? '0.00') ?>"></div>
  <div><label class="f"><?= __('Overtime Rate (Rs/hr) — blank = auto 1.5×') ?></label><input class="f" name="overtime_rate" type="number" step="0.01" min="0" <?= $canSalary ? '' : 'readonly' ?> value="<?= e($employee['overtime_rate'] ?? '') ?>" placeholder="<?= e(__('auto')) ?>"></div>
</div>
<label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= (!$employee || !empty($employee['is_active'])) ? 'checked' : '' ?>><?= __('Active') ?></label>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save Employee') ?></button><a class="btn btn-outline" href="<?= e(url('employees')) ?>"><?= __('Cancel') ?></a><?php if (!empty($employee['id'])): ?><a class="btn btn-outline" href="<?= e(url('employees/' . (int)$employee['id'] . '/ledger')) ?>"><?= __('Salary Ledger') ?></a><?php endif; ?></div>
</form>
<script>if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');</script>
<?php if ($employee): ?>
<div>
  <?php if (Auth::can('advances.request')): ?>
  <div class="panel"><h2><?= __('Salary Advance') ?></h2>
    <p class="help"><?= __('Owner/Admin must approve before the advance is paid out. Deducts automatically from the next salary (full remaining, or cap with a monthly deduction).') ?></p>
    <a class="btn btn-outline" href="<?= e(url('employees/' . (int)$employee['id'] . '/advance')) ?>"><?= __('Request Salary Advance') ?></a>
  </div>
  <?php endif; ?>
  <?php partial('payroll/_advance_ledger', ['employee' => $employee, 'advances' => $advances, 'advanceMax' => $advanceMax]); ?>
</div>
<?php endif; ?>
</div>
