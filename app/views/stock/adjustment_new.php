<?php $title = __('New Stock Adjustment'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Stock Adjustment (pending approval)') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Warehouse *') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. damaged cartons, count variance, wastage…')) ?>"></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Product') ?></th><th><?= __('Direction') ?></th><th class="num"><?= __('Qty') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Submit for Approval') ?></button><a class="btn btn-outline" href="<?= e(url('stock/adjustments')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= $products ?>;
function productOptions(sel){ let h = '<option value="">— select —</option>'; products.forEach(p => h += `<option value="${p.id}">${p.sku} — ${p.name}</option>`); return h; }
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][]" required>${productOptions()}</select></td><td><select class="f" name="items[direction][]"><option value="in">IN (+)</option><option value="out"><?= __('OUT (−)') ?></option></select></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0.001" name="items[qty][]" required></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>`; document.querySelector('#itemsTable tbody').appendChild(tr); }
document.getElementById('addRow').addEventListener('click', addRow);
addRow();
</script>
