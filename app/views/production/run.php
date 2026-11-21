<?php $title = __('Daily Production Entry'); ?>
<form method="post" action="<?= e(url('production/run')) ?>" class="panel" id="runForm">
  <?= csrf_field() ?>
  <input type="hidden" name="idem_key" value="<?= e(bin2hex(random_bytes(12))) ?>">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <h2 style="margin:0;font-size:20px;font-weight:800">🎯 <?= __('Daily Production Entry') ?></h2>
      <small class="muted"><?= __('Finished goods will be added to stock and raw materials deducted via BOM') ?></small>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('production')) ?>">← <?= __('Back to Production') ?></a>
  </div>

  <!-- Date & Notes -->
  <div class="form-grid mt" style="align-items:start;grid-template-columns:180px 1fr">
    <div>
      <label class="form-label"><?= __('Production Date') ?> <span style="color:red">*</span></label>
      <input class="form-control" type="date" name="date" value="<?= e($today) ?>" required>
    </div>
    <div>
      <label class="form-label"><?= __('Notes (optional)') ?></label>
      <input class="form-control" name="notes" placeholder="<?= e(__('e.g. morning shift, machine 1, batch')) ?>">
    </div>
  </div>

  <!-- Section 1: Finished Goods to Produce -->
  <div class="card mt" style="border:1px solid #bae6fd;border-radius:10px;padding:16px;background:#f0f9ff">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 style="margin:0;font-size:16px;font-weight:700;color:#0369a1">📦 1. <?= __('Finished Goods to Produce') ?></h3>
      <span class="muted" style="font-size:13px"><?= __('Enter produced quantity in packets / cartons') ?></span>
    </div>

    <div class="table-wrap" style="margin:0">
      <table class="data-table" id="itemsTable" style="margin:0;background:#fff">
        <thead>
          <tr>
            <th style="width:36%"><?= __('Finished Product') ?> <span style="color:red">*</span></th>
            <th style="width:130px;text-align:right"><?= __('Quantity (Packets)') ?> <span style="color:red">*</span></th>
            <th style="width:140px"><?= __('Batch Number') ?></th>
            <th><?= __('Line Note') ?></th>
            <th style="width:50px"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:10px">
      <button class="btn btn-primary" type="button" id="addRow">+ <?= __('Add Product Line') ?></button>
      <div style="font-size:14px">
        <?= __('Total produced:') ?> <strong id="runTotal" style="font-size:18px;color:#0369a1">0</strong> <?= __('Packets') ?>
        <span id="runBundles" class="muted" style="font-weight:600">(0 <?= __('Bundles') ?>)</span>
      </div>
    </div>
  </div>

  <!-- Section 2: Raw Material Auto-Deduct via BOM -->
  <div class="card mt" style="border:1px solid #bbf7d0;border-radius:10px;padding:16px;background:#f0fdf4">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
      <div>
        <h3 style="margin:0;font-size:16px;font-weight:700;color:#166534">⚡ 2. <?= __('Raw Material Consumption / Auto-Deduct') ?></h3>
        <small class="muted"><?= __('Stock of consumed raw materials will be deducted automatically') ?></small>
      </div>
      <label style="display:flex;align-items:center;gap:6px;font-weight:700;cursor:pointer;color:#166534">
        <input type="checkbox" name="auto_deduct_bom" id="autoDeductBom" value="1" checked onchange="toggleBomView()">
        <?= __('Auto-Deduct from stock via BOM') ?>
      </label>
    </div>

    <!-- Live BOM Calculation Preview -->
    <div id="bomPreviewWrap" style="background:#fff;border:1px solid #86efac;border-radius:8px;padding:12px;margin-bottom:12px">
      <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:6px">
        📋 <?= __('Calculated Raw Material Requirement:') ?>
      </div>
      <div id="bomLinesSummary" style="font-size:13px;color:#334155">
        <span class="muted"><?= __('Select product and quantity above to calculate raw material consumption.') ?></span>
      </div>
    </div>

    <!-- Optional: Manual extra RM lines -->
    <details id="manualRmDetails" style="margin-top:10px">
      <summary style="font-size:13px;font-weight:700;color:#166534;cursor:pointer">
        ➕ <?= __('Click here to add extra or manual raw materials') ?>
      </summary>
      <div class="table-wrap" style="margin-top:10px">
        <table class="data-table" id="rmTable" style="margin:0;background:#fff">
          <thead>
            <tr>
              <th style="width:36%"><?= __('Raw Material') ?></th>
              <th style="width:130px;text-align:right"><?= __('Quantity (KG)') ?></th>
              <th><?= __('Warehouse') ?></th>
              <th><?= __('Note') ?></th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <button class="btn btn-outline btn-sm mt" type="button" id="addRmRow" style="margin-top:8px">+ <?= __('Add Extra Raw Material') ?></button>
    </details>
  </div>

  <!-- Save Bar -->
  <div class="save-bar" style="margin-top:20px;display:flex;gap:12px;align-items:center">
    <button class="btn btn-primary btn-xl" type="submit" style="font-size:16px;padding:12px 28px">
      💾 <?= __('Save Production & Deduct RM') ?>
    </button>
    <a class="btn btn-outline" href="<?= e(url('production')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>

