<?php $title = __('Customer Aging'); ?>
<div class="cards">
  <div class="card red"><div class="lbl"><?= __('Total Outstanding') ?></div><div class="val"><?= money($aging['total_outstanding']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Current') ?></div><div class="val"><?= money($aging['buckets']['current']) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('1–30 Days') ?></div><div class="val"><?= money($aging['buckets']['d1_30']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('31–60 Days') ?></div><div class="val"><?= money($aging['buckets']['d31_60']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('61–90 Days') ?></div><div class="val"><?= money($aging['buckets']['d61_90']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Over 90 Days') ?></div><div class="val"><?= money($aging['buckets']['d90_plus']) ?></div></div>
</div>
<?php foreach ($customers as $pid => $data): ?>
<div class="panel"><h2><?= e($data['name']) ?> <span class="badge red" style="float:right"><?= money($data['total']) ?></span></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Invoice') ?></th><th><?= __('Date') ?></th><th><?= __('Due') ?></th><th class="num"><?= __('Outstanding') ?></th><th class="num"><?= __('Days Overdue') ?></th></tr></thead>
<tbody><?php foreach ($data['invoices'] as $inv): ?><tr><td class="mono"><?= e($inv['doc']) ?></td><td><?= date_display($inv['date']) ?></td><td><?= date_display($inv['due_date']) ?></td><td class="num"><?= number_format($inv['outstanding'], 2) ?></td><td class="num"><?= (int)$inv['days_overdue'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endforeach; ?>
<?php if (!$customers): ?><div class="alert alert-success"><?= __('No outstanding credit invoices — everyone is settled. ✔') ?></div><?php endif; ?>
<p class="help"><?= __('Allocation: receipts are applied FIFO (oldest invoice first). Invoices without a due date are assumed due 30 days after invoice date.') ?></p>
