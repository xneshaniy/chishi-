<?php $title = __('New BOM / Recipe'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Bill of Materials') ?></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Finished Product *') ?></label><select class="f" name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Recipe Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. Standard 10s blend v1')) ?>"></div>
  <div><label class="f"><?= __('Output Qty per batch *') ?></label><input class="f" name="output_qty" type="number" step="0.001" min="0.001" required value="1"></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Materials & Packaging') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Material') ?></th><th class="num"><?= __('Qty per batch') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Material') ?></button>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save BOM') ?></button><a class="btn btn-outline" href="<?= e(url('production/boms')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const materials = <?= json_encode($materials) ?>;
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[material_id][]" required><option value=""><?= __('— select material —') ?></option>${materials.map(m=>`<option value="${m.id}">${m.sku} — ${m.name}</option>`).join('')}</select></td><td><input class="f" style="width:100px" type="number" step="0.001" min="0.001" name="items[qty][]" required value="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>`; document.querySelector('#itemsTable tbody').appendChild(tr); }
document.getElementById('addRow').addEventListener('click', addRow);
addRow(); addRow();
</script>
