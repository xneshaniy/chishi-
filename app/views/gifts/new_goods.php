<?php $title = __('New Goods Gift'); $perBundle = UnitConverter::packetsPerBundle(); ?>
<div class="alert alert-info"><?= __('Goods come from FG stock. Stock is NOT deducted until Owner/Admin approves — the approval itself deducts it exactly once. Quantities are in Packets; a Bundle is the optional 10-Packet equivalent.') ?></div>
<form method="post" class="panel simple-form print-area" id="goodsForm"><?= csrf_field() ?>
  <h2><?= __('Goods Gift') ?></h2>
  <div class="form-grid">
    <div><label class="f"><?= __('Recipient name *') ?></label><input class="f" name="recipient_name" required placeholder="<?= e(__('Full name of the person')) ?>"></div>
    <div><label class="f"><?= __('Recipient type') ?></label><select class="f" name="recipient_type"><option value="employee"><?= __('Employee') ?></option><option value="relative"><?= __('Relative') ?></option><option value="customer"><?= __('Customer') ?></option><option value="guest" selected><?= __('Guest') ?></option><option value="other"><?= __('Other') ?></option></select></div>
    <div><label class="f"><?= __('Gift date *') ?></label><input class="f" type="date" name="gift_date" required value="<?= e(today()) ?>"></div>

    <div class="span-2"><label class="f"><?= __('Product *') ?></label>
      <select class="f" name="product_id" id="productId" required>
        <option value=""><?= __('Select finished good…') ?></option>
        <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> · <?= e($p['sku']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Unit *') ?></label>
      <select class="f" name="qty_unit" id="qtyUnit" required>
        <option value="packet" selected><?= __('Packets (base)') ?></option>
        <option value="bundle"><?= __('Bundles') ?></option>
      </select>
    </div>
    <div><label class="f"><?= __('Quantity (Packets) *') ?></label><input class="f" type="number" name="qty" id="qty" min="0.001" step="0.001" required placeholder="<?= e(__('e.g. 5')) ?>"></div>

    <div class="span-2"><div class="stat-chip" style="border-color:var(--amber,#e8a13a)"><div class="sc-lbl"><?= __('Stock deducted on approval') ?></div><div class="sc-val" id="preview"><?= __('—') ?></div></div></div>

    <div class="span-2"><label class="f"><?= __('Reason / occasion') ?></label><input class="f" name="occasion" placeholder="<?= e(__('e.g. Eid gift, marriage, personal favor')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description') ?></label><textarea class="f" name="description" rows="2"></textarea></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2"></textarea></div>
  </div>
  <div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Record Gift (pending approval)') ?></button><a class="btn btn-outline" href="<?= e(url('gifts')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
(function(){
  var perBundle = <?= json_encode($perBundle) ?>;
  var unit = document.getElementById('qtyUnit'); var qty = document.getElementById('qty'); var out = document.getElementById('preview');
  function packets() {
    var q = parseFloat(qty.value || '0');
    if (!(q > 0)) return null;
    return (unit.value === 'bundle') ? q * perBundle : q;
  }
  function render(){
    var n = packets();
    if (n === null) { out.textContent = '—'; return; }
    out.textContent = n.toLocaleString('en-US', {maximumFractionDigits:3}) + ' Packets · '
      + (n / perBundle).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' Bundles';
  }
  [unit, qty].forEach(function(el){ el.addEventListener('input', render); el.addEventListener('change', render); });
  render();
})();
</script>
