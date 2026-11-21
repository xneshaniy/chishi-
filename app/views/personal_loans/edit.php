<?php $title = __('Edit Loan') . ' — ' . e((string)($loan['loan_no'] ?? '')); ?>
<div class="alert alert-info"><?= __('Personal record — loans and repayments tracked here are NOT part of company accounts.') ?></div>
<form method="post" action="<?= e(url('personal-loans/' . (int)$loan['id'] . '/edit')) ?>" class="panel simple-form print-area"><?= csrf_field() ?>
  <h2><?= __('Edit Loan') . ' ' . e($loan['loan_no']) ?></h2>
  <p class="help"><?= __('Original loan amount cannot be reduced below the total already repaid.') ?></p>
  <div class="form-grid">
    <div><label class="f"><?= __('Person’s full name *') ?></label><input class="f" name="person_name" required value="<?= e($loan['person_name']) ?>"></div>
    <div><label class="f"><?= __('Contact number') ?></label><input class="f" name="phone" value="<?= e($loan['phone'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Loan date *') ?></label><input class="f" type="date" name="loan_date" required value="<?= e($loan['loan_date']) ?>"></div>
    <div><label class="f"><?= __('Original loan amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="original_amount" required value="<?= e($loan['original_amount']) ?>"></div>
    <div><label class="f"><?= __('Expected repayment date') ?></label><input class="f" type="date" name="expected_date" value="<?= e($loan['expected_date'] ?? '') ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description / reason') ?></label><input class="f" name="description" value="<?= e($loan['description'] ?? '') ?>"></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2"><?= e($loan['notes'] ?? '') ?></textarea></div>
  </div>
  <p class="help"><?= __('Total repaid so far:') ?> <strong><?= money($loan['total_repaid']) ?></strong> · <?= __('Remaining:') ?> <strong><?= money($loan['remaining_amount']) ?></strong></p>
  <div class="btn-group mt">
    <button class="btn btn-primary" type="submit"><?= __('Save Changes') ?></button>
    <a class="btn btn-outline" href="<?= e(url('personal-loans/' . (int)$loan['id'])) ?>"><?= __('Cancel') ?></a>
  </div>
</form>
