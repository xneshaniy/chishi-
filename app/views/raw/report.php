<?php $title = __('Raw Material Consumption Report'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="inventory/raw-usage/report">
  <label class="muted"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>">
  <label class="muted"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>">
  <select class="f" name="material_id" style="min-width:200px"><option value="0"><?= __('All materials') ?></option><?php foreach ($materialsAll ?? [] as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $materialId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>

<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Used (range)') ?></div><div class="sc-val" style="font-size:24px"><?= number_format($totQty, 3) ?> <span style="font-size:14px">KG</span></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Entries') ?></div><div class="sc-val" style="font-size:24px"><?= count($rows) ?></div></div>
</div>

<?php if ($materials): ?>
<div class="panel print-area">
  <h2 style="margin-top:0"><?= sprintf(__('By Material — %s — %s'), e(date_display($from)), e(date_display($to))) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Material') ?></th><th class="num"><?= __('Entries') ?></th><th class="num"><?= __('Used (KG)') ?></th></tr></thead>
  <tbody><?php foreach ($materials as $m): ?><tr><td><?= e($m['name']) ?></td><td class="num"><?= (int)$m['entries'] ?></td><td class="num"><strong><?= number_format((float)$m['qty'], 3) ?></strong> KG</td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<div class="panel print-area">
  <h2 style="margin-top:0"><?= sprintf(__('Entries — %s — %s'), e(date_display($from)), e(date_display($to))) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th><?= __('Warehouse') ?></th><th class="num"><?= __('Used (KG)') ?></th><th><?= __('By') ?></th><th><?= __('Note') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr style="<?= $r['status'] === 'cancelled' ? 'opacity:.55' : '' ?>"><td><?= e(date_display($r['usage_date'])) ?></td><td><?= e($r['product_name']) ?> <span class="muted" style="font-size:11px">(<?= e($r['sku']) ?>)</span></td><td class="mono"><?= e($r['warehouse_name'] ?? (string)$r['warehouse_id']) ?></td><td class="num"><?= number_format((float)$r['qty_kg'], 3) ?></td><td><?= e($r['recorded_by_username'] ?? '—') ?></td><td class="muted"><?= e($r['note'] ?? '') ?></td><td><span class="badge <?= $r['status'] === 'posted' ? 'green' : 'gray' ?>"><?= e(strtoupper($r['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= __('No usage entries in this range.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>

<div class="panel print-area">
  <h2 style="margin-top:0"><?= __('Monthly Usage (posted)') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Month') ?></th><th class="num"><?= __('Entries') ?></th><th class="num"><?= __('Used (KG)') ?></th></tr></thead>
  <tbody><?php foreach ($monthly as $m): ?><tr><td class="mono"><?= e($m['ym']) ?></td><td class="num"><?= (int)$m['entries'] ?></td><td class="num"><?= number_format((float)$m['qty'], 3) ?> KG</td></tr><?php endforeach; ?><?php if (!$monthly): ?><tr><td colspan="3" class="muted"><?= __('No data yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
