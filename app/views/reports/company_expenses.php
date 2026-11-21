<?php $title = __('Company Expenses Report'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/company-expenses">
  <label class="muted"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>">
  <label class="muted"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>">
  <select class="f" name="group"><?php foreach (['category' => __('Category-wise'), 'item' => __('Item-wise'), 'day' => __('Daily'), 'month' => __('Monthly'), 'year' => __('Yearly')] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <select class="f" name="category_id"><option value="0"><?= __('All categories') ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-outline" type="button" onclick="window.location='<?= e(url('reports/company-expenses', ['from' => $from, 'to' => $to, 'group' => $group, 'category_id' => $catId, 'export' => 'csv'])) ?>'"><?= __('CSV') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total') ?></div><div class="sc-val" style="font-size:24px"><?= money($sum) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash Payments') ?></div><div class="sc-val" style="color:#7a5a17"><?= money($cash) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank Payments') ?></div><div class="sc-val" style="color:var(--green,#2e7d4f)"><?= money($bank) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Pending Approvals') ?></div><div class="sc-val"><?= (int)$pendingCnt ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Vouchers') ?></div><div class="sc-val"><?= (int)$totalCnt ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Paid') ?></div><div class="sc-val" style="color:var(--green,#2e7d4f)"><?= money($paid) ?></div><div class="sc-sub"><?= __('posted') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Pending') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($unpaid) ?></div><div class="sc-sub"><?= __('awaiting approval') ?></div></div>
</div>
<div class="panel print-area">
  <h2 style="margin-top:0"><?= __('Breakdown —') . ' ' . ($group === 'category' ? __('Category') : ($group === 'item' ? __('Item') : ($group === 'day' ? __('Day') : ($group === 'month' ? __('Month') : __('Year'))))) . ' · ' . e(date_display($from)) . ' — ' . e(date_display($to)) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Group') ?></th><th class="num"><?= __('Vouchers') ?></th><th class="num"><?= __('Total') ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr><td><strong><?= e((string)$r['label']) ?></strong></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><strong><?= money($r['total']) ?></strong></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="3" class="muted" style="text-align:center;padding:20px"><?= __('No approved expenses in this period.') ?></td></tr><?php else: ?><tr style="font-weight:800;background:#faf7ee"><td><?= __('TOTAL') ?></td><td class="num"><?= array_sum(array_column($rows, 'n')) ?></td><td class="num"><?= money($sum) ?></td></tr><?php endif; ?></tbody></table></div>
</div>
