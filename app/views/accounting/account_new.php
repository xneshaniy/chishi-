<?php $title = __('New GL Account'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New General Ledger Account') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Account Code *') ?></label><input class="f" name="code" required placeholder="e.g. 5660"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Account Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. Insurance Expense')) ?>"></div>
  <div><label class="f"><?= __('Type *') ?></label><select class="f" name="type"><?php foreach (['asset','liability','equity','income','expense'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Parent (optional)') ?></label><select class="f" name="parent_id"><option value=""><?= __('— none —') ?></option><?php foreach ($parents as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option><?php endforeach; ?></select></div>
</div>
<label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="allow_posting" value="1" checked><?= __('Allow direct posting to this account') ?></label>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Create Account') ?></button><a class="btn btn-outline" href="<?= e(url('accounting/accounts')) ?>"><?= __('Cancel') ?></a></div>
</form>
