<?php $title = __('Balance Sheet'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/balance-sheet">
  <label class="muted"><?= __('As of') ?></label><input type="date" name="as_of" value="<?= e($asOf) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Compute') ?></button>
  <a class="btn btn-outline" href="<?= e(url('accounting/balance-sheet', ['as_of' => $asOf, 'export' => 'csv'])) ?>"><?= __('Export CSV') ?></a>
  <button class="btn btn-outline" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<div class="alert <?= $bs['balanced'] ? 'alert-success' : 'alert-error' ?>"><strong><?= $bs['balanced'] ? '✓ Balance Sheet agrees (Assets = Liabilities + Equity)' : '✗ OUT OF BALANCE — check opening balances and journal entries' ?></strong></div>
<div class="panel print-area">
  <h2><?= __('Balance Sheet — as of') ?><?= e(date_display($bs['as_of'])) ?></h2>
  <div class="two-col">
    <div><h3 style="font-size:13px;color:var(--green-dark)"><?= __('ASSETS') ?></h3>
      <div class="table-wrap"><table class="tbl"><tbody><?php foreach ($bs['assets'] as $r): ?><tr><td><code><?= e($r['code']) ?></code> <?= e($r['name']) ?></td><td class="num"><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Total Assets') ?></th><th class="num"><?= number_format($bs['total_assets'], 2) ?></th></tr></tfoot></table></div></div>
    <div>
      <h3 style="font-size:13px;color:var(--red)"><?= __('LIABILITIES') ?></h3>
      <div class="table-wrap"><table class="tbl"><tbody><?php foreach ($bs['liabilities'] as $r): ?><tr><td><code><?= e($r['code']) ?></code> <?= e($r['name']) ?></td><td class="num"><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Total Liabilities') ?></th><th class="num"><?= number_format($bs['total_liabilities'], 2) ?></th></tr></tfoot></table></div>
      <h3 style="font-size:13px;color:var(--blue);margin-top:14px"><?= __('EQUITY') ?></h3>
      <div class="table-wrap"><table class="tbl"><tbody><?php foreach ($bs['equity'] as $r): ?><tr><td><code><?= e($r['code']) ?></code> <?= e($r['name']) ?></td><td class="num"><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><th><?= __('Total Equity') ?></th><th class="num"><?= number_format($bs['total_equity'], 2) ?></th></tr></tfoot></table></div>
    </div>
  </div>
  <div style="text-align:center;border-top:3px double var(--charcoal);margin-top:14px;padding-top:12px">
    <div class="help"><?= __('LIABILITIES + EQUITY') ?></div>
    <div style="font-size:26px;font-weight:800"><?= __('Rs') ?><?= number_format($bs['total_liabilities'] + $bs['total_equity'], 2) ?></div>
  </div>
</div>
