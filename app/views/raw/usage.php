<?php
$title = __('Daily Raw Material Usage');

// totals for today
$todayKg    = 0.0;
$todayCount = 0;
foreach ($entries as $e) {
    if ($e['status'] === 'posted') { $todayKg += (float)$e['qty_kg']; }
    $todayCount++;
}

// current-month total (from DB via controller — fallback if not passed)
$monthKg = 0.0;
if (function_exists('db_val')) {
    $monthKg = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE status='posted' AND usage_date >= ? AND usage_date <= ?", [date('Y-m-01'), date('Y-m-t')]);
}

// monthly date-range summary (last 30 days)
$summaryFrom = date('Y-m-01');
$summaryTo   = today();
$usageMat    = intv(inp('usage_mat'));
$summaryFrom = inp('summary_from', $summaryFrom);
$summaryTo   = inp('summary_to',   $summaryTo);

$rptWhere  = ["rdu.usage_date BETWEEN ? AND ?"]; $rptParams = [$summaryFrom, $summaryTo];
if ($usageMat > 0) { $rptWhere[] = "rdu.product_id = ?"; $rptParams[] = $usageMat; }
$summary = db_all("SELECT rdu.usage_date, p.name AS product_name, p.sku,
                          COALESCE(SUM(CASE WHEN rdu.status='posted' THEN rdu.qty_kg ELSE 0 END),0) AS total_kg,
                          COUNT(*) AS entries
                   FROM rm_daily_usage rdu
                   JOIN products p ON p.id = rdu.product_id
                   WHERE " . implode(' AND ', $rptWhere) . "
                   GROUP BY rdu.usage_date, rdu.product_id
                   ORDER BY rdu.usage_date DESC, total_kg DESC", $rptParams);
$rptTotalKg = array_sum(array_column($summary, 'total_kg'));
?>

<!-- ═══════════ PAGE HERO ═══════════ -->
<div class="simple-hero no-print" style="padding:14px 20px 0;margin-bottom:0">
  <div class="greet">
    <span style="font-size:22px;font-weight:800">📋 <?= __('ڈیلی را مٹیریل یوز') ?></span><br>
    <small><?= __('روزانہ کا را مٹیریل ایڈ کریں — اسٹاک خودبخود کم ہوگا') ?></small>
  </div>
  <div class="no-print" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <a class="btn btn-outline" href="<?= e(url('inventory/raw-usage/report', ['from'=>date('Y-m-01'), 'to'=>today()])) ?>">📊 <?= __('Full Report') ?></a>
    <a class="btn btn-outline" href="<?= e(url('purchase/raw', ['tab'=>'purchases'])) ?>">📦 <?= __('Purchases') ?></a>
    <button class="btn btn-outline no-print" onclick="window.print()">🖨 <?= __('Print') ?></button>
  </div>
</div>

<!-- ═══════════ SUMMARY CHIPS ═══════════ -->
<div class="stat-strip no-print" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin:14px 0 0">
  <div class="stat-chip" style="border-color:#a5f3fc">
    <div class="sc-lbl">📅 <?= e($date) ?> <?= __('کا کل یوز') ?></div>
    <div class="sc-val" style="color:#0e7490"><?= number_format($todayKg, 2) ?> <span class="muted" style="font-size:12px">KG</span></div>
    <div class="sc-sub"><?= $todayCount ?> <?= __('انٹریز') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#bbf7d0">
    <div class="sc-lbl">📆 <?= __('اس مہینے کا کل') ?></div>
    <div class="sc-val" style="color:#166534"><?= number_format($monthKg, 2) ?> <span class="muted" style="font-size:12px">KG</span></div>
    <div class="sc-sub"><?= date('F Y') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#fde68a">
    <div class="sc-lbl">🏭 <?= __('کل مواد') ?></div>
    <div class="sc-val" style="color:#92400e"><?= count($products) ?></div>
    <div class="sc-sub"><?= __('Raw / Packaging') ?></div>
  </div>
</div>

<!-- ═══════════ DATE PICKER TOOLBAR ═══════════ -->
<form method="get" class="toolbar no-print" style="margin-top:12px">
  <input type="hidden" name="r" value="inventory/raw-usage">
  <label class="muted"><?= __('تاریخ') ?></label>
  <input type="date" name="date" value="<?= e($date) ?>" class="form-control" onchange="this.form.submit()">
  <button class="btn btn-primary" type="submit"><?= __('دیکھیں') ?> →</button>
  <a class="btn btn-outline" href="<?= e(url('inventory/raw-usage')) ?>"><?= __('آج') ?></a>
</form>

<!-- ═══════════ MAIN GRID: FORM + ENTRIES ═══════════ -->
<div style="display:grid;grid-template-columns:minmax(300px,420px) 1fr;gap:18px;align-items:start;margin-top:4px;flex-wrap:wrap">

  <!-- ───── ADD ENTRY FORM ───── -->
  <?php if (Auth::can('rm.usage')): ?>
  <div class="card" style="border:2px solid #bae6fd;border-radius:12px;padding:20px;background:#f0f9ff;position:sticky;top:70px">
    <h3 style="margin:0 0 14px;font-size:16px;font-weight:800">➕ <?= __('یوز ایڈ کریں') ?></h3>
    <form method="post" action="<?= e(url('inventory/raw-usage')) ?>" id="usageForm">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($date) ?>">

      <label class="form-label"><?= __('را مٹیریل') ?> <span style="color:red">*</span></label>
      <select name="product_id" id="productId" class="form-control mb-2" required>
        <option value="">— <?= __('منتخب کریں') ?> —</option>
        <?php foreach ($products as $prd): ?>
        <option value="<?= (int)$prd['id'] ?>"><?= e($prd['name']) ?> <small>(<?= e($prd['sku']) ?>)</small></option>
        <?php endforeach; ?>
      </select>

      <label class="form-label"><?= __('Quantity (KG)') ?> <span style="color:red">*</span></label>
      <input type="number" name="qty_kg" id="qtyKg" min="0.001" step="0.001"
             placeholder="<?= e(__('مثلاً: 250.000')) ?>" class="form-control mb-2" required autocomplete="off">

      <label class="form-label"><?= __('گودام') ?> <span style="color:red">*</span></label>
      <select name="warehouse_id" id="warehouseId" class="form-control mb-2" required>
        <option value="">— <?= __('منتخب کریں') ?> —</option>
        <?php foreach ($warehouses as $wh): ?>
        <option value="<?= (int)$wh['id'] ?>"
                data-bal="<?= number_format((float)($wh['balance'] ?? 0), 3) ?>"
                <?= (int)$wh['id'] === (int)$defaultWh ? 'selected' : '' ?>>
          <?= e($wh['name']) ?>
          <?php if ($wh['balance'] !== null): ?>
            — <?= number_format((float)$wh['balance'], 3) ?> KG
          <?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>

      <label class="form-label"><?= __('Note') ?></label>
      <input type="text" name="note" class="form-control mb-3"
             placeholder="<?= e(__('پروڈکشن نوٹ… (اختیاری)')) ?>">

      <!-- Live balance preview -->
      <div id="balanceChip" style="background:#fff;border:1px solid #e8a13a;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:none">
        <div style="font-size:11px;color:#92400e;font-weight:600"><?= __('اسٹاک → اس انٹری کے بعد') ?></div>
        <div id="balancePreview" style="font-size:17px;font-weight:800;color:#0e7490">—</div>
      </div>

      <button class="btn btn-primary" type="submit" style="width:100%;font-size:15px;padding:10px">
        💾 <?= __('محفوظ کریں (اسٹاک کٹے گا)') ?>
      </button>
      <p style="font-size:11px;color:#64748b;margin:8px 0 0;text-align:center">
        <?= __('ایک ہی دن میں ایک ہی مواد دوبارہ ایڈ کریں تو اپڈیٹ ہوگا') ?>
      </p>
    </form>
  </div>
  <?php endif; ?>

  <!-- ───── TODAY'S ENTRIES TABLE ───── -->
  <div>
    <div class="card" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
      <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
        <h3 style="margin:0;font-size:15px;font-weight:700">
          📋 <?= sprintf(__('%s کی یوز انٹریز'), '<strong>' . e($date) . '</strong>') ?>
        </h3>
        <?php if ($todayCount > 0): ?>
        <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700">
          <?= number_format($todayKg, 2) ?> KG <?= __('کل') ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if (empty($entries)): ?>
      <div style="padding:36px;text-align:center;color:#94a3b8">
        <div style="font-size:36px;margin-bottom:8px">📭</div>
        <?= __('اس تاریخ کی کوئی انٹری نہیں۔') ?>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="data-table" style="margin:0">
          <thead>
            <tr>
              <th>#</th>
              <th><?= __('را مٹیریل') ?></th>
              <th style="text-align:right"><?= __('KG') ?></th>
              <th><?= __('گودام') ?></th>
              <th><?= __('نوٹ') ?></th>
              <th><?= __('ریکارڈ کرنے والا') ?></th>
              <th><?= __('اسٹیٹس') ?></th>
              <?php if (is_owner_or_admin()): ?><th class="no-print"><?= __('کینسل') ?></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $ent): ?>
            <?php $cancelled = $ent['status'] !== 'posted'; ?>
            <tr style="<?= $cancelled ? 'opacity:.45;' : '' ?>">
              <td style="color:#94a3b8"><?= (int)$ent['id'] ?></td>
              <td>
                <strong><?= e($ent['product_name']) ?></strong>
                <div style="font-size:11px;color:#94a3b8"><?= e($ent['sku']) ?></div>
              </td>
              <td style="text-align:right;font-weight:700;font-size:15px;color:<?= $cancelled ? '#94a3b8' : '#0e7490' ?>">
                <?= number_format((float)$ent['qty_kg'], 3) ?>
                <?php if ($cancelled): ?><s><?php endif; ?>
              </td>
              <td style="font-size:12px;color:#64748b"><?= e($ent['warehouse_name'] ?? '—') ?></td>
              <td style="color:#64748b;font-size:13px"><?= e($ent['note'] ?? '—') ?></td>
              <td style="font-size:12px"><?= e($ent['recorded_by_username'] ?? '—') ?></td>
              <td>
                <?php if ($cancelled): ?>
                  <span style="color:#ef4444;font-size:11px;font-weight:700">❌ <?= __('کینسل') ?></span>
                  <?php if (!empty($ent['cancel_reason'])): ?>
                  <div style="font-size:10px;color:#94a3b8"><?= e($ent['cancel_reason']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#22c55e;font-weight:700;font-size:12px">✅ پوسٹڈ</span>
                <?php endif; ?>
              </td>
              <?php if (is_owner_or_admin()): ?>
              <td class="no-print">
                <?php if (!$cancelled): ?>
                <button type="button" class="btn btn-danger btn-sm"
                  onclick="document.getElementById('cdlg<?= (int)$ent['id'] ?>').showModal()">🗑</button>
                <dialog id="cdlg<?= (int)$ent['id'] ?>" style="border-radius:10px;border:1px solid #fca5a5;padding:20px;min-width:310px">
                  <form method="post" action="<?= e(url('inventory/raw-usage/cancel')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$ent['id'] ?>">
                    <p style="font-weight:700;margin:0 0 8px">⚠️ <?= __('کیا واقعی کینسل کرنا ہے؟') ?></p>
                    <p style="font-size:13px;color:#64748b;margin:0 0 10px">
                      <strong><?= e($ent['product_name']) ?></strong> — <?= number_format((float)$ent['qty_kg'],3) ?> KG
                    </p>
                    <label class="form-label"><?= __('وجہ (ضروری)') ?></label>
                    <input type="text" name="reason" class="form-control mb-2" required placeholder="<?= e(__('وجہ لکھیں…')) ?>">
                    <div style="display:flex;gap:8px;justify-content:flex-end">
                      <button type="button" class="btn btn-outline" onclick="this.closest('dialog').close()"><?= __('واپس') ?></button>
                      <button type="submit" class="btn btn-danger">🗑 <?= __('کینسل کریں') ?></button>
                    </div>
                  </form>
                </dialog>
                <?php else: ?>—<?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#f1f5f9;font-weight:700">
              <td colspan="2"><?= __('کل') ?></td>
              <td style="text-align:right;color:#0e7490"><?= number_format($todayKg, 3) ?> KG</td>
              <td colspan="<?= is_owner_or_admin() ? 5 : 4 ?>"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /main grid -->

<!-- ═══════════ DATE-RANGE SUMMARY REPORT ═══════════ -->
<div class="card no-print" style="margin-top:20px;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0;font-size:15px;font-weight:700">📊 <?= __('Date-Range Report') ?></h3>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="r" value="inventory/raw-usage">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <label class="muted" style="font-size:12px"><?= __('سے') ?></label>
      <input type="date" name="summary_from" value="<?= e($summaryFrom) ?>" class="form-control" style="width:145px">
      <label class="muted" style="font-size:12px"><?= __('تک') ?></label>
      <input type="date" name="summary_to"   value="<?= e($summaryTo) ?>"   class="form-control" style="width:145px">
      <select name="usage_mat" class="form-control" style="width:175px">
        <option value="">— <?= __('سب مواد') ?> —</option>
        <?php foreach ($products as $prd): ?>
        <option value="<?= (int)$prd['id'] ?>" <?= (int)$usageMat===(int)$prd['id'] ? 'selected' : '' ?>><?= e($prd['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">📊 <?= __('رپورٹ') ?></button>
      <a class="btn btn-outline btn-sm" href="<?= e(url('inventory/raw-usage', ['date'=>$date])) ?>"><?= __('ریسیٹ') ?></a>
    </form>
  </div>

  <?php if (!empty($summary)): ?>
  <!-- summary totals row -->
  <div style="display:flex;gap:12px;padding:10px 16px;background:#fafafa;border-bottom:1px solid #e2e8f0;flex-wrap:wrap">
    <div style="font-size:13px"><span class="muted"><?= __('کل KG:') ?></span> <strong style="color:#0e7490"><?= number_format($rptTotalKg,2) ?> KG</strong></div>
    <div style="font-size:13px"><span class="muted"><?= __('مواد:') ?></span> <strong><?= count(array_unique(array_column($summary,'sku'))) ?></strong></div>
    <div style="font-size:13px"><span class="muted"><?= __('دن:') ?></span> <strong><?= count(array_unique(array_column($summary,'usage_date'))) ?></strong></div>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table" style="margin:0">
      <thead>
        <tr>
          <th><?= __('تاریخ') ?></th>
          <th><?= __('را مٹیریل') ?></th>
          <th><?= __('SKU') ?></th>
          <th style="text-align:right"><?= __('کل KG') ?></th>
          <th style="text-align:right"><?= __('انٹریز') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $prevDt=''; foreach ($summary as $sr): ?>
        <tr>
          <td>
            <?php if ($sr['usage_date'] !== $prevDt): ?>
              <a href="<?= e(url('inventory/raw-usage', ['date'=>$sr['usage_date']])) ?>" style="font-weight:700;color:#0e7490"><?= e($sr['usage_date']) ?></a>
              <?php $prevDt = $sr['usage_date']; ?>
            <?php else: ?><span style="color:transparent">—</span><?php endif; ?>
          </td>
          <td><?= e($sr['product_name']) ?></td>
          <td><code style="font-size:11px"><?= e($sr['sku']) ?></code></td>
          <td style="text-align:right;font-weight:700;color:#0e7490"><?= number_format((float)$sr['total_kg'],3) ?></td>
          <td style="text-align:right;color:#64748b"><?= (int)$sr['entries'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:700">
          <td colspan="3"><?= __('مجموعہ') ?></td>
          <td style="text-align:right;color:#0e7490"><?= number_format($rptTotalKg,3) ?></td>
          <td style="text-align:right"><?= array_sum(array_column($summary,'entries')) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php else: ?>
  <div style="padding:28px;text-align:center;color:#94a3b8"><?= __('اس رینج میں کوئی ڈیٹا نہیں۔') ?></div>
  <?php endif; ?>
</div>

<!-- ═══════════ JS: live balance preview ═══════════ -->
<script>
(function () {
  var balances = {};
  document.querySelectorAll('#warehouseId option[data-bal]').forEach(function(o){
    balances[o.value] = parseFloat(o.getAttribute('data-bal')) || 0;
  });
  var wh = document.getElementById('warehouseId');
  var qty = document.getElementById('qtyKg');
  var chip = document.getElementById('balanceChip');
  var preview = document.getElementById('balancePreview');
  if (!wh || !qty) return;
  function refresh() {
    var bal = balances[wh.value];
    var used = parseFloat(qty.value) || 0;
    if (isNaN(bal) || wh.value === '') { chip.style.display='none'; return; }
    chip.style.display='block';
    var remaining = bal - used;
    preview.textContent = bal.toFixed(3) + ' KG  →  ' + remaining.toFixed(3) + ' KG';
    preview.style.color = remaining < 0 ? '#dc2626' : '#0e7490';
    chip.style.borderColor = remaining < 0 ? '#fca5a5' : '#fde68a';
  }
  wh.addEventListener('change', refresh);
  qty.addEventListener('input', refresh);
  refresh();
})();
</script>
