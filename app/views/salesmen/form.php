<?php $title = $salesman ? 'Edit Salesman' : 'New Salesman'; $sPhoto = $salesman['photo_path'] ?? null; $sImg = $sPhoto ? photo_data_uri($sPhoto) : null; ?>
<form method="post" class="panel" enctype="multipart/form-data"><?= csrf_field() ?>
<h2><?= $salesman ? 'Edit Salesman — ' . e($salesman['code']) : 'New Salesman' ?></h2>
<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin:10px 0 16px">
  <img id="photoPreview" class="profile-photo" alt="<?= e($salesman['name'] ?? '+') ?>" style="<?= $sImg ? '' : 'display:none' ?>"<?= $sImg ? ' src="' . e($sImg) . '"' : '' ?>>
  <div class="profile-avatar" id="photoAvatar" style="<?= $sImg ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($salesman['name'] ?? '+', 0, 1))) ?></div>
  <div>
    <label class="f" for="photoInput" style="display:block;font-weight:700;margin-bottom:6px"><?= __('Salesman Photo') ?></label>
    <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user">
    <div class="help" style="margin-top:6px"><?= __('JPG / PNG / WebP, max 2 MB.') ?></div>
  </div>
</div>

<div class="form-grid">
  <div><label class="f"><?= __('Code *') ?></label><input class="f" name="code" required value="<?= e($salesman['code'] ?? $suggestedCode) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Full Name *') ?></label><input class="f" name="name" required value="<?= e($salesman['name'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($salesman['phone'] ?? '') ?>"></div>
  <div><label class="f"><?= __('CNIC') ?></label><input class="f" name="cnic" value="<?= e($salesman['cnic'] ?? '') ?>" placeholder="xxxxx-xxxxxxx-x"></div>

  <?php $province_id = $salesman['province_id'] ?? null; $city_id = $salesman['city_id'] ?? null; $area_text = $salesman['area'] ?? ''; $area_id = $salesman['area_id'] ?? null; require __DIR__ . '/../partials/location_selects.php'; ?>

  <?php $canSetFin = is_owner_or_admin() || !$salesman; ?>
  <div><label class="f"><?= __('Commission %') ?></label><input class="f" name="commission_percent" type="number" step="0.01" min="0" value="<?= e($salesman['commission_percent'] ?? '2.5') ?>" <?= $canSetFin ? '' : 'disabled' ?>><?php if (!$canSetFin): ?><input type="hidden" name="commission_percent" value="<?= e($salesman['commission_percent'] ?? '0') ?>"><p class="help" style="margin:4px 0 0"><?= __('Only Owner/Admin can change financial settings for existing profiles.') ?></p><?php endif; ?></div>
  <div><label class="f"><?= __('Salary (Rs)') ?></label><input class="f" name="salary" type="number" step="0.01" min="0" value="<?= e($salesman['salary'] ?? '0.00') ?>" <?= $canSetFin ? '' : 'disabled' ?>><?php if (!$canSetFin): ?><input type="hidden" name="salary" value="<?= e($salesman['salary'] ?? '0') ?>"><?php endif; ?></div>
  <div><label class="f"><?= __('Salary Payment Frequency') ?> *</label>
    <?php if ($canSetFin): ?><select class="f" name="salary_frequency" required><option value="monthly" <?= (($salesman['salary_frequency'] ?? 'monthly') === 'monthly') ? 'selected' : '' ?>><?= __('Monthly') ?></option><option value="weekly" <?= (($salesman['salary_frequency'] ?? '') === 'weekly') ? 'selected' : '' ?>><?= __('Weekly') ?></option></select>
    <?php else: ?><input class="f" disabled value="<?= e(ucfirst($salesman['salary_frequency'] ?? 'monthly')) ?>"><input type="hidden" name="salary_frequency" value="<?= e($salesman['salary_frequency'] ?? 'monthly') ?>"><?php endif; ?>
  </div>
  <div><label class="f"><?= __('Joining Date') ?></label><input class="f" type="date" name="join_date" value="<?= e($salesman['join_date'] ?? '') ?>"></div>
</div>

<label class="f mt"><?= __('Address') ?></label><input class="f" name="address" value="<?= e($salesman['address'] ?? '') ?>">

<h3 class="help" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 4px"><?= __('CNIC Pictures') ?></h3>
<div class="form-grid">
  <div><label class="f"><?= __('CNIC Front') ?></label><input class="f" type="file" name="cnic_front" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
  <div><label class="f"><?= __('CNIC Back') ?></label><input class="f" type="file" name="cnic_back" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
</div>

<h3 class="help" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 4px"><?= __('Vehicle (optional)') ?></h3>
<div class="form-grid">
  <div><label class="f"><?= __('Vehicle Type') ?></label><input class="f" name="vehicle_type" value="<?= e($salesman['vehicle_type'] ?? '') ?>" placeholder="<?= e(__('e.g. Rickshaw / Motorbike / Van')) ?>"></div>
  <div><label class="f"><?= __('Vehicle Reg #') ?></label><input class="f" name="vehicle_reg" value="<?= e($salesman['vehicle_reg'] ?? '') ?>" placeholder="<?= e(__('e.g. LEA-1234')) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Vehicle Note') ?></label><input class="f" name="vehicle_note" value="<?= e($salesman['vehicle_note'] ?? '') ?>" placeholder="<?= e(__('e.g. company rickshaw')) ?>"></div>
</div>
<?php if ($salesman && is_owner_or_admin()): ?><label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= !empty($salesman['is_active']) ? 'checked' : '' ?>><?= __('Active') ?></label><?php endif; ?>
<?php if ($salesman): ?><p class="help"><?= __('Sales stock location:') ?><strong><?= e($salesman['name']) ?></strong><?= __('\'s warehouse is created automatically on first save.') ?></p>
<div style="margin-top:10px"><a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$salesman['id'] . '/profile')) ?>"><?= __('Open Salesman Profile →') ?></a></div><?php endif; ?>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save') ?></button><a class="btn btn-outline" href="<?= e(url('salesmen')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');</script>
