<?php $title = __('Opening Stock'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('Opening Stock (quantities & cost)') ?></h2>
<div class="alert alert-info"><?= __('Posts') ?><strong><?= __('Dr Inventory / Cr Owner Capital') ?></strong><?= __('for the total value. Use for your starting inventory only — it posts a single journal for the whole batch.') ?></div>
<div class="form-grid">
  <div><label class="f"><?= __('Warehouse *') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Unit Cost (Rs)') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Opening Stock') ?></button><a class="btn btn-outline" href="<?= e(url('dashboard')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= $products ?>;
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][]" required><option value=""><?= __('— select —') ?></option>${products.map(p=>`<option value="${p.id}" data-cost="${p.purchase_price}">${p.sku} — ${p.name}</option>`).join('')}</select></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0.001" name="items[qty][]" required></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="items[cost][]" value="0.00"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>`;
  tr.querySelector('select').addEventListener('change', function(){ const c = this.selectedOptions[0]?.dataset.cost; if (c) tr.querySelector('[name$="[cost][]"]').value = c; });
  document.querySelector('#itemsTable tbody').appendChild(tr); }
document.getElementById('addRow').addEventListener('click', addRow);
addRow();
</script>
