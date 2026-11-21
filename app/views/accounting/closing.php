<?php $title = __('Fiscal Year Closing'); ?>
<div class="alert alert-warn"><strong><?= __('Year-end procedure.') ?></strong> <?= __('Closing transfers all income & expense balances to Retained Earnings (two closing journal entries, dated on the FY end date) and closes every period of the year.') ?> <u><?= __('Post all pending vouchers and verify the Trial Balance first.') ?></u><?= __('This cannot be undone from the UI (you would need to reverse the CL entries manually).') ?></div>
<div class="panel"><h2><?= __('Fiscal Years') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Year') ?></th><th><?= __('Period') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($years as $y): ?><tr><td><strong><?= e($y['name']) ?></strong></td><td><?= date_display($y['start_date']) ?> → <?= date_display($y['end_date']) ?></td>
<td><?= $y['is_closed'] ? '<span class="badge red">CLOSED</span>' : ($y['is_active'] ? '<span class="badge green">ACTIVE</span>' : '<span class="badge gray">OPEN</span>') ?></td>
<td><?php if (!$y['is_closed']): ?>
<form method="post" style="display:flex;gap:6px;align-items:center" data-confirm="<?= e(sprintf(__('Close %s? All income/expense accounts roll into Retained Earnings.'), $y['name'])) ?>"><?= csrf_field() ?>
  <input type="hidden" name="fiscal_year_id" value="<?= (int)$y['id'] ?>">
  <input class="f" name="confirm" placeholder="<?= e(__('Type CLOSE')) ?>" required style="width:110px">
  <button class="btn btn-danger btn-sm" type="submit"><?= __('Close Year') ?></button>
</form>
<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<p class="help">Current-year revenue/expense balances are excluded from the Balance Sheet's opening retained earnings by the closing entries; the Balance Sheet automatically includes “Current Period Net Profit” until you close.</p>
</div>