<script>
var products = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'label' => $p['sku'] . ' — ' . $p['name'], 'unit' => ($p['unit_short'] ?? ''), 'pack' => ($p['pack_size'] ?? '')], $products)) ?>;
var rawMaterials = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'label' => $m['sku'] . ' — ' . $m['name']], $rawMaterials)) ?>;
var warehouses = <?= json_encode(array_map(fn($w) => ['id' => (int)$w['id'], 'name' => $w['name']], $warehouses)) ?>;
var defaultWh = <?= (int)$defaultWh ?>;
var boms = <?= json_encode($boms) ?>;
var perBundle = <?= (float)UnitConverter::packetsPerBundle() ?>;

function addRow() {
  var tr = document.createElement('tr');
  var opts = products.map(function (p) { return '<option value="' + p.id + '">' + p.label + '</option>'; }).join('');
  tr.innerHTML = '<td><select class="form-control prod-select" name="items[product_id][]" required><option value=""><?= __('— Select Product —') ?></option>' + opts + '</select></td>'
    + '<td><input class="form-control qty-in" style="text-align:right;font-weight:800" type="number" step="0.001" min="0.001" name="items[qty][]" required value="0"><div class="muted row-bundle" style="font-size:11px;margin-top:2px;text-align:right">= 0 <?= __('Bundles') ?></div></td>'
    + '<td><input class="form-control" name="items[batch_no][]" placeholder="<?= e(__('e.g. B-' . date('Ymd'))) ?>"></td>'
    + '<td><input class="form-control" name="items[line_notes][]" placeholder="<?= e(__('optional note')) ?>"></td>'
    + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();recalc()">✕</button></td>';
  document.querySelector('#itemsTable tbody').appendChild(tr);
  recalc();
}

function addRmRow() {
  var tr = document.createElement('tr');
  var opts = rawMaterials.map(function (m) { return '<option value="' + m.id + '">' + m.label + '</option>'; }).join('');
  var whOpts = warehouses.map(function (w) { return '<option value="' + w.id + '"' + (w.id === defaultWh ? ' selected' : '') + '>' + w.name + '</option>'; }).join('');
  tr.innerHTML = '<td><select class="form-control" name="rm_items[product_id][]" required><option value=""><?= __('— Select Raw Material —') ?></option>' + opts + '</select></td>'
    + '<td><input class="form-control" style="text-align:right;font-weight:700" type="number" step="0.001" min="0.001" name="rm_items[qty_kg][]" required placeholder="0.000"></td>'
    + '<td><select class="form-control" name="rm_items[warehouse_id][]">' + whOpts + '</select></td>'
    + '<td><input class="form-control" name="rm_items[note][]" placeholder="<?= e(__('Note')) ?>"></td>'
    + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">✕</button></td>';
  document.querySelector('#rmTable tbody').appendChild(tr);
}

function recalc() {
  var total = 0;
  var rmSummary = {};

  document.querySelectorAll('#itemsTable tbody tr').forEach(function (tr) {
    var sel = tr.querySelector('.prod-select');
    var el = tr.querySelector('.qty-in');
    if (!el || !sel) return;
    var q = parseFloat(el.value || '0') || 0;
    var pid = parseInt(sel.value || '0') || 0;
    total += q;
    var hint = tr.querySelector('.row-bundle');
    if (hint) hint.textContent = '= ' + (perBundle > 0 ? (q / perBundle).toFixed(2) : '0') + ' <?= __('Bundles') ?>';

    // Calculate BOM requirement
    if (pid > 0 && q > 0) {
      boms.forEach(function (b) {
        if (parseInt(b.product_id) === pid) {
          var outQ = parseFloat(b.output_qty) || 1;
          var ratio = q / outQ;
          var matId = b.material_id;
          var need = (parseFloat(b.line_qty) || 0) * ratio;
          if (!rmSummary[matId]) {
            rmSummary[matId] = { name: b.material_name, sku: b.material_sku, qty: 0 };
          }
          rmSummary[matId].qty += need;
        }
      });
    }
  });

  document.getElementById('runTotal').textContent = total.toLocaleString(undefined, {maximumFractionDigits: 3});
  document.getElementById('runBundles').textContent = '(' + (perBundle > 0 ? (total / perBundle).toFixed(2) : '0') + ' <?= __('Bundles') ?>)';

  // Render BOM preview
  var wrap = document.getElementById('bomLinesSummary');
  var keys = Object.keys(rmSummary);
  if (keys.length === 0) {
    wrap.innerHTML = '<span class="muted"><?= __('Select product and quantity above to calculate raw material consumption.') ?></span>';
  } else {
    var html = '<ul style="margin:4px 0 0;padding-left:18px;line-height:1.8">';
    keys.forEach(function (k) {
      var item = rmSummary[k];
      html += '<li><strong>' + item.name + '</strong> (' + item.sku + '): <span style="color:#166534;font-weight:800;font-size:14px">' + item.qty.toFixed(3) + ' KG</span> ' + '<?= __('deducted') ?>' + '</li>';
    });
    html += '</ul>';
    wrap.innerHTML = html;
  }
}

function toggleBomView() {
  var chk = document.getElementById('autoDeductBom');
  var box = document.getElementById('bomPreviewWrap');
  if (box) box.style.display = chk.checked ? 'block' : 'none';
}

document.getElementById('addRow').addEventListener('click', addRow);
document.getElementById('addRmRow').addEventListener('click', addRmRow);
document.querySelector('#itemsTable tbody').addEventListener('input', recalc);
document.querySelector('#itemsTable tbody').addEventListener('change', recalc);
addRow();
</script>
