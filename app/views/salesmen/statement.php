<?php $title = 'Statement — ' . e($sm['name']); ?>
<form method="get" class="toolbar no-print"><input type="hidden" name="r" value="salesmen/<?= (int)$sm['id'] ?>/statement"><label class="muted"><?= __('From') ?></label><input class="f" type="date" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('To') ?></label><input class="f" type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-outline" type="submit"><?= __('Show') ?></button><button class="btn btn-primary" type="button" onclick="window.print()">🖨 <?= __('Print Statement') ?></button></form>

<div class="panel print-area">
  <div class="print-only" style="text-align:center;margin-bottom:12px"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Salesman Statement') ?> — <?= e($sm['name']) ?> (<?= e($sm['code']) ?>) · <?= date_display($from) ?> → <?= date_display($to) ?></span><hr></div>

  <h2><?= __('Opening Balance') ?></h2>
  <p class="mono" style="font-size:16px"><?= $opening >= 0 ? '+' : '' ?><?= money($opening) ?> <span class="muted" style="font-size:11px">(<?= $opening >= 0 ? __('salesman owes company') : __('company owes salesman') ?>)</span></p>

  <h2 style="margin-top:16px"><?= __('Goods Issued to Salesman') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Issue') ?></th><th class="num"><?= __('Paid (Packets)') ?></th><th class="num"><?= __('Bonus (Packets)') ?></th><th class="num"><?= __('Payable') ?></th></tr></thead>
    <tbody><?php foreach ($issues as $i): ?><tr><td><?= date_display($i['date']) ?></td><td class="mono"><?= e($i['issue_no']) ?></td><td class="num"><?= qty($i['paid_qty']) ?></td><td class="num"><?= qty($i['bonus_qty']) ?></td><td class="num"><?= money($i['payable'], false) ?></td></tr><?php endforeach; ?><?php if (!$issues): ?><tr><td colspan="5" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>

  <h2 style="margin-top:16px"><?= __('Goods Returned') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Return') ?></th><th class="num"><?= __('Qty (Packets)') ?></th></tr></thead>
    <tbody><?php foreach ($returns as $r): ?><tr><td><?= date_display($r['date']) ?></td><td class="mono"><?= e($r['issue_no']) ?></td><td class="num"><?= qty($r['qty']) ?></td></tr><?php endforeach; ?><?php if (!$returns): ?><tr><td colspan="3" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>

  <h2 style="margin-top:16px"><?= __('Customer Sales &amp; Collections') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Sales') ?></th><th class="num"><?= __('Collected') ?></th><th class="num"><?= __('Cash Counted') ?></th><th class="num"><?= __('Variance') ?></th></tr></thead>
    <tbody><?php foreach ($settlements as $s): ?><tr><td><?= date_display($s['date']) ?></td><td class="num"><?= money($s['sales_total'], false) ?></td><td class="num"><?= money($s['collected_total'], false) ?></td><td class="num"><?= money($s['cash_counted'], false) ?></td><td class="num"><?= ($s['variance'] >= 0 ? '+' : '') . number_format($s['variance'], 2) ?></td></tr><?php endforeach; ?><?php if (!$settlements): ?><tr><td colspan="5" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>

  <h2 style="margin-top:16px"><?= __('Cash Deposited by Salesman') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Reference') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Note') ?></th></tr></thead>
    <tbody><?php foreach ($payments as $p): ?><tr><td><?= date_display($p['date']) ?></td><td class="mono"><?= e($p['doc_no']) ?></td><td class="num"><?= money($p['credit'], false) ?></td><td><?= e($p['notes']) ?></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="4" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>

  <div class="two-col" style="margin-top:16px">
    <div>
      <h2><?= __('Company Expenses Paid for Salesman') ?></h2>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Description') ?></th></tr></thead>
        <tbody><?php foreach ($expenses as $e): ?><tr><td><?= date_display($e['date']) ?></td><td><?= e(strtoupper($e['expense_type'])) ?></td><td class="num"><?= money($e['amount'], false) ?></td><td><?= e($e['description']) ?></td></tr><?php endforeach; ?><?php if (!$expenses): ?><tr><td colspan="4" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div>
      <h2><?= __('Salary &amp; Advances') ?></h2>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Period / Reason') ?></th><th><?= __('Method') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
        <tbody><?php foreach ($salary as $s): ?><tr><td><?= date_display($s['date']) ?></td><td><?= e($s['period']) ?> · <?= e($s['status']) ?></td><td><?= e($s['method']) ?></td><td class="num"><?= money($s['amount'], false) ?></td></tr><?php endforeach; ?>
        <?php foreach ($advances as $a): ?><tr><td><?= date_display($a['date']) ?></td><td><?= e($a['reason']) ?> · <?= e($a['status']) ?></td><td><?= e($a['method']) ?></td><td class="num"><?= money($a['amount'], false) ?></td></tr><?php endforeach; ?><?php if (!$salary && !$advances): ?><tr><td colspan="4" class="muted"><?= __('None in this period.') ?></td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>

  <h2 style="margin-top:16px"><?= __('Closing Position') ?></h2>
  <div class="att-counts">
    <span class="att-count"><?= __('Closing Stock') ?><b><?= qty($stockRow['qty']) ?> <span style="font-size:11px">KG</span></b></span>
    <span class="att-count"><?= __('Stock Value') ?><b><?= money($stockRow['value']) ?></b></span>
    <span class="att-count" style="border-color:#bfe3cd"><?= __('Total Payable') ?><b style="color:#0a4d29"><?= money($goodsPayable) ?></b></span>
  </div>
  <p class="help" style="margin-top:12px;text-align:right"><?= __('Generated') ?>: <?= dt_display(now_local()) ?></p>
</div>
