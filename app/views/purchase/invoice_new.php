<?php
$isRaw = !empty($_GET['raw']) || inp('raw');
$title = $isRaw ? __('Add New Purchase — Raw Material') : __('Receive Goods (GRN)');
?>
<?php if ($isRaw): /* ============ SIMPLE RAW MATERIAL PURCHASE ============ */ ?>
<div class="simple-hero no-print" style="padding:14px 18px;margin-bottom:14px">
  <div class="greet">
    <span style="font-size:22px;font-weight:800"><?= __('+ Add New Purchase') ?></span><br>
    <small><?= __('Purchasing Date, Raw Material Name, Quantity (KG/TON auto-calculate), Rate / KG, Total Price, Amount Paid and Remaining.') ?></small>
  </div>
</div>

<div class="panel" style="max-width:980px;margin:0 auto">
  <form method="post" action="<?= e(url('purchase/invoices/new', ['raw' => 1])) ?>">
    <?= csrf_field() ?>
    <?= idempotency_input() ?>
    <input type="hidden" name="is_raw" value="1">
    <input type="hidden" name="discount" value="0.00">
    <input type="hidden" name="warehouse_id" value="1">

    <!-- Top Fields: Date & Supplier & Account -->
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:16px">
      <div>
        <label class="f"><?= __('Purchase Date *') ?></label>
        <input class="f" type="date" name="date" value="<?= e(today()) ?>" required>
      </div>
      <div>
        <label class="f"><?= __('Supplier') ?> <small class="muted" id="suppProdCountHint"></small></label>
        <select class="f" name="supplier_id" id="selSupplier">
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ((int)($selectedSupplierId ?? inp('supplier_id')) === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f"><?= __('Paid From Account') ?></label>
        <select class="f" name="cash_account_id">
          <?php foreach (($accounts ?? []) as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Materials Table with Date / Raw Material Name / Quantity (KG) / Quantity (TON) / Rate / KG / Total Amount -->
    <div class="table-wrap" style="margin-bottom:16px">
      <table class="tbl" id="rawItemsTbl">
        <thead>
          <tr>
            <th style="min-width:220px"><?= __('Raw Material Name *') ?></th>
            <th class="num" style="min-width:130px"><?= __('Quantity (KG) *') ?></th>
            <th class="num" style="min-width:130px"><?= __('Quantity (TON)') ?></th>
            <th class="num" style="min-width:130px"><?= __('Rate / KG (Rs)') ?></th>
            <th class="num" style="min-width:140px"><?= __('Total Amount') ?></th>
            <th style="width:40px"></th>
          </tr>
        </thead>
        <tbody id="rawItemsBody">
          <tr class="raw-row">
            <td>
              <select class="f raw-prod" name="items[product_id][]" required>
                <option value=""><?= __('— select raw material —') ?></option>
                <?php foreach ($rawProducts as $rp): ?>
                  <option value="<?= (int)$rp['id'] ?>"><?= e($rp['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="items[unit][]" value="KG">
              <input type="hidden" name="items[tax_rate][]" value="0.00">
            </td>
            <td>
              <input class="f raw-kg num" type="number" step="0.01" min="0.01" name="items[qty][]" placeholder="0.00" required>
            </td>
            <td>
              <input class="f raw-ton num" type="number" step="0.0001" min="0" placeholder="0.0000">
            </td>
            <td>
              <input class="f raw-rate num" type="number" step="0.01" min="0" name="items[cost][]" placeholder="0.00">
            </td>
            <td>
              <input class="f raw-total num" type="number" step="0.01" min="0" name="items[line_total][]" placeholder="0.00" style="font-weight:700">
            </td>
            <td style="text-align:center">
              <button type="button" class="btn btn-outline btn-sm btn-del-row" style="color:#cf3f2f;padding:4px 8px" title="<?= e(__('Remove')) ?>">✕</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <button type="button" class="btn btn-outline btn-sm" id="btnRawAddLine" style="margin-bottom:20px">
      + <?= __('Add Another Material') ?>
    </button>

    <!-- Payment & Settlement Section: Total Amount, Amount Paid, Remaining -->
    <div style="background:#f8faf9;border:1px solid var(--line);border-radius:12px;padding:16px 20px;margin-bottom:20px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end">
        <div>
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--gray);margin-bottom:6px"><?= __('Total Amount') ?></div>
          <div style="font-size:24px;font-weight:800;color:var(--ink)">
            Rs <span id="dispTotalAmount">0.00</span>
          </div>
        </div>

        <div>
          <label class="f" style="margin-bottom:4px">
            <strong><?= __('Amount Paid') ?></strong>
          </label>
          <div style="display:flex;gap:6px;align-items:center">
            <input class="f num" type="number" step="0.01" min="0" name="amount_paid" id="inpAmountPaid" value="0.00" style="font-size:16px;font-weight:700;color:#1d6530" required>
          </div>
          <div style="display:flex;gap:6px;margin-top:6px">
            <button type="button" class="btn btn-outline btn-sm" id="btnPayFull" style="font-size:11px;padding:3px 8px"><?= __('Paid Full') ?></button>
            <button type="button" class="btn btn-outline btn-sm" id="btnPayZero" style="font-size:11px;padding:3px 8px"><?= __('Credit (0)') ?></button>
          </div>
        </div>

        <div>
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--gray);margin-bottom:6px"><?= __('Remaining Balance') ?></div>
          <div style="font-size:24px;font-weight:800;color:#8f1d13" id="wrapRemaining">
            Rs <span id="dispRemaining">0.00</span>
          </div>
          <small class="muted" id="statusNote"><?= __('Credit / Unpaid') ?></small>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:12px;align-items:center;justify-content:flex-end">
      <a class="btn btn-outline" href="<?= e(url('purchase/raw')) ?>"><?= __('Cancel') ?></a>
      <button class="btn btn-primary btn-xl" type="submit"><?= __('Save Raw Material Purchase') ?> →</button>
    </div>
  </form>
</div>

<script>
(function() {
  var tonFactor = <?= json_encode((float)setting('rm_unit_ton', '1000')) ?> || 1000;
  var supplierProductsMap = <?= $supplierProductsMap ?? '{}' ?>;
  var allRawProducts = <?= json_encode($rawProducts ?? []) ?>;
  var selSupplier = document.getElementById('selSupplier');
  var suppProdCountHint = document.getElementById('suppProdCountHint');
  var tblBody = document.getElementById('rawItemsBody');
  var dispTotal = document.getElementById('dispTotalAmount');
  var inpPaid = document.getElementById('inpAmountPaid');
  var dispRem = document.getElementById('dispRemaining');
  var wrapRem = document.getElementById('wrapRemaining');
  var statusNote = document.getElementById('statusNote');

  function getAvailableProducts() {
    var supId = selSupplier ? parseInt(selSupplier.value, 10) : 0;
    if (supId && supplierProductsMap && supplierProductsMap[supId] && supplierProductsMap[supId].length > 0) {
      var allowed = supplierProductsMap[supId].map(Number);
      return allRawProducts.filter(function(p) { return allowed.indexOf(Number(p.id)) !== -1; });
    }
    return allRawProducts;
  }

  function updateRowOptions(selectEl, keepVal) {
    var prods = getAvailableProducts();
    var currentVal = keepVal !== undefined ? keepVal : selectEl.value;
    selectEl.innerHTML = '<option value=""><?= e(__('— select raw material —')) ?></option>';
    prods.forEach(function(p) {
      var opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.name;
      selectEl.appendChild(opt);
    });
    if (currentVal && selectEl.querySelector('option[value="' + currentVal + '"]')) {
      selectEl.value = currentVal;
    } else {
      selectEl.selectedIndex = 0;
    }
  }

  function filterAllRows() {
    var prods = getAvailableProducts();
    var supId = selSupplier ? parseInt(selSupplier.value, 10) : 0;
    if (suppProdCountHint) {
      if (supId && supplierProductsMap && supplierProductsMap[supId] && supplierProductsMap[supId].length > 0) {
        suppProdCountHint.textContent = '· ' + prods.length + ' <?= e(__('products supplied')) ?>';
        suppProdCountHint.style.color = '#1d6530';
      } else {
        suppProdCountHint.textContent = '· <?= e(__('all raw materials')) ?>';
        suppProdCountHint.style.color = 'var(--gray)';
      }
    }
    var rows = tblBody.querySelectorAll('.raw-row');
    rows.forEach(function(r) {
      var sel = r.querySelector('.raw-prod');
      if (sel) updateRowOptions(sel, sel.value);
    });
  }

  if (selSupplier) {
    selSupplier.addEventListener('change', filterAllRows);
  }

  function calculateAll() {
    var grandTotal = 0;
    var rows = tblBody.querySelectorAll('.raw-row');
    rows.forEach(function(r) {
      var kgInput = r.querySelector('.raw-kg');
      var rateInput = r.querySelector('.raw-rate');
      var totalInput = r.querySelector('.raw-total');
      var kg = parseFloat(kgInput ? kgInput.value : 0) || 0;
      var rate = parseFloat(rateInput ? rateInput.value : 0) || 0;
      var tot = parseFloat(totalInput ? totalInput.value : 0) || 0;
      var line = tot > 0 ? tot : (kg * rate);
      grandTotal += line;
    });

    dispTotal.textContent = grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    var paid = parseFloat(inpPaid.value) || 0;
    var rem = Math.max(0, grandTotal - paid);
    dispRem.textContent = rem.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    if (rem > 0) {
      wrapRem.style.color = '#8f1d13';
      statusNote.textContent = paid > 0 ? '<?= e(__('Partial Payment')) ?>' : '<?= e(__('Unpaid (Credit)')) ?>';
    } else {
      wrapRem.style.color = '#1d6530';
      statusNote.textContent = grandTotal > 0 ? '<?= e(__('Fully Paid')) ?>' : '—';
    }
  }

  // Handle row inputs with bidirectional calculation
  tblBody.addEventListener('input', function(e) {
    var target = e.target;
    var row = target.closest('.raw-row');
    if (!row) return;

    var kgInput = row.querySelector('.raw-kg');
    var tonInput = row.querySelector('.raw-ton');
    var rateInput = row.querySelector('.raw-rate');
    var totalInput = row.querySelector('.raw-total');

    if (target.classList.contains('raw-kg')) {
      var kg = parseFloat(target.value) || 0;
      if (tonInput) tonInput.value = kg > 0 ? (kg / tonFactor).toFixed(4) : '';
      var rate = parseFloat(rateInput.value) || 0;
      var tot = parseFloat(totalInput.value) || 0;
      if (rate > 0) {
        totalInput.value = (kg * rate).toFixed(2);
      } else if (tot > 0 && kg > 0) {
        rateInput.value = (tot / kg).toFixed(2);
      }
    } else if (target.classList.contains('raw-ton')) {
      var ton = parseFloat(target.value) || 0;
      var kg = ton > 0 ? ton * tonFactor : 0;
      if (kgInput) kgInput.value = kg > 0 ? kg.toFixed(2) : '';
      var rate = parseFloat(rateInput.value) || 0;
      var tot = parseFloat(totalInput.value) || 0;
      if (rate > 0) {
        totalInput.value = (kg * rate).toFixed(2);
      } else if (tot > 0 && kg > 0) {
        rateInput.value = (tot / kg).toFixed(2);
      }
    } else if (target.classList.contains('raw-rate')) {
      var rate = parseFloat(target.value) || 0;
      var kg = parseFloat(kgInput.value) || 0;
      if (kg > 0) {
        totalInput.value = (kg * rate).toFixed(2);
      }
    } else if (target.classList.contains('raw-total')) {
      var tot = parseFloat(target.value) || 0;
      var kg = parseFloat(kgInput.value) || 0;
      if (kg > 0) {
        rateInput.value = (tot / kg).toFixed(2);
      }
    }

    calculateAll();
  });

  inpPaid.addEventListener('input', calculateAll);

  // Quick buttons
  document.getElementById('btnPayFull').addEventListener('click', function() {
    var totalText = dispTotal.textContent.replace(/,/g, '');
    inpPaid.value = parseFloat(totalText) || 0;
    calculateAll();
  });

  document.getElementById('btnPayZero').addEventListener('click', function() {
    inpPaid.value = '0.00';
    calculateAll();
  });

  // Add line
  document.getElementById('btnRawAddLine').addEventListener('click', function() {
    var firstRow = tblBody.querySelector('.raw-row');
    var clone = firstRow.cloneNode(true);
    var cloneSelect = clone.querySelector('.raw-prod');
    updateRowOptions(cloneSelect, '');
    clone.querySelector('.raw-kg').value = '';
    clone.querySelector('.raw-ton').value = '';
    clone.querySelector('.raw-rate').value = '';
    clone.querySelector('.raw-total').value = '';
    tblBody.appendChild(clone);
  });

  // Remove row
  tblBody.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-del-row')) {
      var rows = tblBody.querySelectorAll('.raw-row');
      if (rows.length > 1) {
        e.target.closest('.raw-row').remove();
        calculateAll();
      }
    }
  });

  filterAllRows();
  calculateAll();
})();
</script>
<?php else: /* ============ STANDARD GRN (non-raw) ============ */ ?>
<?php if ($po): ?><div class="alert alert-info"><?= __('Receiving against') ?><strong><?= e($po['po_no']) ?></strong><?= __('— quantities pre-filled from the PO.') ?></div><?php endif; ?>
<form method="post" class="panel"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Goods Received Note / Purchase Invoice') ?><span class="muted" style="font-size:12px"><?= __('stock in + journal posted on save') ?></span></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Supplier *') ?></label><select class="f" name="supplier_id" required><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $po && (int)$po['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' — ' . $s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Against PO') ?></label><select class="f" name="po_id" onchange="if(this.value){location='<?= e(url('purchase/invoices/new')) ?>&po_id='+this.value}"><option value=""><?= __('— direct GRN —') ?></option><?php foreach ($pendingPos as $pp): ?><option value="<?= (int)$pp['id'] ?>" <?= $po && (int)$po['id'] === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['po_no'] . ' — ' . $pp['supplier']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Their Invoice / Bill No') ?></label><input class="f" name="supplier_invoice_no" value="<?= e($po ? '' : '') ?>" placeholder="<?= e(__('Supplier\'s own bill number')) ?>"></div>
  <div><label class="f"><?= __('GRN Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Receive Into *') ?></label><select class="f" name="warehouse_id" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['code'] . ' — ' . $w['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Payment Type') ?></label><select class="f" name="payment_type" id="payment_type_purchase"><option value="credit"><?= __('Credit (payable)') ?></option><option value="cash"><?= __('Cash') ?></option></select></div>
  <div id="cashAccountWrap" style="display:none"><label class="f"><?= __('Pay From *') ?></label><select class="f" name="cash_account_id" id="cashAccountSelect"><option value=""><?= __('— select —') ?></option><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Invoice Discount (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="discount" id="invDiscount" value="0.00"></div>
</div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Items') ?><span class="muted" style="font-weight:400"><?= __('(check “Bonus” for supplier free goods — they add stock at zero cost and never increase the payable)') ?></span></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Item') ?></th><th><?= __('Qty') ?></th><th><?= __('Unit') ?></th><th><?= __('Unit Cost') ?></th><th><?= __('Tax %') ?></th><th><?= __('Bonus') ?></th><th class="num"><?= __('Line Total') ?></th><th></th></tr></thead><tbody id="itemsBody">
<?php if ($po && $poItems): foreach ($poItems as $k => $it): ?>
<tr><td><select class="f" name="items[product_id][<?= $k ?>]" required></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][<?= $k ?>]" value="<?= e(qty($it['qty'])) ?>" required></td><td><select class="f item-unit" style="width:74px" name="items[unit][<?= $k ?>]"><option value="KG">KG</option></select></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[cost][<?= $k ?>]" value="<?= e($it['unit_cost']) ?>" required></td><td><input class="f" style="width:65px" type="number" step="0.01" min="0" name="items[tax_rate][<?= $k ?>]" value="0.00"></td><td style="text-align:center"><input type="checkbox" name="items[is_bonus][<?= $k ?>]" value="1"></td><td class="line-total num">0.00</td><td><button type="button" class="btn btn-danger btn-sm" data-remove>✕</button></td></tr>
<?php endforeach; else: ?>
<tr><td><select class="f" name="items[product_id][0]" required><option value=""><?= __('— select product —') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[qty][0]" value="1" required></td><td><select class="f item-unit" style="width:74px" name="items[unit][0]"><option value="KG">KG</option></select></td><td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="items[cost][0]" value="0.00" required></td><td><input class="f" style="width:65px" type="number" step="0.01" min="0" name="items[tax_rate][0]" value="0.00"></td><td style="text-align:center"><input type="checkbox" name="items[is_bonus][0]" value="1"></td><td class="line-total num">0.00</td><td></td></tr>
<?php endif; ?>
</tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addLine"><?= __('+ Add Line') ?></button>
<div style="text-align:right;margin-top:8px" class="mono"><?= __('Subtotal:') ?><strong><?= __('Rs') ?><span id="subTotal">0.00</span></strong><?= __(' · Tax:') ?><strong><?= __('Rs') ?><span id="taxTotal">0.00</span></strong> · <span style="font-size:18px"><?= __('TOTAL:') ?><strong><?= __('Rs') ?><span id="grandTotal2">0.00</span></strong></span></div>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post GRN / Invoice') ?></button><a class="btn btn-outline" href="<?= e(url('purchase/invoices')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
document.getElementById('payment_type_purchase').addEventListener('change', function(){
  var isCash = this.value === 'cash';
  var w = document.getElementById('cashAccountWrap');
  if (w) w.style.display = isCash ? '' : 'none';
  var sel = document.getElementById('cashAccountSelect');
  if (sel) { sel.required = isCash; if (!isCash) sel.value = ''; }
});
</script>
<?php if ($po && $poItems): ?>
<script>
window.__poPrefill = <?= json_encode(array_map(fn($it) => (int)$it['product_id'], $poItems)) ?>;
</script>
<?php endif; ?>
<script>
window.UNIT_KG = <?= json_encode(['KG' => (float)setting('rm_unit_kg', '1'), 'TON' => (float)setting('rm_unit_ton', '1000'), 'MAN' => (float)setting('rm_unit_man', '40')]) ?>;
erpLineGrid({
  tbody: '#itemsBody', products: <?= $products ?>, field: 'items', priceFrom: 'cost', allowBonus: true, unit: 'rm',
  addBtn: '#addLine', subtotalEl: '#subTotal', taxEl: '#taxTotal', totalEl: '#grandTotal2', discountEl: '#invDiscount',
});
// Pre-fill product selects for PO-receive mode
if (window.__poPrefill) {
  document.querySelectorAll('#itemsBody select[name^="items[product_id]"]').forEach((sel, i) => {
    if (window.__poPrefill[i]) sel.value = window.__poPrefill[i];
  });
}
</script>
<?php endif; ?>
