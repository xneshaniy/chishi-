<?php $title = __('Factory Use'); ?>
<form method="post" class="simple-form" id="consumptionForm" novalidate>
<?= csrf_field() ?>
<h2><?= __('Use Raw Material') ?><span class="muted" style="font-size:12px;font-weight:400"><?= __('raw materials leave stock into production') ?></span></h2>

<div id="materialRows">
  <div class="big-fields consumption-row" style="border-top:1px solid var(--line);padding-top:14px;margin-top:6px">
    <div class="big-field span-2"><label><?= __('Raw Material *') ?></label><select class="f mat" name="items[product_id][]" required><option value=""><?= __('— pick material —') ?></option><?php foreach ($materials as $m): ?><option value="<?= (int)$m['id'] ?>" data-onhand="<?= e((string)$m['on_hand']) ?>"><?= e($m['sku']) ?> — <?= e($m['name']) ?> (<?= number_format($m['on_hand'], 0) ?> kg)</option><?php endforeach; ?></select></div>
    <div class="big-field"><label><?= __('Used Today (KG) *') ?></label><input class="f used-qty" type="number" step="0.001" min="0" name="items[qty_kg][]" value="0" required style="font-size:20px;font-weight:800;color:var(--green-deep)"></div>
    <div class="big-field"><label><?= __('Available') ?></label><input class="f available" value="—" readonly></div>
    <div class="big-field span-4" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <div><span class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em"><?= __('Remaining') ?></span><br><strong class="remaining-val" style="font-size:18px;color:var(--green-deep)">—</strong></div>
      <button type="button" class="btn btn-danger btn-sm remove-row" style="visibility:hidden">✕</button>
    </div>
  </div>
</div>
<button type="button" class="btn btn-outline btn-sm mt" id="addMaterial"><?= __('+ Add another material') ?></button>

<input type="hidden" name="date" value="<?= e(today()) ?>">
<input type="hidden" name="batch_no" value="FC-<?= e(date('ymdHi')) ?>">
<details class="advanced"><summary><?= __('More options (admin)') ?></summary>
  <div class="big-fields" style="margin-top:12px">
    <div class="big-field"><label><?= __('Batch No') ?></label><input class="f" name="batch_no_manual" placeholder="<?= e(__('e.g. BATCH-2026-001')) ?>" oninput="document.querySelector('[name=batch_no]').value=this.value"></div>
    <div class="big-field span-2"><label><?= __('Details') ?></label><input class="f" name="details" placeholder="<?= e(__('e.g. JUSTI 10s production run')) ?>"></div>
    <div class="big-field"><label><?= __('Production Order (optional)') ?></label><select class="f" name="production_order_id"><option value=""><?= __('— not linked —') ?></option><?php foreach ($released as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['order_no']) ?><?= !empty($o['batch_no']) ? ' · ' . e($o['batch_no']) : '' ?></option><?php endforeach; ?></select></div>
    <div class="big-field span-2"><label><?= __('RM Warehouse') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code']) ?> — <?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="big-field"><label><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. day shift')) ?>"></div>
  </div>
</details>
<div class="save-bar">
  <div class="save-summary"><strong><?= __('Used') ?></strong><span class="amount-due"><span id="totUsed">0.000</span> <?= __('KG') ?></span></div>
  <button class="btn btn-primary btn-xl" type="submit"><?= __('Save → Deduct Stock') ?></button>
</div>
</form>

<script>
(function(){
  var form = document.getElementById('consumptionForm');
  var rows = document.getElementById('materialRows');
  function totalUsed(){
    var t = 0; form.querySelectorAll('.used-qty').forEach(function(i){ t += parseFloat(i.value) || 0; });
    document.getElementById('totUsed').textContent = t.toFixed(3);
  }
  function recalcRow(row){
    var sel = row.querySelector('.mat');
    var opt = sel.selectedOptions[0];
    var onhand = opt && opt.dataset.onhand ? parseFloat(opt.dataset.onhand) : 0;
    var qty = parseFloat(row.querySelector('.used-qty').value) || 0;
    var remain = onhand - qty;
    row.querySelector('.available').value = onhand.toFixed(3) + ' kg';
    var rv = row.querySelector('.remaining-val');
    rv.textContent = remain.toFixed(3) + ' kg';
    rv.style.color = remain < 0 ? 'var(--red)' : 'var(--green-deep)';
    totalUsed();
  }
  function wire(row){
    row.querySelector('.mat').addEventListener('change', function(){ recalcRow(row); });
    row.querySelector('.used-qty').addEventListener('input', function(){ recalcRow(row); });
    row.querySelector('.remove-row').addEventListener('click', function(){ row.remove(); totalUsed(); });
  }
  document.querySelectorAll('.consumption-row').forEach(wire);
  document.getElementById('addMaterial').addEventListener('click', function(){
    var template = rows.querySelector('.consumption-row');
    var clone = template.cloneNode(true);
    clone.querySelector('.mat').selectedIndex = 0;
    clone.querySelector('.used-qty').value = '0';
    clone.querySelector('.available').value = '—';
    clone.querySelector('.remaining-val').textContent = '—';
    clone.querySelector('.remove-row').style.visibility = 'visible';
    rows.appendChild(clone);
    wire(clone);
  });
  form.addEventListener('submit', function(e){
    var bad = false, rowsList = [], idx = 0;
    form.querySelectorAll('.consumption-row').forEach(function(row){
      var sel = row.querySelector('.mat');
      var opt = sel.selectedOptions[0];
      var onhand = opt && opt.dataset.onhand ? parseFloat(opt.dataset.onhand) : 0;
      var qty = parseFloat(row.querySelector('.used-qty').value) || 0;
      if (qty > onhand + 0.0009) bad = true;
      if (qty > 0) idx++;
    });
    if (bad) { e.preventDefault(); window.erpNotify ? window.erpNotify(<?= json_encode(__('One or more materials exceed their available stock.')) ?>, 'error') : null; }
    else if (idx === 0) { e.preventDefault(); window.erpNotify ? window.erpNotify(<?= json_encode(__('Enter at least one material with a quantity.')) ?>, 'error') : null; }
  });
})();
</script>
