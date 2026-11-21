<?php
$isEdit = !empty($profile);
$p = $profile ?? [];
$u = $linkedUser ?? ($isEdit ? ['username' => $p['username'] ?? '', 'email' => $p['email'] ?? '', 'phone' => $p['phone'] ?? ''] : null);
$photo = $p['photo_path'] ?? null;
$imgSrc = $photo ? photo_data_uri($photo) : null;
?>
<form method="post" class="panel simple-form" action="<?= e(url($isEdit ? 'admin/team/' . (int)$p['employee_id'] . '/edit/post' : 'admin/team/new/post')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <h2><?= e($isEdit ? __('Edit Management Profile') : __('Create Accountant / Manager')) ?></h2>
  <?php if ($isEdit && !empty($p['force_password_change'])): ?><div class="alert alert-warn"><?= __('This user must change password at next login.') ?></div><?php endif; ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$p['employee_id'] ?>"><?php endif; ?>

  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin:8px 0 14px">
    <img id="photoPreview" class="profile-photo" style="width:92px;height:92px;border-radius:50%;<?= $imgSrc ? '' : 'display:none' ?>" alt="<?= e($p['full_name'] ?? __('Photo')) ?>"<?= $imgSrc ? ' src="' . e($imgSrc) . '"' : '' ?>>
    <div class="profile-avatar" id="photoAvatar" style="width:92px;height:92px;border-radius:50%;font-size:36px;<?= $imgSrc ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($p['full_name'] ?? '+', 0, 1))) ?></div>
    <div>
      <label class="f" for="photoInput" style="display:block;font-weight:700;margin-bottom:6px"><?= __('Profile Picture') ?></label>
      <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user">
      <div class="help" style="margin-top:6px"><?= __('JPG / PNG / WebP, max 2 MB.') ?></div>
    </div>
  </div>

  <h3 style="font-size:13px;margin:8px 0 8px"><?= __('Login Account') ?></h3>
  <div class="form-grid">
    <?php if ($isEdit): ?>
      <div><label class="f"><?= __('Username') ?></label><input class="f mono" value="<?= e($u['username'] ?? '') ?>" disabled></div>
    <?php else: ?>
      <div><label class="f"><?= __('Username *') ?></label><input class="f" name="username" required placeholder="e.g. accountant1" autocomplete="off"></div>
    <?php endif; ?>
    <div><label class="f"><?= __('Full Name *') ?></label><input class="f" name="full_name" required value="<?= e($p['full_name'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Role *') ?></label><select class="f" name="role" required>
      <option value="manager" <?= ($p['role_code'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
      <option value="accountant" <?= ($p['role_code'] ?? '') === 'accountant' ? 'selected' : '' ?>>Accountant</option>
    </select></div>
    <div><label class="f"><?= __('Branch') ?></label><select class="f" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= ((int)($p['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Email') ?></label><input class="f" type="email" name="email" value="<?= e($p['email'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($p['phone'] ?? '') ?>" placeholder="03xx-xxxxxxx"></div>
    <div class="span-2"><label class="f"><?= $isEdit ? __('New Password (leave blank to keep current)') : 'Password * (min 8)' ?></label><input class="f" type="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>></div>
  </div>

  <h3 style="font-size:13px;margin:14px 0 8px"><?= __('Profile') ?></h3>
  <div class="form-grid">
    <?php if ($isEdit && !empty($p['code'])): ?><div><label class="f"><?= __('Employee ID') ?></label><input class="f mono" value="<?= e($p['code']) ?>" disabled></div><?php endif; ?>
    <div><label class="f"><?= __('CNIC') ?></label><input class="f" name="cnic" value="<?= e($p['cnic'] ?? '') ?>" placeholder="xxxxx-xxxxxxx-x"></div>
    <div><label class="f"><?= __('Job Title / Position') ?></label><input class="f" name="designation" value="<?= e($p['designation'] ?? '') ?>" placeholder="e.g. Senior Accountant"></div>
    <div><label class="f"><?= __('Department') ?></label><input class="f" name="department" value="<?= e($p['department'] ?? 'Management') ?>"></div>
    <div><label class="f"><?= __('Joining Date') ?></label><input class="f" type="date" name="join_date" value="<?= e($p['join_date'] ?? today()) ?>"></div>
    <div><label class="f"><?= __('Salary Type') ?></label><select class="f" name="salary_type"><option value="monthly" <?= ($p['salary_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>><?= __('Monthly') ?></option><option value="daily" <?= ($p['salary_type'] ?? '') === 'daily' ? 'selected' : '' ?>><?= __('Daily') ?></option></select></div>
    <div><label class="f"><?= __('Salary Rate (Rs)') ?> *</label><input class="f" type="number" step="0.01" min="0" name="salary_rate" required value="<?= e($p['salary_rate'] ?? '0.00') ?>"></div>
    <div><label class="f"><?= __('Salary Payment Method (Cash/Bank)') ?></label><select class="f" name="payment_method"><option value="cash" <?= ($p['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>><?= __('Cash') ?></option><option value="bank" <?= ($p['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>><?= __('Bank') ?></option></select></div>
    <div class="span-2"><label class="f"><?= __('Address') ?></label><input class="f" name="address" value="<?= e($p['address'] ?? '') ?>"></div>
  </div>

  <label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= (!$isEdit || !empty($p['emp_active'])) ? 'checked' : '' ?>> <?= __('Active (account + profile)') ?></label>

  <div class="save-bar" style="margin-top:16px">
    <button class="btn btn-primary btn-xl" type="submit"><?= $isEdit ? __('Save Changes') : __('Create Profile') ?></button>
    <a class="btn btn-outline" href="<?= e(url('admin/team')) ?>"><?= __('Cancel') ?></a>
    <?php if ($isEdit && !empty($p['employee_id'])): ?><a class="btn btn-outline" href="<?= e(url('admin/team/' . (int)$p['employee_id'])) ?>"><?= __('Open Profile') ?></a><?php endif; ?>
  </div>
</form>
<script>if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');</script>
