<?php $title = 'Claim Goods' ?>
<div class="pill-toggle" style="margin-bottom:14px">
  <a href="<?= e(url('claims')) ?>"><?= __('Customer Claims') ?></a>
  <a href="<?= e(url('claims/goods')) ?>" class="on"><?= __('Claim Goods') ?></a>
</div>
<form method="get" class="toolbar"><input type="hidden" name="r" value="claims/goods">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <select name="supplier_id"><option value="0"><?= __('All suppliers') ?></option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value=""><?= __('All statuses') ?></option><?php foreach (['pending_approval' => 'Pending Approval', 'posted' => 'Posted', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('claims/goods/new')) ?>"><?= __('+ New Claim Receipt') ?></a>
</form>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Good / Accepted (qty)') ?></div><div class="val"><?= qty($totals['good_qty']) ?></div><div class="sub"><?= (int)$totals['n'] ?><?= __('claim(s)') ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Damaged (qty)') ?></div><div class="val"><?= qty($totals['damaged_qty']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Rejected (qty)') ?></div><div class="val"><?= qty($totals['rejected_qty']) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Eligible Claim') ?></div><div class="val"><?= money($totals['claim_amount']) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Cash Paid') ?></div><div class="val"><?= money($totals['cash_paid']) ?></div></div>
  <div class="card <?= $totals['outstanding'] > 0 ? 'red' : 'accent' ?>"><div class="lbl"><?= __('Outstanding') ?></div><div class="val"><?= money($totals['outstanding']) ?></div><div class="sub"><?= __('supplier payable (claim terms)') ?></div></div>
</div>

<div class="panel">
  <h2><?= __('Supplier-wise Claim History') ?></h2>
  <?php if (!$supplierHistory): ?><p class="muted"><?= __('No posted claims in this period.') ?></p><?php else: ?>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Supplier') ?></th><th class="num"><?= __('Claims') ?></th><th class="num"><?= __('Good Qty') ?></th><th class="num"><?= __('Damaged') ?></th><th class="num"><?= __('Rejected') ?></th><th class="num"><?= __('Claim Amount') ?></th><th class="num"><?= __('Cash Paid') ?></th><th class="num"><?= __('Outstanding') ?></th></tr></thead>
  <tbody><?php foreach ($supplierHistory as $r): ?><tr><td><?= e($r['supplier']) ?></td><td class="num"><?= (int)$r['claims'] ?></td><td class="num"><?= qty($r['good_qty']) ?></td><td class="num"><?= qty($r['damaged_qty']) ?></td><td class="num"><?= qty($r['rejected_qty']) ?></td><td class="num"><?= money($r['claim_amount']) ?></td><td class="num"><?= money($r['cash_paid']) ?></td><td class="num"><?= $r['outstanding'] > 0 ? '<strong>' . money($r['outstanding']) . '</strong>' : money(0) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Claim No') ?></th><th><?= __('Date') ?></th><th><?= __('Supplier') ?></th><th class="num"><?= __('Good') ?></th><th class="num"><?= __('Damaged') ?></th><th class="num"><?= __('Rejected') ?></th><th class="num"><?= __('Claim Amount') ?></th><th class="num"><?= __('Cash Paid') ?></th><th class="num"><?= __('Outstanding') ?></th><th><?= __('Payment') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $c): $out = max(0, round((float)$c['claim_amount'] - (float)$c['cash_paid'], 2)); ?>
<tr><td class="mono"><?= e($c['claim_no']) ?></td><td><?= date_display($c['date']) ?></td><td><?= e($c['supplier']) ?></td>
<td class="num"><?= qty($c['good_qty']) ?></td><td class="num"><?= qty($c['damaged_qty']) ?></td><td class="num"><?= qty($c['rejected_qty']) ?></td>
<td class="num"><?= money($c['claim_amount']) ?></td><td class="num"><?= money($c['cash_paid']) ?></td><td class="num"><?= money($out) ?></td>
<td><span class="badge <?= $c['payment_type'] === 'cash' ? 'green' : 'amber' ?>"><?= $c['payment_type'] === 'cash' ? 'CASH' : 'PAY LATER' ?></span></td>
<td><span class="badge <?= ['pending_approval' => 'amber', 'posted' => 'green', 'rejected' => 'red', 'reversed' => 'gray'][$c['status']] ?? 'gray' ?>"><?= e(strtoupper(str_replace('_', ' ', $c['status']))) ?></span></td>
<td><a class="btn btn-outline btn-sm" href="<?= e(url('claims/goods/' . (int)$c['id'])) ?>"><?= __('Details') ?></a></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="12" class="muted"><?= __('No claim receipts in this period.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'claims/goods', ['from' => $from, 'to' => $to, 'supplier_id' => $supplierId, 'status' => $status]) ?></div>
