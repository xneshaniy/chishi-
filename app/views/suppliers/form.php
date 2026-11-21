<?php $title = $supplier ? 'Edit Supplier' : 'New Supplier'; ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= $supplier ? 'Edit Supplier — ' . e($supplier['code']) : 'New Supplier' ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Supplier Code *') ?></label><input class="f" name="code" required value="<?= e($supplier['code'] ?? $suggestedCode) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Supplier / Company Name *') ?></label><input class="f" name="name" required value="<?= e($supplier['name'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($supplier['phone'] ?? '') ?>"></div>
  <div><label class="f"><?= __('City') ?></label><input class="f" name="city" value="<?= e($supplier['city'] ?? '') ?>"></div>
  <div><label class="f"><?= __('NTN (optional)') ?></label><input class="f" name="ntn" value="<?= e($supplier['ntn'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Opening Balance (Rs)') ?></label><input class="f" name="opening_balance" type="number" step="0.01" min="0" value="<?= e($supplier['opening_balance'] ?? '0.00') ?>"></div>
  <div><label class="f"><?= __('Opening Type') ?></label><select class="f" name="opening_type"><option value="credit" <?= ($supplier['opening_type'] ?? 'credit') === 'credit' ? 'selected' : '' ?>><?= __('Credit (payable)') ?></option><option value="debit" <?= ($supplier['opening_type'] ?? '') === 'debit' ? 'selected' : '' ?>><?= __('Debit') ?></option></select></div>
</div>
<label class="f mt"><?= __('Address') ?></label><input class="f" name="address" value="<?= e($supplier['address'] ?? '') ?>">
<?php if ($supplier): ?><label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= !empty($supplier['is_active']) ? 'checked' : '' ?>><?= __('Active') ?></label><?php endif; ?>
<p class="help"><?= __('Opening balances post to the ledger via') ?><a href="<?= e(url('settings/opening')) ?>"><?= __('Settings → Opening Balances') ?></a>.</p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save Supplier') ?></button><a class="btn btn-outline" href="<?= e(url('suppliers')) ?>"><?= __('Cancel') ?></a></div>
</form>
