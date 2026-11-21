<?php $title = __('Bank Reconciliation'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="cashbank/reconcile">
<label class="muted"><?= __('Bank Account:') ?></label><select name="account_id" onchange="this.form.submit()"><option value="0"><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></form>
<?php if ($data): ?>
<div class="panel">
  <h2><?= __('Reconciliation Summary') ?></h2>
  <div class="table-wrap"><table class="tbl"><tbody>
    <tr><td><?= __('Opening balance') ?></td><td class="num"><?= money($data['opening']) ?></td></tr>
    <tr><td><?= __('+ All posted GL movements (book)') ?></td><td class="num"><?= money($data['gl']) ?></td></tr>
    <tr><th><?= __('Book balance (per ERP)') ?></th><th class="num"><?= money($data['book']) ?></th></tr>
    <tr><td><?= __('+ Cheques received, cleared (per cheque register)') ?></td><td class="num"><?= money($data['clearedIn']) ?></td></tr>
    <tr><td><?= __('− Cheques issued, cleared') ?></td><td class="num"><?= money($data['clearedOut']) ?></td></tr>
  </tbody></table></div>
  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Uncleared / pending items (timing differences)') ?></h3>
  <ul class="list-clean">
    <li><span><?= __('Pending cheques received (not yet cleared)') ?></span><strong><?= money($data['pendingIn']) ?></strong></li>
    <li><span><?= __('Pending cheques issued (not yet cleared)') ?></span><strong><?= money($data['pendingOut']) ?></strong></li>
  </ul>
  <p class="help">Method: compare the bank statement balance to the book balance; the difference should equal pending cheques + bank charges not yet entered. Any residual difference indicates an error or a missing voucher — investigate via the General Ledger for this account (Accounting → General Ledger).</p>
</div>
<?php else: ?><div class="alert alert-info"><?= __('Select a bank account to see the reconciliation summary.') ?></div><?php endif; ?>
