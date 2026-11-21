<?php $title = 'New Claim Receipt' ?>
<div class="pill-toggle" style="margin-bottom:14px">
  <a href="<?= e(url('claims')) ?>"><?= __('Customer Claims') ?></a>
  <a href="<?= e(url('claims/goods')) ?>" class="on"><?= __('Claim Goods') ?></a>
</div>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Claim Receipt') ?><span class="muted" style="font-size:12px"><?= __('accepted goods enter stock at claim terms — damaged/rejected are recorded and never stocked') ?></span></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Supplier *') ?></label><select class="f" name="supplier_id" required><option value=""><?= __('— select —') ?></option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' — ' . $s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Warehouse *') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Against Purchase Invoice (optional)') ?></label><select class="f" name="purchase_invoice_id"><option value=""><?= __('— select —') ?></option><?php foreach ($purchaseInvoices as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_no']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Reason / Claim Note') ?></label><input class="f" name="reason" placeholder="<?= e(__('e.g. damaged in transit; short-shipped cartons')) ?>"></div>
  <div><label class="f"><?= __('Payment Option *') ?></label>
    <select class="f" name="payment_type" id="paymentType">
      <option value="outstanding"><?= __('Outstanding — pay later (supplier payable)') ?></option>
      <option value="cash"><?= __('Cash payment — pay now') ?></option>
    </select></div>
  <div id="payAccountWrap" style="display:none"><label class="f"><?= __('Pay From *') ?></label><select class="f" name="pay_account_id" id="payAccount"><option value=""><?= __('— select account —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
</div>

<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items — Good / Accepted · Damaged · Rejected (claim rate per accepted unit)') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Good Qty') ?></th><th class="num"><?= __('Damaged Qty') ?></th><th class="num"><?= __('Rejected Qty') ?></th><th class="num"><?= __('Claim Rate (Rs)') ?></th><th class="num"><?= __('Good Value') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div style="text-align:right;margin-top:10px"><?= __('Accepted:') ?><strong><span id="totGood">0.000</span><?= __('units') ?></strong><?= __('· Damaged:') ?><strong><span id="totDamaged">0.000</span></strong><?= __('· Rejected:') ?><strong><span id="totRejected">0.000</span></strong><br>
  <span style="font-size:17px"><?= __('Eligible Claim Amount:') ?><strong><?= __('Rs') ?><span id="totClaim">0.00</span></strong></span>
</div>
<p class="help">Only Good/Accepted quantities enter stock (valued at claim rate). Damaged and Rejected quantities are recorded here and excluded from stock and from stock valuation. The claim posts only after Owner/Admin approval. If payment is cash, Rs posts to the selected account immediately on approval; otherwise the amount is added to the supplier's outstanding balance and can be paid (fully or in part) later with approval.</p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Submit Claim (goes for Admin approval)') ?></button><a class="btn btn-outline" href="<?= e(url('claims/goods')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const products = <?= json_encode($products) ?>;
function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `<td><select class="f" name="items[product_id][]" required><option value=""><?= __('— select —') ?></option>${products.map(p=>`<option value="${p.id}">${p.sku} — ${p.name}</option>`).join('')}</select></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0" name="items[good_qty][]" value="0"></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0" name="items[damaged_qty][]" value="0"></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0" name="items[rejected_qty][]" value="0"></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[claim_rate][]" value="0.00"></td><td class="line-claim num">0.00</td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()">✕</button></td>`;
  tr.addEventListener('input', recalc);
  document.querySelector('#itemsTable tbody').appendChild(tr);
}
function recalc(){ let g=0,d=0,r=0,t=0; document.querySelectorAll('#itemsTable tbody tr').forEach(tr => { const gq=parseFloat(tr.querySelector('[name$="[good_qty][]"]').value||0), dq=parseFloat(tr.querySelector('[name$="[damaged_qty][]"]').value||0), rq=parseFloat(tr.querySelector('[name$="[rejected_qty][]"]').value||0), rate=parseFloat(tr.querySelector('[name$="[claim_rate][]"]').value||0); const line=gq*rate; tr.querySelector('.line-claim').textContent=line.toFixed(2); g+=gq; d+=dq; r+=rq; t+=line; }); document.getElementById('totGood').textContent=g.toFixed(3); document.getElementById('totDamaged').textContent=d.toFixed(3); document.getElementById('totRejected').textContent=r.toFixed(3); document.getElementById('totClaim').textContent=t.toFixed(2); }
document.getElementById('addRow').addEventListener('click', addRow);
document.getElementById('paymentType').addEventListener('change', function(){ const cash = this.value === 'cash'; document.getElementById('payAccountWrap').style.display = cash ? '' : 'none'; const pa = document.getElementById('payAccount'); if (cash) pa.required = true; else pa.required = false; });
addRow();
</script>
