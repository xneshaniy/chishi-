<?php $title = __('Bonus —') . e($sm['name']); $tab = 'bonus'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Bonus Packets') ?></div><div class="sc-val"><?= qty($totalBonusUnits) ?></div><div class="sc-sub"><?= __('free goods given') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bonus Cost') ?></div><div class="sc-val"><?= money($totalBonusCost) ?></div><div class="sc-sub"><?= __('posted to PROMO') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Decisions') ?></div><div class="sc-val"><?= count($bonusLines) ?></div><div class="sc-sub"><?= __('bonus issues in history') ?></div></div>
</div>

<form method="post" class="simple-form" style="max-width:900px"><?= csrf_field() ?><h2><?= __('Add Bonus Goods') ?></h2>
<p class="help" style="margin:-4px 0 12px"><?= __('Bonus goods move from main warehouse to the salesman’s stock. Stock reduces. Payable does NOT change — free goods, paid by the company as promotion expense.') ?></p>
<div class="big-fields">
  <div class="big-field"><label><?= __('Product *') ?></label><select class="f" name="product_id" required><option value=""><?= __('— select —') ?></option><?php foreach ($productsArr as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="big-field"><label><?= __('Bonus Quantity (Packets) *') ?></label><input class="f" type="number" step="0.001" min="0.001" name="bonus_qty" required placeholder="0"></div>
  <div class="big-field"><label><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div class="big-field"><label><?= __('Scheme (optional)') ?></label><select class="f" name="scheme_id"><option value=""><?= __('— none —') ?></option><?php foreach ($schemes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?><?= $s['requires_approval'] ? ' (' . __('needs approval') . ')' : '' ?></option><?php endforeach; ?></select></div>
  <div class="span-4"><label><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= __('e.g. monthly target achieved, special scheme, Diwali gift') ?>"></div>
</div>
<button class="btn btn-primary btn-xl mt" type="submit"><?= __('Give Bonus Goods') ?></button>
</form>

<div class="panel" style="margin-top:14px">
  <h2><?= __('Bonus History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Qty (Packets)') ?></th><th class="num"><?= __('Cost') ?></th><th><?= __('Scheme') ?></th><th><?= __('Reason') ?></th></tr></thead>
  <tbody><?php foreach ($bonusLines as $b): ?><tr><td><?= date_display($b['date']) ?></td><td><?= e($b['sku'] . ' — ' . $b['name']) ?></td><td class="num"><?= qty($b['qty']) ?></td><td class="num"><?= money($b['bonus_cost']) ?></td><td><?= e($b['bonus_scheme'] ?: '—') ?></td><td><?= e($b['bonus_reason'] ?: '—') ?></td></tr><?php endforeach; ?><?php if (!$bonusLines): ?><tr><td colspan="6" class="muted"><?= __('No bonus goods given yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
