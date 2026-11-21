<?php $title = __('Gift Reports'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/gifts">
  <label class="muted"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>">
  <label class="muted"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Money Gifts') ?></div><div class="sc-val" style="font-size:22px"><?= money($money) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Gifts (Packets)') ?></div><div class="sc-val"><?= qty($goodsCount) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('People Gifted') ?></div><div class="sc-val"><?= count($people) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Gifts in period') ?></div><div class="sc-val"><?= count($rows) ?></div></div>
</div>
<?php if ($byMonth): ?>
<div class="panel print-area"><h2 style="margin-top:0"><?= __('Gifts by Month') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Month') ?></th><th class="num"><?= __('Money') ?></th><th class="num"><?= __('Count') ?></th></tr></thead>
<tbody><?php foreach ($byMonth as $m): ?><tr><td class="mono"><?= e($m['ym']) ?></td><td class="num"><?= money($m['money']) ?></td><td class="num"><?= (int)$m['c'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>
<?php if ($people): ?>
<div class="panel print-area"><h2 style="margin-top:0"><?= __('By Recipient') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Recipient') ?></th><th class="num"><?= __('Gifts') ?></th></tr></thead>
<tbody><?php foreach ($people as $name => $c): ?><tr><td><?= e($name) ?></td><td class="num"><?= (int)$c ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>
<div class="panel print-area"><h2 style="margin-top:0"><?= __('All Gifts —') . ' ' . e(date_display($from)) . ' — ' . e(date_display($to)) ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Gift #') ?></th><th><?= __('Recipient') ?></th><th><?= __('Type') ?></th><th><?= __('Product / Amount') ?></th><th><?= __('Reason') ?></th><th><?= __('Recorded by') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr>
  <td><?= e(date_display($r['gift_date'])) ?></td>
  <td class="mono"><?= e($r['gift_no']) ?></td>
  <td><strong><?= e($r['recipient_name']) ?></strong></td>
  <td><span class="badge <?= $r['gift_type'] === 'money' ? 'amber' : 'blue' ?>"><?= e(strtoupper($r['gift_type'])) ?></span></td>
  <td><?= $r['gift_type'] === 'money' ? money($r['amount']) : (($r['product_name'] ? e($r['product_name']) . ' · ' : '') . qty($r['qty_pieces']) . ' ' . __('Packets')) ?></td>
  <td><?= e($r['reason'] ?? '—') ?></td>
  <td><?= e($r['recorded_by_username'] ?? '—') ?></td>
  <td><span class="badge <?= $r['status'] === 'posted' ? 'green' : 'amber' ?>"><?= e(strtoupper($r['status'])) ?></span></td>
</tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= __('No gifts in this period.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
