<?php $title = __('Edit Permissions —') . e($role['name']); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= e($role['name']) ?> <span class="muted" style="font-size:12px">(<?= e($role['code']) ?>)</span></h2>
<div class="btn-group mb"><button type="button" class="btn btn-outline btn-sm" onclick="document.querySelectorAll('input[name=&quot;perms[]&quot;]').forEach(c=>c.checked=true)"><?= __('Select all') ?></button>
<button type="button" class="btn btn-outline btn-sm" onclick="document.querySelectorAll('input[name=&quot;perms[]&quot;]').forEach(c=>c.checked=false)"><?= __('Clear all') ?></button></div>
<?php foreach ($byModule as $module => $perms): ?>
<fieldset style="border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:10px">
  <legend style="font-size:12px;font-weight:700;text-transform:uppercase;color:#3d4a41"><?= e($module) ?></legend>
  <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($perms as $p): ?>
    <label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="perms[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $current, true) ? 'checked' : '' ?>> <span><code><?= e($p['code']) ?></code></span></label>
    <?php endforeach; ?>
  </div>
</fieldset>
<?php endforeach; ?>
<div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Save Permissions') ?></button><a class="btn btn-outline" href="<?= e(url('users/roles')) ?>"><?= __('Back') ?></a></div>
</form>
