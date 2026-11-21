<?php
$title = __('Raw Material Purchases');
// $tab is passed from controller; fall back to GET param for safety
$activeTab = $tab ?? inp('tab', 'purchases');
$tonUnit = $tonUnit ?? max(1, (float)setting('rm_unit_ton', '1000'));
?>

<!-- ═══════════════════ TAB BAR ═══════════════════ -->
<div class="simple-hero no-print" style="padding:12px 18px 0;margin-bottom:0">
  <div class="greet">
    <span style="font-size:22px;font-weight:800"><?= __('Raw Material') ?></span><br>
    <small><?= __('Purchases, warehouse stock levels, summary by material, and supplier product mappings') ?></small>
  </div>
</div>
<nav class="tabs-bar no-print" aria-label="<?= e(__('Raw Material tabs')) ?>">
  <a href="<?= e(url('purchase/raw', ['tab'=>'purchases','from'=>$from,'to'=>$to])) ?>"
     class="<?= $activeTab === 'purchases' ? 'on' : '' ?>"><?= __('📦 Purchases') ?></a>
  <a href="<?= e(url('purchase/raw', ['tab'=>'suppliers','from'=>$from,'to'=>$to])) ?>"
     class="<?= $activeTab === 'suppliers' ? 'on' : '' ?>"><?= __('👥 Suppliers & Products') ?></a>
  <a href="<?= e(url('purchase/raw', ['tab'=>'summary','from'=>$from,'to'=>$to])) ?>"
     class="<?= $activeTab === 'summary' ? 'on' : '' ?>"><?= __('📊 Summary by Material') ?></a>
  <a href="<?= e(url('purchase/raw', ['tab'=>'daily-usage'])) ?>"
     class="<?= $activeTab === 'daily-usage' ? 'on' : '' ?>"><?= __('📋 Daily Usage') ?></a>
  <a href="<?= e(url('warehouse')) ?>"><?= __('🏬 Warehouse') ?> →</a>
</nav>

<!-- ═══════════════════ FILTER & ACTION BAR ═══════════════════ -->
<?php if ($activeTab !== 'suppliers' && $activeTab !== 'daily-usage'): ?>
<form method="get" class="toolbar no-print" style="margin-top:10px">
  <input type="hidden" name="r" value="purchase/raw">
  <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
  <?php if ($activeTab !== 'warehouse'): ?>
    <label class="muted"><?= __('From') ?></label>
    <input type="date" name="from" value="<?= e($from) ?>">
    <label class="muted"><?= __('To') ?></label>
    <input type="date" name="to" value="<?= e($to) ?>">
  <?php endif; ?>
  <input name="q" placeholder="<?= e(__('Search material name…')) ?>" value="<?= e($q) ?>" style="min-width:200px">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('purchase/raw', ['tab'=>$activeTab])) ?>"><?= __('Reset') ?></a>
  <span style="flex:1"></span>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  <?php if (is_owner_or_admin()): ?>
    <a class="btn btn-primary" href="<?= e(url('purchase/invoices/new', ['raw'=>1])) ?>">+ <?= __('Add New Purchase') ?></a>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php if ($activeTab === 'purchases'): ?>
<!-- ═══════════════ SUMMARY CHIPS (Purchases) ═══════════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:14px">
  <div class="stat-chip">
    <div class="sc-lbl"><?= __('Total Purchased') ?></div>
    <div class="sc-val"><?= number_format($totQtyKg, 0) ?> <span class="muted" style="font-size:12px">KG</span></div>
    <div class="sc-sub"><?= number_format($totQtyKg / $tonUnit, 3) ?> TON</div>
  </div>
  <div class="stat-chip" style="border-color:#fde68a">
    <div class="sc-lbl"><?= __('Total Value') ?></div>
    <div class="sc-val" style="color:#92400e"><?= money($totValue) ?></div>
    <div class="sc-sub"><?= __('purchase amount') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#bfe3cd">
    <div class="sc-lbl"><?= __('Total Paid') ?></div>
    <div class="sc-val" style="color:#1d6530"><?= money($totPaid) ?></div>
    <div class="sc-sub"><?= __('cash & bank given') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#f2b8b5">
    <div class="sc-lbl"><?= __('Remaining Payable') ?></div>
    <div class="sc-val" style="color:#8f1d13"><?= money(max(0, $totValue - $totPaid)) ?></div>
    <div class="sc-sub"><?= __('still to pay') ?></div>
  </div>
