<?php $title = $product ? 'Edit Product' : 'New Product';
$photoPath = $product['photo_path'] ?? null;
$imgSrc = $photoPath ? photo_data_uri($photoPath) : null;
$from = inp('from', '') === 'production' ? 'production' : 'products';
?>
<form method="post" class="panel" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="from" value="<?= e($from) ?>">
  <h2><?= $product ? 'Edit Product — ' . e($product['sku']) : 'New Product' ?></h2>

  <div class="photo-block" style="display:flex;gap:16px;align-items:center;margin-bottom:12px">
    <img id="photoPreview" src="<?= e($imgSrc ?: '') ?>" alt="<?= e($product['name'] ?? 'Product') ?>" class="profile-photo" style="width:96px;height:96px;border-radius:10px;object-fit:cover;<?= $imgSrc ? '' : 'display:none' ?>">
    <div id="photoAvatar" class="profile-avatar" style="width:96px;height:96px;border-radius:10px;font-size:34px;<?= $imgSrc ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($product['name'] ?? '+', 0, 1))) ?></div>
    <div>
      <label class="f" for="photoInput" style="font-weight:700;margin-bottom:4px;display:block"><?= __('Product Picture') ?></label>
      <input class="f" type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp">
      <p class="help"><?= __('JPG, JPEG, PNG or WebP. Stored permanently (thumbnail).') ?></p>
    </div>
  </div>

  <div class="form-grid">
    <div><label class="f"><?= __('SKU *') ?></label><input class="f" name="sku" required value="<?= e($product['sku'] ?? $suggestedSku) ?>"></div>
    <div style="grid-column:span 2"><label class="f"><?= __('Product Name *') ?></label><input class="f" name="name" required value="<?= e($product['name'] ?? '') ?>" placeholder="<?= e(__('e.g. JUSTI Number One 10s')) ?>"></div>
    <div><label class="f"><?= __('Category') ?></label><select class="f" name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($product['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['kind']) ?>)</option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Unit') ?></label><select class="f" name="unit_id"><option value="">—</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>" <?= ((int)($product['unit_id'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Kind') ?></label><select class="f" name="kind"><?php foreach (['finished' => 'Finished Goods', 'raw' => 'Raw Material', 'packaging' => 'Packaging Material'] as $k => $l): ?><option value="<?= $k ?>" <?= (($product['kind'] ?? 'finished') === $k) ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Pack Size') ?></label><input class="f" name="pack_size" value="<?= e($product['pack_size'] ?? '') ?>" placeholder="<?= e(__('e.g. 10 sticks / 50 cartons')) ?>"></div>
    <div><label class="f"><?= __('Purchase Price (Rs)') ?></label><input class="f" name="purchase_price" type="number" step="0.01" min="0" required value="<?= e($product['purchase_price'] ?? '0.00') ?>"></div>
    <div><label class="f"><?= __('Sale Price (Rs) — company shop price') ?></label><input class="f" name="sale_price" type="number" step="0.01" min="0" required value="<?= e($product['sale_price'] ?? '0.00') ?>"></div>
    <div><label class="f"><?= __('Salesman Issue Rate (Rs)') ?></label><input class="f" name="issue_rate" type="number" step="0.01" min="0" value="<?= e($product['issue_rate'] ?? '0.00') ?>"><p class="help"><?= __('Rate the salesman pays the company for stock issued to him') ?></p></div>
    <div><label class="f"><?= __('Minimum Selling Price (Rs) — Approvals if below') ?></label><input class="f" name="min_price" type="number" step="0.01" min="0" value="<?= e($product['min_price'] ?? '0.00') ?>"><p class="help"><?= __('Sales below this price require Owner/Admin approval') ?></p></div>
    <div><label class="f"><?= __('Sales Tax Rate (%)') ?></label><input class="f" name="tax_rate" type="number" step="0.01" min="0" max="100" value="<?= e($product['tax_rate'] ?? cfg('default_tax_rate')) ?>"></div>
    <div><label class="f"><?= __('Reorder Level') ?></label><input class="f" name="reorder_level" type="number" step="0.001" min="0" value="<?= e($product['reorder_level'] ?? '0') ?>"></div>
  </div>
  <div class="mt persist-section" id="packagingSection" style="<?= (($product['kind'] ?? 'finished') === 'finished') ? '' : 'display:none' ?>">
    <h3 class="help" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em"><?= __('Packaging Conversion (FG) — optional') ?></h3>
    <p class="help"><?= __('Leave blank to inherit the company default (Set in Settings → Units & Packaging). Finished-goods stock is stored in Packets; Bundles are shown only as an exact optional equivalent.') ?></p>
    <div class="form-grid">
      <div><label class="f"><?= __('Pieces per Packet (base)') ?></label><input class="f" type="number" step="0.001" min="0" name="pack_pieces" value="<?= e($packaging['pack_pieces'] ?? '') ?>" placeholder="<?= e((string)($defaults['pack_pieces'] > 0 ? $defaults['pack_pieces'] : 'none')) ?>"></div>
      <div><label class="f"><?= __('Packets per Bundle (10 by default)') ?></label><input class="f" type="number" step="0.001" min="0" name="bundle_packs" value="<?= e($packaging['bundle_packs'] ?? '') ?>" placeholder="<?= e((string)($defaults['bundle_packs'] > 0 ? $defaults['bundle_packs'] : 'not configured')) ?>"></div>
      <div><label class="f"><?= __('Bundles per Packet (0 = not used)') ?></label><input class="f" type="number" step="0.001" min="0" name="packet_bundles" value="<?= e($packaging['packet_bundles'] ?? '') ?>" placeholder="<?= e((string)($defaults['packet_bundles'] > 0 ? $defaults['packet_bundles'] : 'not used')) ?>"></div>
    </div>
  </div>
  <div class="mt" style="display:flex;gap:18px">
    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="has_expiry" value="1" <?= !empty($product['has_expiry']) ? 'checked' : '' ?>><?= __('Has expiry date / batch tracking') ?></label>
    <?php if ($product): ?><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= !empty($product['is_active']) ? 'checked' : '' ?>><?= __('Active') ?></label><?php endif; ?>
  </div>
  <div class="mt"><label class="f"><?= __('Description / Notes') ?></label><textarea class="f" name="description" rows="2" placeholder="<?= e(__('e.g. flagship 10s pack, preferred distributor…')) ?>"><?= e($product['description'] ?? '') ?></textarea></div>
  <script>
  if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');
  var kindSel = document.querySelector('[name=kind]');
  var packSection = document.getElementById('packagingSection');
  kindSel.addEventListener('change', function () { packSection.style.display = this.value === 'finished' ? '' : 'none'; });
  </script>
  <div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save') ?></button><a class="btn btn-outline" href="<?= e(url('products')) ?>"><?= __('Cancel') ?></a></div>
</form>
