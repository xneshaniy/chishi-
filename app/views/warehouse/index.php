<?php
$title = __('Warehouse');
$tonUnit = $tonUnit ?? max(1, (float)setting('rm_unit_ton', '1000'));
?>

<!-- ═══════════════════ HEADER ═══════════════════ -->
<div class="simple-hero no-print" style="padding:14px 18px;margin-bottom:14px">
  <div class="greet">
    <span style="font-size:24px;font-weight:800"><?= __('Warehouse Stock') ?></span><br>
    <small><?= __('Physical inventory tracking of raw materials and finished goods across all factory warehouses') ?></small>
  </div>
</div>

<!-- ═══════════════════ FILTER BAR ═══════════════════ -->
<form method="get" class="toolbar no-print" style="margin-bottom:14px">
  <input type="hidden" name="r" value="warehouse">
  <input name="q" placeholder="<?= e(__('Search raw material or factory product…')) ?>" value="<?= e($q) ?>" style="min-width:260px">
  <button class="btn btn-primary" type="submit"><?= __('Search') ?></button>
  <a class="btn btn-outline" href="<?= e(url('warehouse')) ?>"><?= __('Reset') ?></a>
  <span style="flex:1"></span>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print Stock Report') ?></button>
</form>

<!-- ═══════════════════ SUMMARY CHIPS (PURE STOCK QUANTITIES - NO PAYMENT / NO MONEY) ═══════════════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:16px">
  <!-- Raw Material Total KG & TON -->
  <div class="stat-chip" style="border-color:#bbf7d0;background:#f0fdf4">
    <div class="sc-lbl" style="color:#166534">🌿 <?= __('Raw Materials Stock') ?></div>
    <div class="sc-val" style="color:#15803d">
      <?= number_format($totRawKg, 2) ?> <span style="font-size:14px;font-weight:600">KG</span>
    </div>
    <div class="sc-sub" style="font-weight:700;color:#166534">
      <?= number_format($totRawKg / $tonUnit, 4) ?> <span style="font-size:12px">TON</span>
    </div>
  </div>

  <!-- Factory Products In Stock -->
  <div class="stat-chip" style="border-color:#bfdbfe;background:#eff6ff">
    <div class="sc-lbl" style="color:#1e40af">🏭 <?= __('Factory Products In Stock') ?></div>
    <div class="sc-val" style="color:#1d4ed8">
      <?= number_format($totFgQty, 0) ?> <span style="font-size:14px;font-weight:600"><?= __('Units') ?></span>
    </div>
    <div class="sc-sub" style="font-weight:700;color:#1e40af">
      <?= count($fgRows) ?> <?= __('total product varieties') ?>
    </div>
  </div>

  <!-- Factory Total Produced -->
  <div class="stat-chip" style="border-color:#e2e8f0;background:#f8fafc">
    <div class="sc-lbl" style="color:#475569">📦 <?= __('Total Tracked Products') ?></div>
    <div class="sc-val" style="color:#334155">
      <?= count($rawRows) + count($fgRows) ?> <span style="font-size:14px;font-weight:600"><?= __('Items') ?></span>
    </div>
    <div class="sc-sub" style="font-weight:700;color:#64748b">
      <?= count($rawRows) ?> <?= __('Raw Materials') ?> + <?= count($fgRows) ?> <?= __('Finished Products') ?>
    </div>
  </div>
</div>