</div>

<!-- ════════════════ TAB 1: PURCHASES ════════════════ -->
<div class="panel print-area">
  <div class="print-only" style="margin-bottom:14px">
    <strong style="font-size:16px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br>
    <span class="muted"><?= __('Raw Material Purchases') ?> · <?= date_display($from) ?> – <?= date_display($to) ?></span>
    <hr>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th><?= __('Date') ?></th>
          <th><?= __('Raw Material Name') ?></th>
          <th class="num"><?= __('Quantity (KG)') ?></th>
          <th class="num"><?= __('Quantity (TON)') ?></th>
          <th class="num"><?= __('Rate / KG') ?></th>
          <th class="num"><?= __('Total Amount') ?></th>
          <th class="num"><?= __('Amount Paid') ?></th>
          <th class="num"><?= __('Remaining') ?></th>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $grandQtyKg = 0; $grandTotal = 0; $grandPaid = 0;
        foreach ($p['rows'] as $r):
          $qtyKg   = (float)($r['qty_kg'] > 0 ? $r['qty_kg'] : UnitConverter::toKg($r['qty'], $r['unit_short'] ?? 'KG'));
          $qtyTon  = $qtyKg / $tonUnit;
          $rateKg  = $qtyKg > 0 ? (float)$r['line_total'] / $qtyKg : 0;
          $paid    = (float)($r['amount_paid'] ?? 0);
          $rem     = max(0, (float)$r['line_total'] - $paid);
          $grandQtyKg += $qtyKg; $grandTotal += (float)$r['line_total']; $grandPaid += $paid;
        ?>
        <tr>
          <td><?= date_display($r['date']) ?></td>
          <td><strong><?= e($r['product']) ?></strong></td>
          <td class="num"><?= number_format($qtyKg, 2) ?> <span class="muted" style="font-size:11px">KG</span></td>
          <td class="num"><?= number_format($qtyTon, 4) ?> <span class="muted" style="font-size:11px">TON</span></td>
          <td class="num"><?= money($rateKg, false) ?></td>
          <td class="num"><strong><?= money($r['line_total']) ?></strong></td>
          <td class="num" style="color:#1d6530;font-weight:700"><?= money($paid) ?></td>
          <td class="num" style="color:<?= $rem > 0 ? '#8f1d13' : '#1d6530' ?>;font-weight:700"><?= money($rem) ?></td>
          <td class="no-print">
            <a class="btn btn-outline btn-sm" href="<?= e(url('purchase/invoices/' . (int)$r['id'])) ?>">
              <?= __('Detail') ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$p['rows']): ?>
        <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">
          <?= __('No raw material purchases found for this date range.') ?>
        </td></tr>
        <?php else: ?>
        <tr style="background:#f0f7f3;font-weight:800;border-top:2px solid var(--brand)">
          <td colspan="2"><strong><?= __('TOTAL') ?></strong></td>
          <td class="num"><?= number_format($grandQtyKg, 2) ?> KG</td>
          <td class="num"><?= number_format($grandQtyKg / $tonUnit, 4) ?> TON</td>
          <td class="num">—</td>
          <td class="num"><?= money($grandTotal) ?></td>
          <td class="num" style="color:#1d6530"><?= money($grandPaid) ?></td>
          <td class="num" style="color:#8f1d13"><?= money(max(0, $grandTotal - $grandPaid)) ?></td>
          <td class="no-print"></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= pager_html($p, 'purchase/raw', ['tab'=>'purchases','from'=>$from,'to'=>$to,'q'=>$q]) ?>
</div>

