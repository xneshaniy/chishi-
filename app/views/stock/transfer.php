<?php $title = __('Stock Transfer'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('Stock Transfer (location change — no sale, no journal)') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('From Warehouse *') ?></label><select class="f" name="from_warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('To Warehouse *') ?></label><select class="f" name="to_warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Qty') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<p class="help"><?= __('Transfer cost follows the configured costing method (') ?><?= e(cfg('costing_method')) ?><?= __('). Use this for factory→warehouse, warehouse→salesman van, etc.') ?></p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Transfer') ?></button><a class="btn btn-outline" href="<?= e(url('stock/movements')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= $products ?>;
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][]" required><option value=""><?= __('— select —') ?></option>${products.map(p=>`<option value="${p.id}">${p.sku} — ${p.name}</option>`).join('')}</select></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0.001" name="items[qty][]" required></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>`; document.querySelector('#itemsTable tbody').appendChild(tr); }
document.getElementById('addRow').addEventListener('click', addRow);
addRow();
</script>
