<?php
$isEdit = !empty($employee);
$p = $employee ?? [];
$pPhoto = $p['photo_path'] ?? null;
$pImg = $pPhoto ? photo_data_uri($pPhoto) : null;
$presets = ['Labour', 'Manager', 'Accountant', 'Cleaning Staff', 'Office Staff'];
?>
<form method="post" class="panel simple-form" action="<?= e(url($isEdit ? 'employees/' . (int)$p['id'] . '/edit' : 'employees/new')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <h2><?= e($title) ?></h2>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>

  <div class="simple-hero" style="margin-bottom:12px;padding:12px 18px">
    <div style="display:flex;gap:16px;align-items:center">
      <img id="photoPreview" class="profile-photo" style="width:96px;height:96px;border-radius:50%;<?= $pImg ? '' : 'display:none' ?>" alt="<?= e($p['name'] ?? '+') ?>"<?= $pImg ? ' src="' . e($pImg) . '"' : '' ?>>
      <div class="profile-avatar" id="photoAvatar" style="width:96px;height:96px;border-radius:50%;font-size:36px;<?= $pImg ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($p['name'] ?? '+', 0, 1))) ?></div>
      <div>
        <label class="f" for="photoInput" style="display:block;font-weight:700;margin-bottom:6px"><?= __('Profile Picture') ?></label>
        <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user">
        <div class="help" style="margin-top:6px"><?= __('JPG / PNG / WebP, max 2 MB.') ?></div>
      </div>
    </div>
  </div>

  <div class="form-grid">
    <div class="span-2"><label class="f"><?= __('Full Name *') ?></label><input class="f" name="name" required value="<?= e($p['name'] ?? '') ?>" autofocus></div>
    <div><label class="f"><?= __('Employee ID') ?></label><input class="f mono" value="<?= e($isEdit ? $p['code'] : $suggestedCode) ?>" disabled></div>
    <div><label class="f"><?= __('Job Title *') ?></label>
      <select class="f" name="designation" required>
        <option value=""><?= __('— select —') ?></option>
        <?php foreach ($presets as $t): ?><option value="<?= e($t) ?>" <?= ($p['designation'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        <option value="Other" <?= ($p['designation'] ?? '') !== '' && !in_array($p['designation'] ?? '', $presets, true) ? 'selected' : '' ?>><?= __('Other (custom)') ?></option>
      </select>
      <small class="help" style="margin-top:4px"><?= __('Type a custom value in “Other” by typing it here:') ?></small>
      <input class="f" list="job-titles" name="designation_custom" id="jobTitleCustom" value="<?= e(!in_array($p['designation'] ?? '', $presets, true) ? ($p['designation'] ?? '') : '') ?>" placeholder="<?= __('e.g. Driver · Technician · Helper…') ?>" style="margin-top:4px;<?= !in_array($p['designation'] ?? '', $presets, true) || ($p['designation'] ?? '') === 'Other' ? '' : 'display:none' ?>">
      <datalist id="job-titles"><?php foreach ($presets as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    </div>
    <div><label class="f"><?= __('Contact Number') ?></label><input class="f" name="phone" value="<?= e($p['phone'] ?? '') ?>" placeholder="03xx-xxxxxxx"></div>
    <div><label class="f"><?= __('CNIC') ?></label><input class="f" name="cnic" value="<?= e($p['cnic'] ?? '') ?>" placeholder="xxxxx-xxxxxxx-x"></div>
    <div><label class="f"><?= __('Department') ?></label><input class="f" name="department" value="<?= e($p['department'] ?? '') ?>" placeholder="e.g. Factory / Office / Stores"></div>
    <div><label class="f"><?= __('Joining Date') ?></label><input class="f" type="date" name="join_date" value="<?= e($p['join_date'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Salary Payment Frequency') ?> *</label>
      <?php $canSetFin = is_owner_or_admin() || !$isEdit; ?>
      <?php if ($canSetFin): ?>
      <select class="f" name="salary_frequency" required><option value="monthly" <?= (($p['salary_frequency'] ?? $p['salary_type'] ?? 'monthly') === 'monthly') ? 'selected' : '' ?>><?= __('Monthly') ?></option><option value="weekly" <?= (($p['salary_frequency'] ?? $p['salary_type'] ?? '') === 'weekly') ? 'selected' : '' ?>><?= __('Weekly') ?></option></select>
      <?php else: ?>
      <input class="f" disabled value="<?= e(ucfirst($p['salary_frequency'] ?? $p['salary_type'] ?? 'monthly')) ?>">
      <p class="help" style="margin:4px 0 0"><?= __('Only Owner/Admin can change salary settings for existing profiles.') ?></p>
      <input type="hidden" name="salary_frequency" value="<?= e($p['salary_frequency'] ?? $p['salary_type'] ?? 'monthly') ?>">
      <?php endif; ?>
    </div>
    <div><label class="f"><?= __('Salary Rate (Rs)') ?> *</label>
      <?php if ($canSetFin): ?><input class="f" type="number" step="0.01" min="0" name="salary_rate" required value="<?= e($p['salary_rate'] ?? '0.00') ?>">
      <?php else: ?><input class="f" disabled value="<?= e(number_format((float)($p['salary_rate'] ?? 0), 2)) ?>"><input type="hidden" name="salary_rate" value="<?= e($p['salary_rate'] ?? '0') ?>"><?php endif; ?>
    </div>
    <div><label class="f"><?= __('Payment') ?></label><select class="f" name="payment_method"><option value="cash" <?= ($p['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>><?= __('Cash') ?></option><option value="bank" <?= ($p['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>><?= __('Bank') ?></option></select></div>
    <div class="span-2"><label class="f"><?= __('Address') ?></label><input class="f" name="address" value="<?= e($p['address'] ?? '') ?>" placeholder="<?= __('Home address') ?>"></div>
  </div>

  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('CNIC Pictures') ?></h3>
  <div class="big-fields">
    <div class="big-field"><label><?= __('CNIC Front') ?></label><input class="f" type="file" name="cnic_front" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
    <div class="big-field"><label><?= __('CNIC Back') ?></label><input class="f" type="file" name="cnic_back" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
  </div>

  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Other Documents') ?></h3>
  <div class="span-4" style="display:flex;flex-direction:column;gap:10px;margin-bottom:12px">
    <input class="f" type="file" name="documents[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
  </div>

  <div class="save-bar">
    <button class="btn btn-primary btn-xl" type="submit"><?= $isEdit ? __('Save Changes') : __('Add Employee') ?></button>
    <a class="btn btn-outline" href="<?= e(url($isEdit ? 'employees/' . (int)$p['id'] : 'employees')) ?>"><?= __('Cancel') ?></a>
    <?php if ($isEdit && !empty($p['id'])): ?><a class="btn btn-outline" href="<?= e(url('employees/' . (int)$p['id'])) ?>"><?= __('Open Profile') ?></a><?php endif; ?>
  </div>
</form>
<script>
if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');
document.querySelector('[name=designation]').addEventListener('change', function() {
  var custom = document.getElementById('jobTitleCustom');
  if (this.value === 'Other') { custom.style.display = ''; custom.focus(); }
  else { custom.style.display = 'none'; }
});
document.getElementById('jobTitleCustom').addEventListener('input', function() {
  if (this.value.trim()) document.querySelector('[name=designation]').value = 'Other';
});
</script>
