<?php $title = __('Ledger —') . e($sm['name']); $printName = e($sm['name']); ?>
<div class="print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Salesman Ledger') ?> · <?= $printName ?> · <?= date_display(today()) ?></span><hr></div>
<div class="cards no-print">
  <div class="card <?= $balance > 0 ? 'red' : 'accent' ?>"><div class="lbl"><?= __('Salesman Outstanding (owed to company)') ?></div><div class="val"><?= money($balance) ?></div><div class="sub"><?= __('issue rate × Packets issued on credit') ?></div></div>
</div>
<div class="two-col">
<form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/pay')) ?>" class="panel no-print"><?= csrf_field() ?>
<h2><?= __('Receive Payment From Salesman') ?></h2>
<div class="form-grid"><div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" max="<?= e($balance) ?>" name="amount" required></div>
<div><label class="f"><?= __('Received Into *') ?></label><select class="f" name="account_id" required><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div></div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. Sunday settlement')) ?>">
<button class="btn btn-primary mt" type="submit"><?= __('Post Payment') ?></button>
<p class="help"><?= __('No approval needed for money IN. Dr Cash/Bank, Cr Salesman Receivable — ledger credited.') ?></p>
</form>
<div class="panel"><h2><?= __('Bonus & Payable Summary') ?></h2>
<ul class="list-clean">
<?php $tPaid = (float)db_val("SELECT COALESCE(SUM(total_payable),0) FROM salesman_issues WHERE salesman_id = ? AND issue_type = 'credit_issue' AND status = 'posted'", [$id]); $tBonus = (float)db_val("SELECT COALESCE(SUM(total_bonus_cost),0) FROM salesman_issues WHERE salesman_id = ?", [$id]); ?>
<li><span><?= __('Total credit issued (payable)') ?></span><strong><?= money($tPaid) ?></strong></li>
<li><span><?= __('Total bonus value issued (never charged)') ?></span><strong><?= money($tBonus) ?></strong></li>
<li><span><?= __('Returned stock value') ?></span><strong><?= money((float)db_val("SELECT COALESCE(SUM(line_payable),0) FROM salesman_issue_items i JOIN salesman_issues s ON s.id = i.issue_id WHERE s.salesman_id = ? AND s.issue_type = 'salesman_return'", [$id])) ?></strong></li>
</ul>
<div class="btn-group mt no-print"><a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/return')) ?>"><?= __('Return Stock to Warehouse') ?></a><button class="btn btn-outline" type="button" onclick="window.print()">🖨 <?= __('Print Ledger') ?></button></div>
</div>
</div>
<div class="panel"><h2><?= __('Ledger History') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Doc') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th><th class="num"><?= __('Balance') ?></th><th><?= __('Notes') ?></th></tr></thead>
<tbody><?php $run = 0; foreach ($entries as $l): $run = (float)$l['balance']; ?><tr><td><?= date_display($l['date']) ?></td><td class="mono"><?= e($l['doc_no'] ?? '—') ?></td><td><span class="badge gray"><?= e(strtoupper($l['doc_type'])) ?></span></td>
<td class="num"><?= $l['debit'] ? number_format($l['debit'], 2) : '' ?></td><td class="num"><?= $l['credit'] ? number_format($l['credit'], 2) : '' ?></td><td class="num"><?= number_format($run, 2) ?></td><td><?= e($l['notes'] ?? '') ?></td></tr>
<?php endforeach; ?><?php if (!$entries): ?><tr><td colspan="7" class="muted"><?= __('No ledger entries yet — first credit issue creates them.') ?></td></tr><?php endif; ?></tbody></table></div></div>
</div>
