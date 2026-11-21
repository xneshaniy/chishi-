<?php $title = __('New Expense'); ?>
<?php $PERSON_KEYS = [10, 20, 30, 40]; ?>
<form method="post" enctype="multipart/form-data" class="panel"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Daily Expense') ?><span class="muted" style="font-size:12px"> · <?= __('factory, guest, tea, meals, fuel, office, owner drawings…') ?></span></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Category *') ?></label><select class="f" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label class="f"><?= __('Person / Guest Name') ?></label><input class="f" name="person_name" placeholder="<?= e(__('e.g. Mr. Khan / Guest / Driver')) ?>"></div>
  <div><label class="f"><?= __('Quantity') ?></label><input class="f" type="number" step="0.001" min="0" name="qty" placeholder="<?= e(__('optional, e.g. 4 kg, 3 plates')) ?>"></div>
  <div><label class="f"><?= __('Payment Type') ?></label><select class="f" name="payment_type" id="payType"><option value="cash"><?= __('Paid from Cash') ?></option><option value="bank"><?= __('Paid from Bank') ?></option><option value="payable"><?= __('On credit (expense payable)') ?></option></select></div>
  <div id="accountWrap"><label class="f"><?= __('Cash / Bank Account') ?></label><select class="f" name="account_id" id="accountId"><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" data-type="<?= e($a['type']) ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div class="span-2"><label class="f"><?= __('Description / Bill Details') ?></label><input class="f" name="description" placeholder="<?= e(__('e.g. Guest dinner for 4 — Nadeem & family')) ?>" required></div>
  <div><label class="f"><?= __('Payee / Vendor') ?></label><input class="f" name="payee" placeholder="<?= e(__('e.g. Restaurant, LESCO, driver, landlord')) ?>"></div>
  <div><label class="f"><?= __('Receipt / Photo') ?></label><input class="f" type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf"><span class="muted" style="font-size:11px"><?= __('JPG / PNG / WebP / PDF, max 5 MB') ?></span></div>
  <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2" placeholder="<?= e(__('Optional notes')) ?>"></textarea></div>
</div>
<p class="help"><?= __('Expenses are recorded as') ?><strong><?= __('pending') ?></strong> and posted to the ledger after Owner/Admin approval — Dr Expense, Cr Cash/Bank/Payable. Cash and bank balances update only when approved.</p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save (Pending Approval)') ?></button><a class="btn btn-outline" href="<?= e(url('expenses')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const t = document.getElementById('payType'), wrap = document.getElementById('accountWrap'), acc = document.getElementById('accountId');
t.addEventListener('change', () => { wrap.style.display = t.value === 'payable' ? 'none' : ''; });
wrap.style.display = '';
</script>