<?php elseif ($activeTab === 'warehouse'): ?>
<!-- ════════════════ TAB 2: WAREHOUSE STOCK ════════════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:14px">
  <div class="stat-chip">
    <div class="sc-lbl"><?= __('Current Warehouse Stock') ?></div>
    <div class="sc-val" style="color:#1d6530"><?= number_format($totStockQtyKg, 2) ?> <span class="muted" style="font-size:12px">KG</span></div>
    <div class="sc-sub"><?= number_format($totStockQtyKg / $tonUnit, 4) ?> TON</div>
  </div>
  <div class="stat-chip" style="border-color:#fde68a">
    <div class="sc-lbl"><?= __('Total Stock Valuation') ?></div>
    <div class="sc-val" style="color:#92400e"><?= money($totStockValue) ?></div>
    <div class="sc-sub"><?= __('cost value on hand') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#bfe3cd">
    <div class="sc-lbl"><?= __('Tracked Materials') ?></div>
    <div class="sc-val"><?= count($rawStockRows ?? []) ?></div>
    <div class="sc-sub"><?= __('active raw items') ?></div>
  </div>
</div>

<div class="panel print-area">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div>
      <h2 style="margin:0"><?= __('Warehouse Stock — Current Available Raw Materials') ?></h2>
      <small class="muted"><?= __('Stock updates automatically whenever new purchases are saved or production is run.') ?></small>
    </div>
    <a class="btn btn-primary btn-sm no-print" href="<?= e(url('purchase/invoices/new', ['raw'=>1])) ?>">+ <?= __('Add New Purchase') ?></a>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th><?= __('Raw Material Name') ?></th>
          <th><?= __('SKU / Code') ?></th>
          <th class="num"><?= __('On Hand Stock (KG)') ?></th>
          <th class="num"><?= __('On Hand Stock (TON)') ?></th>
          <th class="num"><?= __('Total Purchased (KG)') ?></th>
          <th class="num"><?= __('Used in Factory (KG)') ?></th>
          <th class="num"><?= __('Current Stock Value') ?></th>
          <th><?= __('Status') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rawStockRows as $sr):
          $kg = (float)$sr['qty'];
          $ton = $kg / $tonUnit;
          $val = (float)$sr['value'];
          $reorder = (float)($sr['reorder_level'] ?? 0);
          $isLow = $reorder > 0 && $kg <= $reorder;
        ?>
        <tr style="<?= $isLow ? 'background:#fdf3ef' : '' ?>">
          <td><strong><?= e($sr['name']) ?></strong></td>
          <td class="mono"><?= e($sr['sku']) ?></td>
          <td class="num" style="font-weight:700;font-size:15px;color:<?= $kg > 0 ? '#1d6530' : '#8f1d13' ?>">
            <?= number_format($kg, 2) ?> <span class="muted" style="font-size:11px">KG</span>
          </td>
          <td class="num" style="font-weight:700">
            <?= number_format($ton, 4) ?> <span class="muted" style="font-size:11px">TON</span>
          </td>
          <td class="num"><?= number_format((float)$sr['purchased'], 2) ?> KG</td>
          <td class="num"><?= number_format((float)$sr['used'], 2) ?> KG</td>
          <td class="num" style="font-weight:700"><?= money($val) ?></td>
          <td>
            <span class="badge <?= $isLow ? 'red' : ($kg > 0 ? 'green' : 'gray') ?>">
              <?= $isLow ? __('LOW STOCK') : ($kg > 0 ? __('IN STOCK') : __('EMPTY')) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rawStockRows): ?>
        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px"><?= __('No raw materials found.') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($activeTab === 'summary'): ?>
