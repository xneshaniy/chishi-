<?php $title = __('Profit & Loss'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/pnl">
  <label class="muted"><?= __('From') ?></label><input type="date" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('To') ?></label><input type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Compute') ?></button>
  <a class="btn btn-outline" href="<?= e(url('accounting/pnl', ['from' => $from, 'to' => $to, 'export' => 'csv'])) ?>"><?= __('Export CSV') ?></a>
  <button class="btn btn-outline" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<div class="panel print-area">
  <h2><?= __('Income Statement —') ?><?= e(date_display($pl['from'])) ?><?= __('to') ?><?= e(date_display($pl['to'])) ?></h2>
  <div class="two-col">
    <div><h3 style="font-size:13px;color:var(--green-dark)"><?= __('INCOME') ?></h3>
      <div class="table-wrap"><table class="tbl"><tbody><?php foreach ($pl['income'] as $r): ?><tr><td><code><?= e($r['code']) ?></code> <?= e($r['name']) ?></td><td class="num"><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Total Income') ?></th><th class="num"><?= number_format($pl['total_income'], 2) ?></th></tr></tfoot></table></div></div>
    <div><h3 style="font-size:13px;color:var(--red)"><?= __('EXPENSES') ?></h3>
      <div class="table-wrap"><table class="tbl"><tbody><?php foreach ($pl['expense'] as $r): ?><tr><td><code><?= e($r['code']) ?></code> <?= e($r['name']) ?></td><td class="num"><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Total Expenses') ?></th><th class="num"><?= number_format($pl['total_expense'], 2) ?></th></tr></tfoot></table></div></div>
  </div>
  <div style="text-align:center;border-top:3px double var(--charcoal);margin-top:14px;padding-top:12px">
    <div class="help"><?= __('NET PROFIT / (LOSS)') ?></div>
    <div style="font-size:30px;font-weight:800;color:<?= $pl['net_profit'] >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= money($pl['net_profit']) ?></div>
  </div>
</div>
