<?php $title = __('Employee Loans'); ?>
<div class="cards">
  <div class="card blue"><div class="lbl"><?= __('Total Loans (active)') ?></div><div class="val"><?= money($totals['total']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Recovered') ?></div><div class="val"><?= money($totals['recovered']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Outstanding') ?></div><div class="val"><?= money($totals['outstanding']) ?></div></div>
</div>
<form method="get" class="toolbar"><input type="hidden" name="r" value="loans">
  <input name="q" placeholder="<?= e(__('Search loan no / employee / reason…')) ?>" value="<?= e($q) ?>" style="min-width:240px">
  <select name="status"><option value=""><?= __('All') ?></option><?php foreach (['pending','active','completed','rejected','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('loans/new')) ?>"><?= __('+ New Loan') ?></a></form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Loan No') ?></th><th><?= __('Date') ?></th><th><?= __('Employee') ?></th><th><?= __('Reason') ?></th><th class="num"><?= __('Amount') ?></th><th class="num"><?= __('Recovered') ?></th><th class="num"><?= __('Outstanding') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $l): ?><tr><td class="mono"><?= e($l['loan_no']) ?></td><td><?= date_display($l['date']) ?></td><td><?= e($l['employee']) ?> <span class="muted"><?= e($l['code']) ?></span></td><td><?= e($l['reason']) ?></td>
<td class="num"><?= money($l['amount']) ?></td><td class="num"><?= money($l['recovered']) ?></td><td class="num"><?= money($l['outstanding']) ?></td>
<td><span class="badge <?= ['pending'=>'amber','active'=>'blue','completed'=>'green','rejected'=>'red','cancelled'=>'gray'][$l['status']] ?>"><?= e(strtoupper($l['status'])) ?></span></td>
<td><a class="btn btn-outline btn-sm" href="<?= e(url('loans/' . (int)$l['id'])) ?>"><?= __('Details') ?></a></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="9" class="muted"><?= __('No loans on file.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'loans', ['q' => $q, 'status' => $status]) ?></div>
