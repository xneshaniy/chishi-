<?php $title = __('Trial Balance'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/trial-balance">
  <label class="muted"><?= __('From') ?></label><input type="date" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('To') ?></label><input type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Compute') ?></button>
  <a class="btn btn-outline" href="<?= e(url('accounting/trial-balance', ['from' => $from, 'to' => $to, 'export' => 'csv'])) ?>"><?= __('Export CSV') ?></a>
  <button class="btn btn-outline" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<div class="alert <?= $tb['balanced'] ? 'alert-success' : 'alert-error' ?>"><strong><?= $tb['balanced'] ? '✓ Trial Balance agrees' : '✗ OUT OF BALANCE — investigate immediately' ?></strong><?= __('· Total Debits') ?><?= money($tb['total_debit']) ?><?= __('= Total Credits') ?><?= money($tb['total_credit']) ?></div>
<div class="panel print-area"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Account') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th></tr></thead>
<tbody><?php foreach ($tb['rows'] as $r): if ($r['debit'] == 0 && $r['credit'] == 0) continue; ?>
<tr><td class="mono"><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['type']) ?></td><td class="num"><?= $r['debit'] ? number_format($r['debit'], 2) : '' ?></td><td class="num"><?= $r['credit'] ? number_format($r['credit'], 2) : '' ?></td></tr>
<?php endforeach; ?></tbody>
<tfoot><tr><th colspan="3"><?= __('TOTALS') ?></th><th class="num"><?= number_format($tb['total_debit'], 2) ?></th><th class="num"><?= number_format($tb['total_credit'], 2) ?></th></tr></tfoot></table></div></div>
