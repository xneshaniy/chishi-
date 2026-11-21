<?php $title = 'Factory Maintenance & Repairs'; ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="maintenance">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="q" placeholder="<?= e(__('Search machine / record / vendor…')) ?>" value="<?= e($q) ?>">
  <select name="department"><option value=""><?= __('All departments') ?></option><?php foreach ($departments as $d): ?><option value="<?= e($d['department']) ?>" <?= $dept === $d['department'] ? 'selected' : '' ?>><?= e($d['department']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value=""><?= __('All') ?></option><?php foreach (['pending','posted','rejected','reversed'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  <a class="btn btn-outline" href="<?= e(url('maintenance', ['from' => $from, 'to' => $to, 'q' => $q, 'department' => $dept, 'status' => $status, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <span style="flex:1"></span><a class="btn btn-outline" href="<?= e(url('maintenance/assets')) ?>"><?= __('Assets') ?></a>
  <?php if (Auth::can('maintenance.create')): ?><a class="btn btn-primary" href="<?= e(url('maintenance/new')) ?>"><?= __('+ New Maintenance Record') ?></a><?php endif; ?>
</form>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Total Maintenance Cost (Range)') ?></div><div class="val"><?= money($sum) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Posted to Ledger') ?></div><div class="val"><?= money($totPosted) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Awaiting Approval') ?></div><div class="val"><?= (int)$totPending ?></div></div>
</div>
<div class="panel print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Factory Maintenance & Repairs') ?> · <?= date_display($from) ?> – <?= date_display($to) ?></span><hr></div><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Machine') ?></th><th><?= __('Asset No') ?></th><th><?= __('Department') ?></th><th><?= __('Problem') ?></th><th><?= __('Vendor') ?></th><th class="num"><?= __('Parts') ?></th><th class="num"><?= __('Labour') ?></th><th class="num"><?= __('Other') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Via') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $x): ?><tr><td class="mono"><?= e($x['record_no']) ?></td><td><?= date_display($x['problem_date']) ?></td>
<td><?= e($x['machine_name']) ?></td><td class="mono"><?= e($x['asset_no'] ?? '—') ?></td><td><?= e($x['department']) ?></td><td style="max-width:220px"><?= e($x['problem']) ?></td><td><?= e($x['vendor'] ?? '—') ?></td>
<td class="num"><?= money($x['parts_cost'], false) ?></td><td class="num"><?= money($x['labour_cost'], false) ?></td><td class="num"><?= money($x['other_cost'], false) ?></td><td class="num"><strong><?= money($x['total_cost'], false) ?></strong></td>
<td><span class="badge gray"><?= e(strtoupper($x['payment_type'])) ?></span></td>
<td><span class="badge <?= ['pending'=>'amber','posted'=>'green','rejected'=>'red','reversed'=>'red'][$x['status']] ?? 'gray' ?>"><?= e(strtoupper($x['status'])) ?></span></td>
<td class="btn-group no-print"><a class="btn btn-outline btn-sm" href="<?= e(url('maintenance/' . (int)$x['id'])) ?>"><?= __('View') ?></a>
<?php if ($x['status'] === 'pending'): ?><?php if (Auth::can('approvals.decide')): ?>
  <form method="post" action="<?= e(url('maintenance/' . (int)$x['id'] . '/approve')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= __('Approve & Post') ?></button></form>
<?php else: ?><span class="badge amber"><?= __('Awaiting Admin approval') ?></span><?php endif; ?><?php endif; ?></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="14" class="muted"><?= __('No maintenance records in this range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'maintenance', ['from' => $from, 'to' => $to, 'q' => $q, 'department' => $dept, 'status' => $status]) ?></div>
