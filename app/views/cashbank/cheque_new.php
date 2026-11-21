<?php $title = __('New Cheque'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('Record Cheque') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Bank Account *') ?></label><select class="f" name="bank_account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Cheque No *') ?></label><input class="f" name="cheque_no" required></div>
  <div><label class="f"><?= __('Direction') ?></label><select class="f" name="direction"><option value="received"><?= __('Received from customer') ?></option><option value="issued"><?= __('Issued to supplier/other') ?></option></select></div>
  <div><label class="f"><?= __('Party Type') ?></label><select class="f" name="party_type"><option value="customer"><?= __('Customer') ?></option><option value="supplier"><?= __('Supplier') ?></option><option value="other"><?= __('Other') ?></option></select></div>
  <div><label class="f"><?= __('Party Name') ?></label><input class="f" name="party_name"></div>
  <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label class="f"><?= __('Issue Date') ?></label><input class="f" type="date" name="issue_date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Due / Clearing Date') ?></label><input class="f" type="date" name="due_date"></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes">
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Record Cheque') ?></button><a class="btn btn-outline" href="<?= e(url('cashbank/cheques')) ?>"><?= __('Cancel') ?></a></div>
</form>
