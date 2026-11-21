<?php $title = __('Cash Flow'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/cash-flow">
  <label class="muted"><?= __('From') ?></label><input type="date" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('To') ?></label><input type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Compute') ?></button>
  <button class="btn btn-outline" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button>
</form>
<div class="panel print-area">
  <h2><?= __('Cash Flow Statement —') ?><?= e(date_display($cf['from'])) ?><?= __('to') ?><?= e(date_display($cf['to'])) ?></h2>
  <div class="cards">
    <div class="card accent"><div class="lbl"><?= __('Opening Cash & Bank') ?></div><div class="val"><?= money($cf['opening_cash']) ?></div></div>
    <div class="card <?= $cf['net_movement'] >= 0 ? 'accent' : 'red' ?>"><div class="lbl"><?= __('Net Movement') ?></div><div class="val"><?= money($cf['net_movement']) ?></div></div>
    <div class="card gold"><div class="lbl"><?= __('Closing Cash & Bank') ?></div><div class="val"><?= money($cf['closing_cash']) ?></div></div>
  </div>
  <p class="help"><?= e($cf['note']) ?></p>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Voucher') ?></th><th><?= __('Memo') ?></th><th class="num"><?= __('Net Cash') ?></th></tr></thead>
  <tbody><?php foreach ($cf['entries'] as $e): $n = (float)$e['cash_net']; if (abs($n) < 0.004) continue; ?>
  <tr><td><?= date_display($e['date']) ?></td><td class="mono"><?= e($e['entry_no']) ?></td><td><?= e($e['memo']) ?></td><td class="num" style="color:<?= $n >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= ($n >= 0 ? '+' : '') . number_format($n, 2) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
