<?php $title = __('New Cash/Bank Account'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Cash / Bank Account') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Account Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. HBL Main Account / Factory Petty Cash')) ?>"></div>
  <div><label class="f"><?= __('Type *') ?></label><select class="f" name="type" id="accType"><option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option></select></div>
  <div><label class="f"><?= __('Bank Name') ?></label><input class="f" name="bank_name"></div>
  <div><label class="f"><?= __('Account No') ?></label><input class="f" name="account_no"></div>
  <div><label class="f"><?= __('IBAN') ?></label><input class="f" name="iban"></div>
  <div><label class="f"><?= __('Branch') ?></label><input class="f" name="branch"></div>
  <div><label class="f"><?= __('Opening Balance (Rs)') ?></label><input class="f" name="opening_balance" type="number" step="0.01" value="0.00"></div>
  <div><label class="f"><?= __('GL Account (optional)') ?></label><select class="f" name="gl_account_id"><option value=""><?= __('Auto — system default (Cash/Bank)') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['code'] . ' — ' . $a['name']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="help"><?= __('If you set an opening balance it is added to the book balance; the offset (Owner Capital) is posted from Settings → Opening Balances.') ?></p>
<div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Create Account') ?></button><a class="btn btn-outline" href="<?= e(url('cashbank/accounts')) ?>"><?= __('Cancel') ?></a></div>
</form>