<!-- ════════════════ TAB 3: SUMMARY BY MATERIAL ════════════════ -->
<div class="panel print-area">
  <div class="print-only" style="margin-bottom:14px">
    <strong style="font-size:16px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br>
    <span class="muted"><?= __('Raw Material Summary') ?> · <?= date_display($from) ?> – <?= date_display($to) ?></span>
    <hr>
  </div>

  <h2 style="margin-top:0"><?= __('Summary — Total Purchased per Raw Material') ?></h2>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th><?= __('Raw Material Name') ?></th>
          <th class="num"><?= __('Total KG') ?></th>
          <th class="num"><?= __('Total TON') ?></th>
          <th class="num"><?= __('Avg Rate / KG') ?></th>
          <th class="num"><?= __('Total Amount') ?></th>
          <th class="num"><?= __('Total Paid') ?></th>
          <th class="num"><?= __('Remaining') ?></th>
          <th class="num"><?= __('Purchases') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $summary = [];
        foreach ($allRows as $r) {
          $name = $r['product'];
          $qkg  = (float)($r['qty_kg'] > 0 ? $r['qty_kg'] : UnitConverter::toKg($r['qty'], $r['unit_short'] ?? 'KG'));
          $paid = (float)($r['amount_paid'] ?? 0);
          if (!isset($summary[$name])) {
            $summary[$name] = ['name'=>$name,'qty_kg'=>0,'total'=>0,'paid'=>0,'count'=>0];
          }
          $summary[$name]['qty_kg'] += $qkg;
          $summary[$name]['total']  += (float)$r['line_total'];
          $summary[$name]['paid']   += $paid;
          $summary[$name]['count']++;
        }
        usort($summary, fn($a,$b) => $b['total'] <=> $a['total']);
        $gKg=0;$gTotal=0;$gPaid=0;$gCount=0;
        foreach ($summary as $s):
          $avgRate = $s['qty_kg'] > 0 ? $s['total'] / $s['qty_kg'] : 0;
          $rem     = max(0, $s['total'] - $s['paid']);
          $gKg+=$s['qty_kg']; $gTotal+=$s['total']; $gPaid+=$s['paid']; $gCount+=$s['count'];
        ?>
        <tr>
          <td><strong><?= e($s['name']) ?></strong></td>
          <td class="num"><?= number_format($s['qty_kg'], 2) ?> <span class="muted" style="font-size:11px">KG</span></td>
          <td class="num"><?= number_format($s['qty_kg'] / $tonUnit, 4) ?> <span class="muted" style="font-size:11px">TON</span></td>
          <td class="num"><?= money($avgRate, false) ?> / KG</td>
          <td class="num"><strong><?= money($s['total']) ?></strong></td>
          <td class="num" style="color:#1d6530;font-weight:700"><?= money($s['paid']) ?></td>
          <td class="num" style="color:<?= $rem > 0 ? '#8f1d13' : '#1d6530' ?>;font-weight:700"><?= money($rem) ?></td>
          <td class="num"><span class="badge blue"><?= (int)$s['count'] ?></span></td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$summary): ?>
        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">
          <?= __('No data for this date range.') ?>
        </td></tr>
        <?php else: ?>
        <tr style="background:#f0f7f3;font-weight:800;border-top:2px solid var(--brand)">
          <td><strong><?= __('TOTAL') ?></strong></td>
          <td class="num"><?= number_format($gKg, 2) ?> KG</td>
          <td class="num"><?= number_format($gKg / $tonUnit, 4) ?> TON</td>
          <td class="num">—</td>
          <td class="num"><?= money($gTotal) ?></td>
          <td class="num" style="color:#1d6530"><?= money($gPaid) ?></td>
          <td class="num" style="color:#8f1d13"><?= money(max(0, $gTotal - $gPaid)) ?></td>
          <td class="num"><?= (int)$gCount ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($activeTab === 'suppliers'): ?>
