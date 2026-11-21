<?php $title = __('General Ledger'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/ledger">
  <select name="account_id" required onchange="this.form.submit()"><option value="0"><?= __('— select account —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $account && (int)$account['id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['code'] . ' — ' . $a['name']) ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary" type="submit"><?= __('Show') ?></button>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<?php if ($account): ?>
<div class="panel print-area">
  <h2><?= __('Ledger —') ?><?= e($account['code'] . ' · ' . $account['name']) ?> <span class="muted" style="font-size:12px"><?= date_display($from) ?> → <?= date_display($to) ?></span></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Voucher') ?></th><th><?= __('Type') ?></th><th><?= __('Narration') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th><th class="num"><?= __('Balance') ?></th></tr></thead>
  <tbody><tr><td><?= date_display($from) ?></td><td><?= __('OB') ?></td><td><?= __('OPENING') ?></td><td><?= __('Balance brought forward') ?></td><td></td><td></td><td class="num"><?= number_format($opening, 2) ?></td></tr>
  <?php $run = $opening; foreach ($rows as $r): $run += (float)$r['debit'] - (float)$r['credit']; ?>
  <tr><td><?= date_display($r['date']) ?></td><td class="mono"><?= e($r['entry_no']) ?></td><td><?= e($r['voucher_type']) ?></td><td><?= e($r['line_memo'] ?: $r['memo']) ?></td>
  <td class="num"><?= $r['debit'] ? number_format($r['debit'], 2) : '' ?></td><td class="num"><?= $r['credit'] ? number_format($r['credit'], 2) : '' ?></td><td class="num"><?= number_format($run, 2) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><th colspan="4"><?= __('Closing Balance') ?></th><th colspan="2"></th><th class="num"><?= number_format($closing, 2) ?></th></tr></tfoot></table></div>
</div>
<?php endif; ?>