<div class="print-area">
  <div class="print-only" style="margin-bottom:16px">
    <strong style="font-size:18px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br>
    <span style="font-size:15px;font-weight:700"><?= __('Warehouse Stock Report') ?></span> · <?= date_display(today()) ?>
    <hr>
  </div>

  <!-- ═══════════════════ SECTION 1: RAW MATERIALS ═══════════════════ -->
  <div class="panel" style="margin-bottom:20px;border-top:3px solid #16a34a">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <h2 style="margin:0;color:#166534">🌿 <?= __('1. Raw Material Stock') ?></h2>
        <small class="muted"><?= __('خام مال کا موجودہ فزیکل اسٹاک جو گودام میں دستیاب ہے (صرف کے جی اور ٹن)') ?></small>
      </div>
      <span class="badge green" style="font-size:13px"><?= count($rawRows) ?> <?= __('Materials') ?></span>
    </div>

    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr style="background:#f8fafc">
            <th><?= __('Raw Material Name') ?></th>
            <th style="width:140px"><?= __('SKU / Code') ?></th>
            <th class="num" style="min-width:160px"><?= __('Quantity (KG)') ?></th>
            <th class="num" style="min-width:160px"><?= __('Quantity (TON)') ?></th>
            <th style="width:120px;text-align:center"><?= __('Status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rawRows as $r):
            $kg = (float)$r['qty'];
            $ton = $kg / $tonUnit;
            $isLow = (float)($r['reorder_level'] ?? 0) > 0 && $kg <= (float)$r['reorder_level'];
          ?>
          <tr style="<?= $kg <= 0 ? 'opacity:0.65' : '' ?>">
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="mono"><?= e($r['sku']) ?></td>
            <td class="num" style="font-weight:700;font-size:15px;color:<?= $kg > 0 ? '#15803d' : '#8f1d13' ?>">
              <?= number_format($kg, 2) ?> <span class="muted" style="font-size:12px">KG</span>
            </td>
            <td class="num" style="font-weight:700;font-size:15px;color:<?= $kg > 0 ? '#15803d' : '#8f1d13' ?>">
              <?= number_format($ton, 4) ?> <span class="muted" style="font-size:12px">TON</span>
            </td>
            <td style="text-align:center">
              <span class="badge <?= $isLow ? 'red' : ($kg > 0 ? 'green' : 'gray') ?>">
                <?= $isLow ? __('LOW STOCK') : ($kg > 0 ? __('IN STOCK') : __('EMPTY')) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rawRows): ?>
          <tr><td colspan="5" class="muted" style="text-align:center;padding:24px"><?= __('No raw materials found.') ?></td></tr>
          <?php else: ?>
          <tr style="background:#f0fdf4;font-weight:800;border-top:2px solid #16a34a">
            <td><strong><?= __('TOTAL RAW MATERIAL') ?></strong></td>
            <td>—</td>
            <td class="num" style="color:#15803d;font-size:16px"><?= number_format($totRawKg, 2) ?> KG</td>
            <td class="num" style="color:#15803d;font-size:16px"><?= number_format($totRawKg / $tonUnit, 4) ?> TON</td>
            <td></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════ SECTION 2: FACTORY FINISHED PRODUCTS ═══════════════════ -->
  <div class="panel" style="border-top:3px solid #2563eb">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <h2 style="margin:0;color:#1e40af">🏭 <?= __('2. Factory Finished Products') ?></h2>
        <small class="muted"><?= __('وہ تمام پروڈکٹس جو فیکٹری میں تیار ہو کر گودام میں اسٹاک کی صورت میں موجود ہیں (صرف تعداد / یونٹس)') ?></small>
      </div>
      <span class="badge blue" style="font-size:13px"><?= count($fgRows) ?> <?= __('Products') ?></span>
    </div>

    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr style="background:#f8fafc">
            <th><?= __('Product Name') ?></th>
            <th style="width:130px"><?= __('SKU / Code') ?></th>
            <th style="width:140px"><?= __('Packing / Unit') ?></th>
            <th class="num" style="min-width:160px"><?= __('In Stock Quantity') ?></th>
            <th class="num" style="min-width:160px"><?= __('Factory Produced') ?></th>
            <th style="width:120px;text-align:center"><?= __('Status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fgRows as $r):
            $qty = (float)$r['qty'];
            $produced = (float)$r['produced_total'];
            $unit = $r['unit_short'] ?: ($r['pack_size'] ?: 'PCS');
            $isLow = (float)($r['reorder_level'] ?? 0) > 0 && $qty <= (float)$r['reorder_level'];
          ?>
          <tr style="<?= $qty <= 0 ? 'opacity:0.65' : '' ?>">
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="mono"><?= e($r['sku']) ?></td>
            <td><?= e($unit) ?></td>
            <td class="num" style="font-weight:700;font-size:15px;color:<?= $qty > 0 ? '#1d4ed8' : '#8f1d13' ?>">
              <?= number_format($qty, 0) ?> <span class="muted" style="font-size:12px"><?= e($unit) ?></span>
            </td>
            <td class="num" style="font-weight:700;font-size:15px">
              <?= number_format($produced, 0) ?> <span class="muted" style="font-size:12px"><?= e($unit) ?></span>
            </td>
            <td style="text-align:center">
              <span class="badge <?= $isLow ? 'red' : ($qty > 0 ? 'green' : 'gray') ?>">
                <?= $isLow ? __('LOW STOCK') : ($qty > 0 ? __('IN STOCK') : __('EMPTY')) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$fgRows): ?>
          <tr><td colspan="6" class="muted" style="text-align:center;padding:24px"><?= __('No factory finished goods found.') ?></td></tr>
          <?php else: ?>
          <tr style="background:#eff6ff;font-weight:800;border-top:2px solid #2563eb">
            <td><strong><?= __('TOTAL FACTORY FINISHED GOODS') ?></strong></td>
            <td>—</td>
            <td>—</td>
            <td class="num" style="color:#1d4ed8;font-size:16px"><?= number_format($totFgQty, 0) ?></td>
            <td>—</td>
            <td></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
