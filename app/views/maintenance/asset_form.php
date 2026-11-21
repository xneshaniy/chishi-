<?php $title = $asset ? __('Edit Maintenance Asset') : __('New Maintenance Asset');
  $pPhoto = $asset ? ($asset['photo_path'] ?? null) : null;
  $imgSrc = $pPhoto ? photo_data_uri($pPhoto) : null;
  $curStatus = $asset['status'] ?? ($asset ? ((int)$asset['is_active'] ? 'active' : 'inactive') : 'active');
?>
<form method="post" enctype="multipart/form-data" class="panel"><?= csrf_field() ?>
<h2><?= $asset ? __('Edit Asset') : __('Register New Asset') ?></h2>
<?php if ($asset): ?>
<input type="hidden" name="id" value="<?= (int)$asset['id'] ?>">
<?php endif; ?>
<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin:8px 0 14px">
  <?php if ($imgSrc): ?><img id="photoPreview" src="<?= e($imgSrc) ?>" alt="<?= e($asset['name'] ?? '') ?>" style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid var(--line)"><?php else: ?><div id="photoAvatar" class="profile-avatar" style="width:120px;height:120px;font-size:44px;border-radius:12px"><?= e(mb_strtoupper(mb_substr($asset['name'] ?? 'A', 0, 1))) ?></div><?php endif; ?>
  <div>
    <label class="f" for="photoInput"><?= __('Asset Photo') ?></label>
    <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp">
    <span class="muted" style="font-size:11px"><?= __('JPG / PNG / WebP, max 2 MB') ?></span>
  </div>
</div>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Machine / Equipment Name *') ?></label><input class="f" name="name" required value="<?= e($asset['name'] ?? '') ?>" placeholder="<?= e(__('e.g. Grinding Machine #2')) ?>"></div>
  <div><label class="f"><?= __('Asset ID / Code') ?></label><input class="f" name="asset_no" value="<?= e($asset['asset_no'] ?? '') ?>" placeholder="<?= e(__('e.g. MAC-014')) ?>"></div>
  <div><label class="f"><?= __('Asset Category') ?></label><input class="f" name="category" value="<?= e($asset['category'] ?? '') ?>" placeholder="<?= e(__('e.g. Grinding / Packing / Electrical')) ?>"></div>
  <div><label class="f"><?= __('Purchase Date') ?></label><input class="f" type="date" name="purchase_date" value="<?= e($asset['purchase_date'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Department') ?></label><input class="f" name="department" value="<?= e($asset['department'] ?? '') ?>" placeholder="<?= e(__('e.g. Packing')) ?>"></div>
  <div><label class="f"><?= __('Location') ?></label><input class="f" name="location" value="<?= e($asset['location'] ?? '') ?>" placeholder="<?= e(__('e.g. Main factory, Line 2')) ?>"></div>
  <div><label class="f"><?= __('Status') ?></label><select class="f" name="status"><?php foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'retired' => __('Retired')] as $k => $l): ?><option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Description') ?></label><textarea class="f" name="description" rows="2" placeholder="<?= e(__('e.g. Grinding unit, 500 kg capacity')) ?>"><?= e($asset['description'] ?? '') ?></textarea></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2" placeholder="<?= e(__('Optional notes…')) ?>"><?= e($asset['notes'] ?? '') ?></textarea></div>
</div>
<div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Save Asset') ?></button><a class="btn btn-outline" href="<?= e(url($asset ? 'maintenance/assets/' . (int)$asset['id'] : 'maintenance/assets')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>window.PHOTO_PREVIEW=document.getElementById('photoInput');document.getElementById('photoInput')?.addEventListener('change',function(e){var img=document.getElementById('photoPreview')||document.getElementById('photoAvatar');var f=e.target.files[0];if(f&&img){if(img.tagName==='IMG'){img.src=URL.createObjectURL(f);}else{img.textContent='+';}}});</script>
