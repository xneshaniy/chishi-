<?php $title = __('Production Order'); ?>
<div class="simple-wrap">
<form method="post" class="simple-form" id="prodForm"><?= csrf_field() ?>
<h2><?= __('Make Product') ?><span class="muted" style="font-size:12px;font-weight:400"><?= __('FG enters stock in Packets (1 Bundle = 10 Packets)') ?></span></h2>

<div class="big-fields">
  <div class="big-field span-2"><label><?= __('Product *') ?></label><select class="f" name="product_id" id="productId" required><option value=""><?= __('— pick product —') ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" data-pack="<?= e($p['pack_size'] ?? '') ?>" data-sku="<?= e($p['sku']) ?>"><?= e($p['sku']) ?> — <?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="big-field span-2"><label><?= __('Quantity to Produce (Packets) *') ?></label><input class="f" name="qty_planned" id="qtyPlanned" type="number" step="0.001" min="0.001" value="1" required style="font-size:22px;font-weight:800;color:var(--green-deep)"><div class="f-note" id="packHint"><?= __('Stored exactly as Packets. Bundles shown only as an equivalent (1 Bundle = 10 Packets).') ?></div></div>
</div>

<div class="save-bar">
  <div class="save-summary"><strong><?= __('Stock Unit') ?></strong><span class="amount-due" id="packExample">1 Packet = 0.10 Bundle</span></div>
  <button class="btn btn-primary btn-xl" type="submit"><?= __('Save → Start Production') ?></button>
</div>
<input type="hidden" name="auto_release" value="1">

<details class="advanced"><summary><?= __('More options (admin)') ?></summary>
  <div class="big-fields" style="margin-top:12px">
    <div class="big-field span-2"><label><?= __('Recipe / BOM') ?></label><select class="f" name="bom_id" id="bomSelect"><option value=""><?= __('— auto (first BOM of product) —') ?></option><?php foreach ($boms as $b): ?><option value="<?= (int)$b['id'] ?>" data-product="<?= (int)$b['product_id'] ?>"><?= e($b['name']) ?> → <?= e($b['product']) ?> (<?= qty($b['output_qty']) ?>)</option><?php endforeach; ?></select></div>
    <div class="big-field"><label><?= __('Destination Warehouse') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code']) ?> — <?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="big-field"><label><?= __('Batch No') ?></label><input class="f" name="batch_no" id="batchNo" placeholder="<?= e(__('Auto (e.g. BATCH-2026-001)')) ?>"></div>
    <div class="big-field"><label><?= __('Expiry Date') ?></label><input class="f" type="date" name="expiry_date"></div>
  </div>
</details>
</form>
</div>

<script>
(function(){
  var qty = document.getElementById('qtyPlanned');
  var sel = document.getElementById('productId');
  var ex = document.getElementById('packExample');
  var hint = document.getElementById('packHint');
  var bomSel = document.getElementById('bomSelect');
  var batchNo = document.getElementById('batchNo');
  var defaults = { packets_per_bundle: <?= (float)UnitConverter::packetsPerBundle() ?> };
  function packDemo(){
    var q = parseFloat(qty.value) || 1;
    var bundles = q / (defaults.packets_per_bundle || 10);
    ex.textContent = q + ' Packet' + (q === 1 ? '' : 's') + ' = ' + bundles.toFixed(2) + ' Bundles';
    hint.textContent = '1 Bundle = 10 Packets (from Settings → Units & Packaging). Packets are the stock unit.';
  }
  qty.addEventListener('input', packDemo);
  sel.addEventListener('change', function(){
    // auto-select the first BOM matching the chosen product
    var pid = this.value;
    var first = null;
    bomSel.querySelectorAll('option[data-product]').forEach(function(o){ var match = o.getAttribute('data-product') === pid; o.hidden = !match; if (match && !first) first = o; });
    if (first) bomSel.value = first.value; else bomSel.value = '';
    packDemo();
    if (!batchNo.value) batchNo.value = 'PO-' + Date.now().toString().slice(-6);
  });
  packDemo();
})();
</script>
