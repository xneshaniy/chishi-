<?php $title = __('Bonus & Free Stock Report'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/bonus-report"><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary" type="submit"><?= __('Filter') ?></button><a class="btn btn-outline" href="<?= e(url('reports/bonus-report', ['from' => $from, 'to' => $to, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a><button class="btn btn-outline no-print" type="button" onclick="window.print()"><?= __('🖨 Print') ?></button></form>
<div class="cards">
  <div class="card gold"><div class="lbl"><?= __('Salesman Bonus Issued (Packets)') ?></div><div class="val"><?= qty($summary['salesman_bonus_qty']) ?></div><div class="sub"><?= __('cost value') ?><?= money($summary['salesman_bonus_cost']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Customer Free Goods (Packets)') ?></div><div class="val"><?= qty($summary['customer_bonus_qty']) ?></div><div class="sub"><?= __('cost value') ?><?= money($summary['customer_bonus_cost']) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Purchase Bonus Received') ?></div><div class="val"><?= qty($summary['purchase_bonus_qty']) ?><?= __('units') ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Period') ?></div><div class="val" style="font-size:15px"><?= date_display($from) ?> →<br><?= date_display($to) ?></div></div>
</div>

<div class="panel print-area">
  <h2><?= __('Salesman Bonus Issues — Paid vs Free') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Salesman') ?></th><th class="num"><?= __('Issues') ?></th><th class="num"><?= __('Paid Qty (Packets)') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th><th class="num"><?= __('Payable') ?></th><th class="num"><?= __('Bonus Cost') ?></th></tr></thead>
  <tbody><?php foreach ($salesmanBonus as $r): ?><tr><td><?= e($r['salesman']) ?></td><td class="num"><?= (int)$r['issues'] ?></td><td class="num"><?= qty($r['paid_qty']) ?></td><td class="num"><strong><?= qty($r['bonus_qty']) ?></strong></td><td class="num"><?= money($r['payable']) ?></td><td class="num"><?= money($r['bonus_cost']) ?></td></tr>
  <?php endforeach; ?><?php if (!$salesmanBonus): ?><tr><td colspan="6" class="muted"><?= __('No salesman bonus issues in range.') ?></td></tr><?php endif; ?></tbody></table></div>

  <h2 class="mt"><?= __('Customer Free Goods (invoice bonus lines)') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Scheme / Product') ?></th><th class="num"><?= __('Lines') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th><th class="num"><?= __('Cost Value') ?></th></tr></thead>
  <tbody><?php foreach ($customerBonus as $r): ?><tr><td><strong><?= e($r['bonus_scheme'] ?: 'No scheme') ?></strong> · <?= e($r['sku']) ?> — <?= e($r['product']) ?></td><td class="num"><?= (int)$r['lines'] ?></td><td class="num"><?= qty($r['qty']) ?></td><td class="num"><?= money($r['cost']) ?></td></tr>
  <?php endforeach; ?><?php if (!$customerBonus): ?><tr><td colspan="4" class="muted"><?= __('No customer bonus lines in range.') ?></td></tr><?php endif; ?></tbody></table></div>

  <h2 class="mt"><?= __('Purchase Bonus (supplier free goods)') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th><th class="num"><?= __('Lines') ?></th></tr></thead>
  <tbody><?php foreach ($purchaseBonus as $r): ?><tr><td><?= e($r['sku']) ?> — <?= e($r['product']) ?></td><td class="num"><?= qty($r['qty']) ?></td><td class="num"><?= (int)$r['lines'] ?></td></tr>
  <?php endforeach; ?><?php if (!$purchaseBonus): ?><tr><td colspan="3" class="muted"><?= __('No purchase bonus recorded in range.') ?></td></tr><?php endif; ?></tbody></table></div>
  <p class="help">Bonus stock is inventory-asset until issued — it appears in Stock Valuation, Stock Ledger and salesman van balances. Customer bonus lines are never billed; salesman bonus is never added to their ledger payable.</p>
</div>
