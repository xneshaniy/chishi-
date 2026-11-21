<?php $title = __('New Purchase Order'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Purchase Order') ?></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Supplier *') ?></label><select class="f" name="supplier_id" required><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' — ' . $s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Order Date') ?></label><input class="f" type="date" name="order_date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Expected Delivery') ?></label><input class="f" type="date" name="expected_date"></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes">
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Material') ?></th><th><?= __('Qty') ?></th><th><?= __('Unit Cost') ?></th><th><?= __('Line Total') ?></th><th></th></tr></thead><tbody id="itemsBody">
<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __('— select —') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][0]" value="1"></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[cost][0]" value="0.00"></td><td class="line-total num">0.00</td><td></td></tr>
</tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div style="text-align:right;margin-top:10px"><strong><?= __('PO Total: Rs') ?><span id="orderTotal">0.00</span></strong></div>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Create PO') ?></button><a class="btn btn-outline" href="<?= e(url('purchase/orders')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= $products ?>;
const opts = '<option value="">— select —</option>' + products.map(p => `<option value="${p.id}" data-cost="${p.purchase_price}">${p.sku} — ${p.name}</option>`).join('');
document.querySelector('#itemsBody select[name^="items[product_id]"]').innerHTML = opts;
let rowKey = 1;
function recalc(){ let t = 0; document.querySelectorAll('#itemsBody tr').forEach(tr => { const q = parseFloat(tr.querySelector('[name*="[qty]"]').value||0), c = parseFloat(tr.querySelector('[name*="[cost]"]').value||0); const lt = q*c; tr.querySelector('.line-total').textContent = lt.toFixed(2); t += lt; }); document.getElementById('orderTotal').textContent = t.toFixed(2); }
document.getElementById('itemsBody').addEventListener('input', e => { if (e.target.matches('select[name*="[product_id]"]')) { const o = e.target.selectedOptions[0]; e.target.closest('tr').querySelector('[name*="[cost]"]').value = o.dataset.cost || '0.00'; } recalc(); });
document.getElementById('addRow').addEventListener('click', () => { const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][${rowKey}]" required>${opts}</select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][${rowKey}]" value="1"></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[cost][${rowKey}]" value="0.00"></td><td class="line-total num">0.00</td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()">✕</button></td>`; rowKey++; document.getElementById('itemsBody').appendChild(tr); });
recalc();
</script>