<!-- ════════════════ TAB 4: SUPPLIERS & PRODUCTS ════════════════ -->
<style>
dialog.native-modal::backdrop {
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
}
</style>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px;margin-bottom:16px">
  <!-- Form 1: Add New Supplier -->
  <div class="panel">
    <h2><?= __('+ Add New Supplier') ?></h2>
    <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_supplier">
      <div class="form-grid">
        <div class="span-2">
          <label class="f"><?= __('Supplier Name *') ?></label>
          <input class="f" name="name" required placeholder="<?= e(__('e.g. Khan Tobacco Traders')) ?>">
        </div>
        <div>
          <label class="f"><?= __('Phone Number') ?></label>
          <input class="f" name="phone" placeholder="0300-1234567">
        </div>
        <div>
          <label class="f"><?= __('City / Location') ?></label>
          <input class="f" name="city" placeholder="<?= e(__('e.g. Swabi / Mardan')) ?>">
        </div>
        <div class="span-2">
          <label class="f"><strong><?= __('Assign Raw Materials Supplied:') ?></strong></label>
          <div style="max-height:140px;overflow-y:auto;border:1px solid var(--line);border-radius:8px;padding:8px 12px;display:flex;flex-direction:column;gap:6px">
            <?php foreach ($rawProductsAll as $rp): ?>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="product_ids[]" value="<?= (int)$rp['id'] ?>">
                <?= e($rp['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <small class="muted"><?= __('Selecting this supplier in purchases will show only these products.') ?></small>
        </div>
      </div>
      <button class="btn btn-primary mt" type="submit"><?= __('Save Supplier →') ?></button>
    </form>
  </div>

  <!-- Form 2: Assign Materials to Existing Supplier (Add to Product) -->
  <div class="panel" style="border:1px solid #bfdbfe;background:#f8faff">
    <h2><?= __('+ Assign to Supplier') ?></h2>
    <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_product_to_supplier">
      <div class="form-grid">
        <div class="span-2">
          <label class="f"><strong><?= __('Select Supplier *') ?></strong></label>
          <select class="f" name="supplier_id" required>
            <option value=""><?= __('— select supplier —') ?></option>
            <?php foreach ($suppliersList as $sl): ?>
              <option value="<?= (int)$sl['id'] ?>"><?= e($sl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="span-2">
          <label class="f"><strong><?= __('Select Products to Add *') ?></strong></label>
          <div style="max-height:140px;overflow-y:auto;border:1px solid var(--line);border-radius:8px;padding:8px 12px;background:#fff;display:flex;flex-direction:column;gap:6px">
            <?php foreach ($rawProductsAll as $rp): ?>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="product_ids[]" value="<?= (int)$rp['id'] ?>">
                <?= e($rp['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <button class="btn btn-primary mt" type="submit"><?= __('+ Add Products to Supplier →') ?></button>
    </form>
  </div>

  <!-- Form 3: Add New Raw Material Product -->
  <div class="panel">
    <h2><?= __('+ Add New Raw Material') ?></h2>
    <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_raw_product">
      <div class="form-grid">
        <div class="span-2">
          <label class="f"><?= __('Raw Material Name *') ?></label>
          <input class="f" name="product_name" required placeholder="<?= e(__('e.g. Virginia Tobacco Leaf Grade A')) ?>">
        </div>
        <div class="span-2">
          <label class="f"><?= __('Link to Supplier (Optional)') ?></label>
          <select class="f" name="supplier_id">
            <option value=""><?= __('— select supplier to link directly —') ?></option>
            <?php foreach ($suppliersList as $sl): ?>
              <option value="<?= (int)$sl['id'] ?>"><?= e($sl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="help mt"><?= __('Raw material will be created in units of KG and automatically appear in purchases and warehouse stock.') ?></p>
      <button class="btn btn-primary mt" type="submit"><?= __('Save Raw Material Product →') ?></button>
    </form>
  </div>
</div>

<!-- Supplier List & Product Assignments -->
<div class="panel">
  <h2><?= __('Suppliers & Assigned Raw Materials List') ?></h2>
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:110px"><?= __('Supplier Code') ?></th>
          <th style="min-width:180px"><?= __('Supplier Name') ?></th>
          <th style="min-width:150px"><?= __('Contact') ?></th>
          <th><?= __('Products Supplied') ?></th>
          <th style="min-width:270px;text-align:right"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($suppliersList as $s):
          $pNames = !empty($s['product_names']) ? explode('||', $s['product_names']) : [];
          $pIds = !empty($s['product_ids']) ? array_map('intval', explode(',', $s['product_ids'])) : [];
          $dlgId = 'dlg-map-' . (int)$s['id'];
        ?>
        <tr>
          <td class="mono"><?= e($s['code']) ?></td>
          <td><strong><?= e($s['name']) ?></strong></td>
          <td><?= e($s['phone'] ?: '—') ?><?= !empty($s['city']) ? ' · ' . e($s['city']) : '' ?></td>
          <td>
            <?php if ($pNames): ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <?php foreach ($pNames as $idx => $pn):
                  $pid = $pIds[$idx] ?? 0;
                ?>
                  <span class="badge blue" style="font-size:12px;padding:3px 8px;display:inline-flex;align-items:center;gap:6px">
                    <?= e($pn) ?>
                    <?php if ($pid > 0): ?>
                      <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>" style="display:inline;margin:0" onsubmit="return confirm('<?= e(__('Remove this product from :supplier?', ['supplier' => $s['name']])) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_supplier_product">
                        <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                        <input type="hidden" name="product_id" value="<?= (int)$pid ?>">
                        <button type="submit" style="background:none;border:none;color:#8f1d13;cursor:pointer;padding:0 2px;font-size:12px;font-weight:700;line-height:1" title="<?= e(__('Remove product from this supplier')) ?>">✕</button>
                      </form>
                    <?php endif; ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span class="muted" style="font-size:12px"><?= __('All raw materials (no specific filter)') ?></span>
            <?php endif; ?>
          </td>
          <td style="text-align:right">
            <div style="display:inline-flex;gap:6px;align-items:center;flex-wrap:nowrap">
              <a class="btn btn-primary btn-sm" href="<?= e(url('purchase/invoices/new', ['raw'=>1, 'supplier_id'=>$s['id']])) ?>" title="<?= e(__('Create purchase for this supplier')) ?>">
                + <?= __('Purchase') ?>
              </a>

              <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= e($dlgId) ?>').showModal()" title="<?= e(__('Add or change products supplied by this supplier')) ?>">
                ⚙ <?= __('Add / Edit Products') ?>
              </button>

              <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>" style="display:inline;margin:0" onsubmit="return confirm('<?= e(__('Are you sure you want to delete supplier :name?')) ?>'.replace(':name', '<?= addslashes(e($s['name'])) ?>'))">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_supplier">
                <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="color:#cf3f2f;border-color:#f5b5b5" title="<?= e(__('Delete Supplier')) ?>">
                  🗑 <?= __('Delete') ?>
                </button>
              </form>
            </div>

            <!-- Native Dialog Modal for managing supplier products -->
            <dialog id="<?= e($dlgId) ?>" class="native-modal" style="border:none;border-radius:12px;padding:22px;max-width:500px;width:92%;box-shadow:0 12px 36px rgba(0,0,0,0.25);background:#fff;text-align:left">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid var(--line);padding-bottom:10px">
                <h3 style="margin:0;font-size:17px"><?= __('Products for') ?> <span style="color:var(--brand)"><?= e($s['name']) ?></span></h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= e($dlgId) ?>').close()" style="padding:2px 8px">✕</button>
              </div>
              <form method="post" action="<?= e(url('purchase/raw', ['tab'=>'suppliers'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="map_products">
                <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                <p class="muted" style="font-size:13px;margin:0 0 10px"><?= __('Check the raw materials this supplier provides. When creating a purchase for this supplier, only these products will be shown.') ?></p>
                <div style="max-height:260px;overflow-y:auto;border:1px solid var(--line);border-radius:8px;padding:10px;margin-bottom:16px;display:flex;flex-direction:column;gap:8px">
                  <?php foreach ($rawProductsAll as $rp): ?>
                    <label style="font-size:13px;display:flex;align-items:center;gap:8px;cursor:pointer;padding:3px">
                      <input type="checkbox" name="product_ids[]" value="<?= (int)$rp['id'] ?>" <?= in_array((int)$rp['id'], $pIds, true) ? 'checked' : '' ?>>
                      <span><strong><?= e($rp['name']) ?></strong> <small class="muted">(<?= e($rp['sku']) ?>)</small></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px">
                  <button type="button" class="btn btn-outline" onclick="document.getElementById('<?= e($dlgId) ?>').close()"><?= __('Cancel') ?></button>
                  <button type="submit" class="btn btn-primary"><?= __('Save Products') ?> →</button>
                </div>
              </form>
            </dialog>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/raw_daily_usage_block.php'; ?>
