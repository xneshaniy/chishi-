<?php $title = __('New Payment'); ?>
<form method="post" class="panel"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Payment — Supplier / Owner Drawing') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Payee Type *') ?></label><select class="f" name="party_type" id="partyType"><option value="supplier"><?= __('Supplier') ?></option><option value="owner"><?= __('Owner (personal drawing)') ?></option></select></div>
  <div id="supplierWrap"><label class="f"><?= __('Supplier *') ?></label><select class="f" name="party_id" id="partyId"><option value=""><?= __('— select —') ?></option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' — ' . $s['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Pay From *') ?></label><select class="f" name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label class="f"><?= __('Reference') ?></label><input class="f" name="reference" placeholder="<?= e(__('Cheque / slip / bill no')) ?>"></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes">
<p class="help"><?= __('Supplier payments debit Accounts Payable. Owner drawings debit the Drawings equity account (never an expense) — your profit report stays clean.') ?></p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Payment') ?></button><a class="btn btn-outline" href="<?= e(url('cashbank/payments')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const t = document.getElementById('partyType');
t.addEventListener('change', () => { document.getElementById('supplierWrap').style.display = t.value === 'supplier' ? '' : 'none'; });
</script>
