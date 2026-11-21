<?php $title = __('Vehicle —') . e($v['reg_no']); ?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('vehicles')) ?>">&larr; <?= __('Back to Vehicles') ?></a>
  <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</div>
<div class="simple-hero" style="padding:16px 20px;margin-bottom:12px">
  <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
    <?php if (!empty($v['photo_path'])): ?><img src="<?= e(photo_data_uri($v['photo_path'])) ?>" alt="<?= e($v['reg_no']) ?>" style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid var(--line)"><?php endif; ?>
    <div>
      <span style="font-size:22px;font-weight:800"><?= e($v['name'] ?: $v['reg_no']) ?></span><br>
      <small class="muted"><?= e($v['reg_no']) ?> <?= $v['vehicle_type'] ? '· ' . e($v['vehicle_type']) : '' ?> <?= $v['make'] ? '· ' . e(trim($v['make'] . ' ' . ($v['model'] ?? ''))) : '' ?> <?= $v['year'] ? '· ' . e((string)$v['year']) : '' ?></small>
      <?php if ($v['purchase_date']): ?><br><small class="muted"><?= __('Purchased') ?>: <?= e(date_display($v['purchase_date'])) ?></small><?php endif; ?>
      <?php if ($v['driver_name']): ?><br><small class="muted"><?= __('Driver') ?>: <?= e($v['driver_name']) ?></small><?php endif; ?>
    </div>
    <span class="badge <?= ($v['status'] ?? 'active') === 'active' ? 'green' : (($v['status'] ?? '') === 'retired' ? 'red' : 'gray') ?>" style="font-size:14px;padding:6px 14px"><?= e(strtoupper($v['status'] ?? 'active')) ?></span>
  </div>
</div>

<div class="cards">
  <div class="card gold"><div class="lbl"><?= __('Fuel Cost') ?> (<?= e(date_display($from)) ?> — <?= e(date_display($to)) ?>)</div><div class="val"><?= money($totFuel) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Vehicle Expenses') ?> (<?= e(date_display($from)) ?> — <?= e(date_display($to)) ?>)</div><div class="val"><?= money($totMaint) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Total Running Cost') ?> (<?= e(date_display($from)) ?> — <?= e(date_display($to)) ?>)</div><div class="val"><?= money($totFuel + $totMaint) ?></div><div class="sub"><?= e(trim($v['make'] . ' ' . ($v['model'] ?? '')) ?: $v['reg_no']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('All-time') ?></div><div class="val"><?= money($totals['fuel'] + $totals['maint']) ?></div><div class="sub"><?= money($totals['fuel'], false) ?> <?= __('fuel') ?> · <?= money($totals['maint'], false) ?> <?= __('maint') ?></div></div>
</div>
<form method="get" class="toolbar"><input type="hidden" name="r" value="vehicles/<?= (int)$v['id'] ?>"><label class="muted"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>"><button class="btn btn-primary" type="submit"><?= __('Show') ?></button><button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button></form>

<div class="two-col">
  <div class="panel"><h2><?= __('Add Fuel Log') ?></h2>
    <form method="post" action="<?= e(url('vehicles/' . (int)$v['id'] . '/fuel')) ?>"><?= csrf_field() ?>
      <div class="form-grid"><div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div><div><label class="f"><?= __('Station') ?></label><input class="f" name="station" placeholder="<?= e(__('e.g. PSO Gawalmandi')) ?>"></div><div><label class="f"><?= __('Liters *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="liters" required></div><div><label class="f"><?= __('Cost (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0" name="cost" required></div><div><label class="f"><?= __('Mileage (km)') ?></label><input class="f" type="number" name="mileage"></div></div>
      <button class="btn btn-primary mt" type="submit"><?= __('Add Fuel Log') ?></button>
    </form>
    <div class="table-wrap mt"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Litres') ?></th><th class="num"><?= __('Cost') ?></th><th class="num"><?= __('Mileage') ?></th></tr></thead>
      <tbody><?php foreach ($fuel as $f): ?><tr><td><?= date_display($f['date']) ?></td><td class="num"><?= qty($f['liters']) ?></td><td class="num"><?= money($f['cost']) ?></td><td class="num"><?= e($f['mileage'] ?? '—') ?></td></tr><?php endforeach; ?><?php if (!$fuel): ?><tr><td colspan="4" class="muted"><?= __('No fuel logs.') ?></td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel"><h2><?= __('Add Vehicle Expense') ?></h2>
    <form method="post" action="<?= e(url('vehicles/' . (int)$v['id'] . '/add-expense')) ?>"><?= csrf_field() ?>
      <div class="form-grid"><div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div><div><label class="f"><?= __('Type') ?></label><select class="f" name="type"><option value="fuel"><?= __('Diesel / Fuel') ?></option><option value="repair"><?= __('Repairs') ?></option><option value="maintenance" selected><?= __('Maintenance') ?></option><option value="spare_parts"><?= __('Spare Parts') ?></option><option value="other"><?= __('Other Expense') ?></option></select></div></div>
      <div class="form-grid"><div><label class="f"><?= __('Vendor / Workshop') ?></label><input class="f" name="vendor"></div><div><label class="f"><?= __('Cost (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0" name="cost" required></div></div>
      <label class="f mt"><?= __('Description *') ?></label><input class="f" name="description" required placeholder="<?= e(__('e.g. tyre change, engine oil, brake pads')) ?>">
      <button class="btn btn-primary mt" type="submit"><?= __('Add Expense') ?></button>
    </form>
    <div class="table-wrap mt"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Description') ?></th><th><?= __('Vendor') ?></th><th class="num"><?= __('Cost') ?></th></tr></thead>
      <tbody><?php foreach ($maint as $m): ?><tr><td><?= date_display($m['date']) ?></td><td><?= e(strtoupper(str_replace('_', ' ', $m['type']))) ?></td><td><?= e(mb_substr($m['description'], 0, 120)) ?></td><td><?= e($m['vendor'] ?? '—') ?></td><td class="num"><?= money($m['cost']) ?></td></tr><?php endforeach; ?><?php if (!$maint): ?><tr><td colspan="5" class="muted"><?= __('No expenses logged.') ?></td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>
<div class="panel print-area"><h2 style="margin-top:0"><?= __('Vehicle Expense History —') . ' ' . e(date_display($from)) . ' — ' . e(date_display($to)) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Station / Vendor / Note') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Cost') ?></th><th><?= __('By') ?></th></tr></thead>
  <tbody><?php foreach ($history as $h): ?><tr>
    <td><?= e(date_display($h['date'])) ?></td>
    <td><span class="badge gray"><?= e(strtoupper($h['type'])) ?></span></td>
    <td><?= e($h['label'] ?: ($h['description'] ?? '—')) ?></td>
    <td class="num"><?= $h['liters'] !== null ? qty($h['liters']) . ' L' : '—' ?></td>
    <td class="num"><strong><?= money($h['cost'], false) ?></strong></td>
    <td><?= e($h['created_by'] !== null ? (string)(int)$h['created_by'] : '—') ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$history): ?><tr><td colspan="6" class="muted"><?= __('No records in this range.') ?></td></tr><?php else: ?>
  <tr style="font-weight:800;background:#faf7ee"><td colspan="4"><?= __('TOTAL') ?></td><td class="num"><?= money($historyTotal, false) ?></td><td></td></tr>
  <?php endif; ?></tbody></table></div>
</div>
