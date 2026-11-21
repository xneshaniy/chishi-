<?php $title = __('New Sales Invoice'); ?>
<form method="post" class="panel" id="invoiceForm"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('New Sales Invoice') ?><span class="muted" style="font-size:12px"><?= __('stock out + journal posted on save') ?></span></h2>
<div class="form-grid">
  <div style="grid-column:span 2">
    <label class="f"><?= __('Salesman Name') ?></label>
    <?php if (is_salesman_only()): ?>
      <input type="hidden" name="salesman_id" value="<?= (int)($mySalesman['id'] ?? 0) ?>">
      <div class="f" style="background:#f3f4f6;display:flex;align-items:center;font-weight:700"><?= e($mySalesman ? $mySalesman['code'] . ' — ' . $mySalesman['name'] : '—') ?></div>
    <?php else: ?>
      <input class="f" id="salesmanFilter" placeholder="<?= e(__('Type to search salesman…')) ?>" autocomplete="off" style="margin-bottom:6px">
      <?php $preselectSm = (int)inp('salesman_id'); ?>
      <select class="f" name="salesman_id" id="salesmanSelect"><option value=""><?= __('— none / counter sale —') ?></option><?php foreach ($salesmen as $s): $hay = strtolower($s['code'] . ' ' . $s['name'] . ' ' . ($s['area'] ?? '')); ?><option value="<?= (int)$s['id'] ?>" data-hay="<?= e($hay) ?>" <?= $preselectSm === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' — ' . $s['name']) ?><?= !empty($s['area']) ? ' — ' . e($s['area']) : '' ?></option><?php endforeach; ?></select>
      <script>
      (function(){ var f = document.getElementById('salesmanFilter'), sel = document.getElementById('salesmanSelect'); if (!f || !sel) return; f.addEventListener('input', function(){ var q = this.value.trim().toLowerCase(); var opts = sel.querySelectorAll('option[data-hay]'); var shown = 0; opts.forEach(function(o){ var show = !q || o.getAttribute('data-hay').indexOf(q) !== -1; o.style.display = show ? '' : 'none'; o.hidden = !show; if (show) shown++; }); if (sel.selectedIndex > 0 && sel.options[sel.selectedIndex].hidden) sel.selectedIndex = 0; }); })();
      </script>
    <?php endif; ?>
    <span class="muted" style="font-size:11px"><?= __('Optional — leave as counter sale for over-the-counter sales. A salesman is required only for credit sales. Selecting a salesman posts this as a Goods Issue at the product issue rate, with quantities in Packets.') ?></span>
  </div>
  <div><label class="f"><?= __('Invoice Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Payment Type') ?></label><select class="f" name="payment_type" id="payment_type"><option value="cash"><?= __('Cash Sale') ?></option><option value="credit"><?= __('Credit Sale') ?></option></select></div>
  <div id="dueDateWrap" style="display:none"><label class="f"><?= __('Due Date (credit)') ?></label><input class="f" type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><abbr title="<?= e(__('Regular paid quantity in Packets')) ?>"><?= __('Regular Qty (Packets)') ?></abbr></th><th style="text-align:center"><label style="font-weight:700;cursor:pointer" title="<?= e(__('Bonus / free quantity in Packets — enable with the checkbox in each row')) ?>"><?= __('Bonus / Free Qty (Packets)') ?></label></th><th><?= __('Rate') ?></th><th class="num"><?= __('Total Amount') ?></th><th></th></tr></thead><tbody id="itemsBody">
<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __('— select product —') ?></option></select></td>
  <td><input class="f num qty-in" style="width:90px" type="number" step="0.001" min="0" name="items[qty][0]" value="1" required></td>
  <td style="text-align:center;white-space:nowrap"><input class="f num bonus-qty-in" style="width:90px" type="number" step="0.001" min="0" name="items[bonus_qty][0]" value="0" disabled placeholder="0"> <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;cursor:pointer;margin-left:2px"><input type="checkbox" class="bonus-toggle" value="1"> <?= __('Bonus') ?></label></td>
  <td><input class="f num rate-in" style="width:110px" type="number" step="0.01" min="0" name="items[price][0]" value="0.00" required></td>
  <td class="line-total num">0.00</td>
  <td>–</td></tr>
</tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addLine"><?= __('+ Add Line') ?></button>
<div id="stockHint" class="stock-hint"></div>
<div class="phys-summary mono" id="physSummary"><?= __('Paid') ?> <strong id="sumPaid">0.000</strong> · <?= __('Bonus') ?> <strong id="sumBonus">0.000</strong> · <?= __('Total quantity') ?> <strong id="sumPhys">0.000</strong> <?= __('Packets') ?> <span class="muted">(1 Bundle = 10 Packets)</span></div>
<div class="form-grid mt">
  <div><label class="f"><?= __('Invoice Discount (Rs, whole invoice)') ?></label><input class="f" type="number" step="0.01" min="0" name="discount" id="invDiscount" value="0.00"></div>
</div>
<div style="text-align:right;margin-top:8px" class="mono"><?= __('Subtotal:') ?><strong><?= __('Rs') ?><span id="subTotal">0.00</span></strong> &nbsp;·&nbsp;
  <span style="font-size:18px"><?= __('TOTAL:') ?><strong><?= __('Rs') ?><span id="grandTotal">0.00</span></strong></span>
</div>
<style>
.stock-hint {margin:8px 0;font-size:12px;min-height:1em;color:#8f1d13;font-weight:600}
.stock-hint.ok {color:#0a4d29}
.qty-in,.bonus-qty-in,.rate-in {text-align:right}
.bonus-qty-in:disabled {background:#f5f5f5;color:#9ca3af}
.phys-summary {margin:6px 0 0;font-size:13px;color:#374151;text-align:right}
.phys-summary strong {color:#111827}
</style>
<div class="btn-group mt"><button class="btn btn-primary" type="submit" id="postBtn"><?= __('Post Invoice') ?></button><a class="btn btn-outline" href="<?= e(url('sales/invoices')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
var erpProducts = <?= $products ?>;
var bonusLbl = <?= json_encode(__('Bonus')) ?>;
(function(){
  function esc2(s){ var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function buildOptions(){
    var html = '<option value="">— select product —</option>';
    erpProducts.forEach(function(p){
      var stockTxt = (p.stock !== undefined && p.stock !== null) ? ' · Stock: ' + p.stock + ' Packets' : '';
      html += '<option value="' + p.id + '" data-price="' + p.sale_price + '" data-issue-rate="' + (p.issue_rate || 0) + '" data-stock="' + (p.stock || 0) + '">' + esc2(p.sku) + ' — ' + esc2(p.name) + stockTxt + '</option>';
    });
    return html;
  }
  var tbody = document.getElementById('itemsBody');
  var salesmanSel = document.getElementById('salesmanSelect');
  function issueMode(){ return !!(salesmanSel && salesmanSel.value); }
  function rateFor(opt){
    if (!opt) return 0;
    return issueMode() ? (opt.getAttribute('data-issue-rate') || '0') : (opt.getAttribute('data-price') || '0');
  }
  function syncRates(){
    tbody.querySelectorAll('tr').forEach(function(tr){
      var sel = tr.querySelector('select[name*="[product_id]"]');
      var rateInput = tr.querySelector('.rate-in');
      if (sel && rateInput && sel.selectedOptions[0]) rateInput.value = Number(rateFor(sel.selectedOptions[0])).toFixed(2);
    });
    recalc();
  }
  var rowKey = 1;
  function bindRow(tr){
    var sel = tr.querySelector('select[name*="[product_id]"]');
    var bonusToggle = tr.querySelector('.bonus-toggle');
    var bonusInput = tr.querySelector('.bonus-qty-in');
    if (bonusToggle && bonusInput) {
      bonusToggle.addEventListener('change', function(){
        bonusInput.disabled = !this.checked;
        if (!this.checked) bonusInput.value = 0;
        recalc();
      });
    }
    var name = sel.getAttribute('name');
    var rowIndex = (name.match(/\[(\d+)\]$/) || [null, '0'])[1];
    tr.querySelector('[data-remove]')?.addEventListener('click', function(){
      if (tbody.querySelectorAll('tr').length > 1) { tr.remove(); recalc(); }
    });
  }
  // Inject product options into the server-rendered first row
  tbody.querySelectorAll('select[name*="[product_id]"]').forEach(function(sel){
    if (sel.options.length <= 1) sel.innerHTML = buildOptions();
  });
  // Row template from the first row (clone) — keeps markup consistent
  function addLine(){
    var k = rowKey++;
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><select class="f" name="items[product_id][' + k + ']" required>' + buildOptions() + '</select></td>' +
      '<td><input class="f num qty-in" style="width:90px" type="number" step="0.001" min="0" name="items[qty][' + k + ']" value="1" required></td>' +
      '<td style="text-align:center;white-space:nowrap"><input class="f num bonus-qty-in" style="width:90px" type="number" step="0.001" min="0" name="items[bonus_qty][' + k + ']" value="0" disabled placeholder="0"> <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;cursor:pointer;margin-left:2px"><input type="checkbox" class="bonus-toggle" value="1"> ' + bonusLbl + '</label></td>' +
      '<td><input class="f num rate-in" style="width:110px" type="number" step="0.01" min="0" name="items[price][' + k + ']" value="0.00" required></td>' +
      '<td class="line-total num">0.00</td>' +
      '<td><button type="button" class="btn btn-danger btn-sm" data-remove>✕</button></td>';
    tbody.appendChild(tr);
    bindRow(tr);
    recalc();
  }
  document.getElementById('addLine').addEventListener('click', addLine);
  function recalc(){
    var subtotal = 0, paidSum = 0, bonusSum = 0;
    tbody.querySelectorAll('tr').forEach(function(tr){
      var qty = parseFloat(tr.querySelector('.qty-in')?.value || 0) || 0;
      var bonusInput = tr.querySelector('.bonus-qty-in');
      var bonus = bonusInput && !bonusInput.disabled ? (parseFloat(bonusInput.value) || 0) : 0;
      var rate = parseFloat(tr.querySelector('.rate-in')?.value || 0) || 0;
      var lineTotal = qty * rate;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      subtotal += lineTotal; paidSum += qty; bonusSum += bonus;
    });
    document.getElementById('subTotal').textContent = subtotal.toFixed(2);
    document.getElementById('sumPaid').textContent = paidSum.toFixed(3);
    document.getElementById('sumBonus').textContent = bonusSum.toFixed(3);
    document.getElementById('sumPhys').textContent = (paidSum + bonusSum).toFixed(3);
    var disc = parseFloat(document.getElementById('invDiscount')?.value || 0) || 0;
    document.getElementById('grandTotal').textContent = (subtotal - disc).toFixed(2);
    awaitStockCheck();
  }
  function awaitStockCheck(){
    var worst = null;
    tbody.querySelectorAll('tr').forEach(function(tr){
      var sel = tr.querySelector('select[name*="[product_id]"]');
      if (!sel || !sel.value) return;
      var p = erpProducts.find(function(x){ return String(x.id) === sel.value; });
      if (!p) return;
      var qty = parseFloat(tr.querySelector('.qty-in')?.value || 0) || 0;
      var bonusInput = tr.querySelector('.bonus-qty-in');
      var bonus = bonusInput && !bonusInput.disabled ? (parseFloat(bonusInput.value) || 0) : 0;
      if (qty + bonus > (Number(p.stock) || 0) + 1e-9) worst = 'Insufficient stock for ' + p.sku + ' — available ' + p.stock + ' Packets, requested ' + (qty + bonus).toFixed(3) + ' Packets';
    });
    var hint = document.getElementById('stockHint');
    if (hint) { hint.textContent = worst || ''; hint.className = 'stock-hint' + (worst ? '' : ' ok'); }
    var postBtn = document.getElementById('postBtn');
    if (postBtn) postBtn.disabled = !!worst;
  }
  tbody.addEventListener('input', recalc);
  tbody.addEventListener('change', function(e){
    if (e.target.matches('select[name*="[product_id]"]')) {
      var opt = e.target.selectedOptions[0];
      var priceInput = e.target.closest('tr').querySelector('.rate-in');
      if (priceInput && opt) priceInput.value = Number(rateFor(opt)).toFixed(2);
    }
    recalc();
  });
  if (salesmanSel) salesmanSel.addEventListener('change', syncRates);
  document.getElementById('invDiscount').addEventListener('input', recalc);
  tbody.querySelectorAll('tr').forEach(bindRow);
  syncRates();
})();
document.getElementById('payment_type').addEventListener('change', function(){
  var isCash = this.value === 'cash';
  document.getElementById('dueDateWrap').style.display = isCash ? 'none' : '';
});
</script>
