<?php $title = __('Commission & Bonuses'); ?>
<div class="toolbar"><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('salesmen/bonuses/new')) ?>"><?= __('+ New Bonus') ?></a></div>
<div class="panel"><h2><?= __('Recent Bonuses') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Salesman') ?></th><th><?= __('Month') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Reason') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $b): ?><tr><td class="mono"><?= e($b['bonus_no']) ?></td><td><?= e($b['salesman']) ?></td><td><?= e($b['period_month']) ?></td><td class="num"><?= money($b['amount']) ?></td><td><?= e($b['reason'] ?? '') ?></td><td><span class="badge <?= $b['status'] === 'paid' ? 'green' : 'amber' ?>"><?= e(strtoupper($b['status'])) ?></span></td></tr><?php endforeach; ?></tbody>
</table></div>
<p class="help"><?= __('Bonus payouts: create an Expense with category “Sales Commission” to post the journal (Dr Commission Expense / Cr Cash-Bank).') ?></p></div>
