<?php $title = __('New Sales Order'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Sales Order') ?></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Customer') ?></label><select class="f" name="customer_id"><option value=""><?= __('— walk-in / counter order —') ?></option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?></select><span class="muted" style="font-size:11px"><?= __('Optional — leave as walk-in for over-the-counter orders.') ?></span></div>
  <div><label class="f"><?= __('Order Date') ?></label><input class="f" type="date" name="order_date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Delivery Date') ?></label><input class="f" type="date" name="delivery_date"></div>
  <div><label class="f"><?= __('Salesman') ?></label><select class="f" name="salesman_id"><option value="">—</option><?php foreach ($salesmen as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes">
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th><?= __('Qty (Packets)') ?></th><th><?= __('Price') ?></th><th><?= __('Line Total') ?></th><th></th></tr></thead><tbody id="itemsBody">
<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __('— select —') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][0]" value="1"></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[price][0]" value="0.00"></td><td class="line-total num">0.00</td><td></td></tr>
</tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div style="text-align:right;margin-top:10px"><strong><?= __('Order Total: Rs') ?><span id="orderTotal">0.00</span></strong></div>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Create Order') ?></button><a class="btn btn-outline" href="<?= e(url('sales/orders')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= $products ?>;
const opts = '<option value="">— select —</option>' + products.map(p => `<option value="${p.id}" data-price="${p.sale_price}">${p.sku} — ${p.name}</option>`).join('');
document.querySelector('#itemsBody select[name^="items[product_id]"]').innerHTML = opts;
let rowKey = 1;
function recalc(){ let t = 0; document.querySelectorAll('#itemsBody tr').forEach(tr => { const q = parseFloat(tr.querySelector('[name*="[qty]"]').value||0), p = parseFloat(tr.querySelector('[name*="[price]"]').value||0); const lt = q*p; tr.querySelector('.line-total').textContent = lt.toFixed(2); t += lt; }); document.getElementById('orderTotal').textContent = t.toFixed(2); }
document.getElementById('itemsBody').addEventListener('input', e => { if (e.target.matches('select[name*="[product_id]"]')) { const o = e.target.selectedOptions[0]; e.target.closest('tr').querySelector('[name*="[price]"]').value = o.dataset.price || '0.00'; } recalc(); });
document.getElementById('addRow').addEventListener('click', () => { const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][${rowKey}]" required>${opts}</select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][${rowKey}]" value="1"></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[price][${rowKey}]" value="0.00"></td><td class="line-total num">0.00</td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()">✕</button></td>`; rowKey++; document.getElementById('itemsBody').appendChild(tr); });
recalc();
</script>
