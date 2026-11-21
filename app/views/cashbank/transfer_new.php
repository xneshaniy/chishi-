<?php $title = __('New Transfer'); ?>
<form method="post" class="panel"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Cash ↔ Bank / Bank ↔ Bank Transfer') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('From Account *') ?></label><select class="f" name="from_account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('To Account *') ?></label><select class="f" name="to_account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label class="f"><?= __('Reference') ?></label><input class="f" name="reference"></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. cash deposit to HBL main')) ?>">
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Transfer') ?></button><a class="btn btn-outline" href="<?= e(url('cashbank/transfers')) ?>"><?= __('Cancel') ?></a></div>
</form>
