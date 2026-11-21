<?php $title = __('New Expense Category'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Expense Category') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Category Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. Vehicle Insurance')) ?>"></div>
  <div><label class="f"><?= __('Linked GL Account *') ?></label><select class="f" name="gl_account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['code'] . ' — ' . $a['name']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="help"><?= __('The category\'s linked account receives the debit when an expense is posted. Owner drawings categories link to the Drawings equity account.') ?></p>
<div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Add Category') ?></button><a class="btn btn-outline" href="<?= e(url('expenses/categories')) ?>"><?= __('Cancel') ?></a></div>
</form>
