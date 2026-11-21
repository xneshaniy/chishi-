<?php $title = __('New Bonus'); ?>
<div class="two-col">
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Bonus / Incentive') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Salesman *') ?></label><select class="f" name="salesman_id" required><?php foreach ($salesmen as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Month *') ?></label><input class="f" type="month" name="period_month" value="<?= e(date('Y-m')) ?>" required></div>
  <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
</div>
<label class="f mt"><?= __('Reason') ?></label><input class="f" name="reason" placeholder="<?= e(__('e.g. target achievement, festival incentive')) ?>">
<button class="btn btn-primary mt" type="submit"><?= __('Record Bonus') ?></button>
</form>
<div class="panel"><h2><?= __('Commission Preview —') ?><?= e(date('F Y')) ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Salesman') ?></th><th class="num"><?= __('Month Sales') ?></th><th class="num"><?= __('Comm. %') ?></th><th class="num"><?= __('Commission') ?></th></tr></thead>
<tbody><?php foreach ($commissions as $c): ?><tr><td><?= e($c['name']) ?></td><td class="num"><?= money($c['month_sales'], false) ?></td><td class="num"><?= qty($c['commission_percent']) ?></td><td class="num"><strong><?= money($c['commission'], false) ?></strong></td></tr><?php endforeach; ?></tbody>
</table></div>
<p class="help"><?= __('Commission = posted sales this month × commission %. Pay via Expenses → “Sales Commission” to record it in the ledger.') ?></p></div>
</div>
