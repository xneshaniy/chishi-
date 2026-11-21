<?php $title = $customer ? 'Edit Customer' : 'New Customer'; ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= $customer ? 'Edit Customer — ' . e($customer['code']) : 'New Customer / Shopkeeper' ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Customer Code *') ?></label><input class="f" name="code" required value="<?= e($customer['code'] ?? $suggestedCode) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Shop / Customer Name *') ?></label><input class="f" name="name" required value="<?= e($customer['name'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($customer['phone'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Alt Phone') ?></label><input class="f" name="alt_phone" value="<?= e($customer['alt_phone'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Area / Route') ?></label><input class="f" name="area" value="<?= e($customer['area'] ?? '') ?>" placeholder="<?= e(__('e.g. Gawalmandi')) ?>"></div>
  <div><label class="f"><?= __('City') ?></label><input class="f" name="city" value="<?= e($customer['city'] ?? '') ?>"></div>
  <div><label class="f"><?= __('NTN (optional)') ?></label><input class="f" name="ntn" value="<?= e($customer['ntn'] ?? '') ?>"></div>
  <div><label class="f"><?= __('Credit Limit (Rs)') ?></label><input class="f" name="credit_limit" type="number" step="0.01" min="0" value="<?= e($customer['credit_limit'] ?? '0.00') ?>"></div>
  <div><label class="f"><?= __('Assigned Salesman') ?></label><select class="f" name="salesman_id"><option value="">—</option><?php foreach ($salesmen as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ((int)($customer['salesman_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Opening Balance (Rs)') ?></label><input class="f" name="opening_balance" type="number" step="0.01" min="0" value="<?= e($customer['opening_balance'] ?? '0.00') ?>"></div>
  <div><label class="f"><?= __('Opening Type') ?></label><select class="f" name="opening_type"><option value="debit" <?= ($customer['opening_type'] ?? 'debit') === 'debit' ? 'selected' : '' ?>><?= __('Debit (receivable)') ?></option><option value="credit" <?= ($customer['opening_type'] ?? '') === 'credit' ? 'selected' : '' ?>><?= __('Credit') ?></option></select></div>
</div>
<label class="f mt"><?= __('Address') ?></label><input class="f" name="address" value="<?= e($customer['address'] ?? '') ?>">
<?php if ($customer): ?><label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" value="1" <?= !empty($customer['is_active']) ? 'checked' : '' ?>><?= __('Active') ?></label><?php endif; ?>
<p class="help"><?= __('Opening balances post to the ledger only when you run') ?><a href="<?= e(url('settings/opening')) ?>"><?= __('Settings → Opening Balances') ?></a>.</p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save Customer') ?></button><a class="btn btn-outline" href="<?= e(url('customers')) ?>"><?= __('Cancel') ?></a></div>
</form>
