<?php $title = 'Maintenance Report'; ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/maintenance">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <select name="group"><?php foreach (['month' => 'By Month', 'day' => 'By Day', 'year' => 'By Year', 'machine' => 'By Machine', 'department' => 'By Department', 'vendor' => 'By Vendor', 'asset' => 'By Asset No'] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <input name="department" placeholder="<?= e(__('Department (optional)')) ?>" value="<?= e($dept) ?>" style="width:150px">
  <input name="machine" placeholder="<?= e(__('Machine contains…')) ?>" value="<?= e($machine) ?>" style="width:150px">
  <button class="btn btn-primary" type="submit"><?= __('Run') ?></button>
  <a class="btn btn-outline" href="<?= e(url('reports/maintenance', ['from' => $from, 'to' => $to, 'group' => $group, 'department' => $dept, 'machine' => $machine, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Jobs in Range') ?></div><div class="val"><?= (int)$totCount ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Total Maintenance Cost') ?></div><div class="val"><?= money($totCost) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Posted to Ledger') ?></div><div class="val"><?= money($totPosted) ?></div></div>
</div>
<div class="panel print-area"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Group') ?></th><th class="num"><?= __('Jobs') ?></th><th class="num"><?= __('Total Cost') ?></th><th class="num"><?= __('Posted Cost') ?></th></tr></thead>
<tbody><?php foreach ($summary as $g): ?><tr><td><?= e($g['label']) ?></td><td class="num"><?= (int)$g['count'] ?></td><td class="num"><?= money($g['cost']) ?></td><td class="num"><?= money($g['posted']) ?></td></tr><?php endforeach; ?>
<?php if (!$summary): ?><tr><td colspan="4" class="muted"><?= __('No maintenance in this range.') ?></td></tr><?php endif; ?></tbody></table></div>

<h3 style="margin-top:16px"><?= __('Detail — every job in range') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Machine') ?></th><th><?= __('Department') ?></th><th><?= __('Problem') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><td class="mono"><?= e($r['record_no']) ?></td><td><?= date_display($r['problem_date']) ?></td><td><?= e($r['machine_name']) ?></td><td><?= e($r['department']) ?></td><td style="max-width:340px"><?= e($r['problem']) ?></td><td class="num"><?= money($r['total_cost'], false) ?></td><td><span class="badge <?= ['pending'=>'amber','posted'=>'green','rejected'=>'red','reversed'=>'red'][$r['status']] ?? 'gray' ?>"><?= e(strtoupper($r['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= __('No records.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
