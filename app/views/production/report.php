<?php $title = 'Production Report'; ?>
<form method="get" class="toolbar no-print">
  <input type="hidden" name="r" value="production/report">
  <select class="f" name="mode" id="modeSel" style="min-width:130px">
    <option value="range" <?= $mode === 'range' ? 'selected' : '' ?>><?= __('Date range') ?></option>
    <option value="day" <?= $mode === 'day' ? 'selected' : '' ?>><?= __('Single day') ?></option>
    <option value="month" <?= $mode === 'month' ? 'selected' : '' ?>><?= __('Monthly') ?></option>
  </select>
  <span id="modeRange" style="display:<?= $mode === 'range' ? 'inline-flex' : 'none' ?>;gap:6px;align-items:center">
    <input type="date" class="f" name="from" value="<?= e($from) ?>"> <span class="muted">→</span> <input type="date" class="f" name="to" value="<?= e($to) ?>">
  </span>
  <span id="modeDay" style="display:<?= $mode === 'day' ? 'inline-flex' : 'none' ?>;gap:6px;align-items:center"><input type="date" class="f" name="date" value="<?= e($date) ?>"></span>
  <span id="modeMonth" style="display:<?= $mode === 'month' ? 'inline-flex' : 'none' ?>;gap:6px;align-items:center"><input type="month" class="f" name="month" value="<?= e($month) ?>"></span>
  <select class="f" name="product_id" style="min-width:220px"><option value=""><?= __('All products') ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>

<div class="panel print-area" style="margin-bottom:14px">
  <div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Production Report') ?> · <?= e($from) ?> → <?= e($to) ?></span><hr></div>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0"><?= __('Production Report') ?> <span class="muted" style="font-size:11px;font-weight:400"><?= e($from) ?> → <?= e($to) ?></span></h2>
    <div class="stat-chip no-print" style="min-width:170px"><div class="sc-lbl"><?= __('Grand Total Produced (Packets)') ?></div><div class="sc-val"><?= number_format((float)$grandTotal, 0) ?></div><div class="sc-sub"><?= __('in selected range') ?></div></div>
  </div>
</div>

<div class="panel" style="margin-bottom:14px">
  <h2><?= __('Per-Product Totals') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= count($byProduct) ?> <?= __('products') ?>)</span></h2>
  <?php if (!$byProduct): ?><p class="muted"><?= __('No production records in this range.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Entries') ?></th><th class="num"><?= __('Total Produced (Packets)') ?></th></tr></thead>
      <tbody><?php foreach ($byProduct as $p): ?><tr><td><span class="mono"><?= e($p['sku']) ?></span> — <?= e($p['name']) ?></td><td class="num"><?= (int)$p['entries'] ?></td><td class="num"><?= number_format((float)$p['qty'], 0) ?> <?= e(($p['kind'] ?? '') === 'finished' ? __('Packets') : ($p['unit_short'] ?? '')) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Grand Total') ?></th><th></th><th class="num"><?= number_format((float)$grandTotal, 0) ?></th></tr></tfoot></table></div>
  <?php endif; ?>
</div>

<div class="panel">
  <h2><?= __('Line Detail') ?></h2>
  <?php if (!$rows): ?><p class="muted"><?= __('No production records in this range.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Qty (Packets)') ?></th><th><?= __('Batch') ?></th><th><?= __('Reference') ?></th><th><?= __('Recorded By') ?></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?><tr><td><?= date_display($r['row_date']) ?></td><td><span class="mono"><?= e($r['sku']) ?></span> — <?= e($r['name']) ?></td><td class="num"><?= number_format((float)$r['qty'], 0) ?> <?= e(($r['kind'] ?? '') === 'finished' ? __('Packets') : ($r['unit_short'] ?? '')) ?></td><td><?= e($r['batch_no'] ?: '—') ?></td><td class="mono"><?= e($r['run_no']) ?></td><td><?= e($r['recorded_by'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var sel = document.getElementById('modeSel');
  var ranges = { range: document.getElementById('modeRange'), day: document.getElementById('modeDay'), month: document.getElementById('modeMonth') };
  function sync() { var v = sel.value; for (var k in ranges) ranges[k].style.display = (k === v ? 'inline-flex' : 'none'); }
  sel.addEventListener('change', sync);
})();
</script>
