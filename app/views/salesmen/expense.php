<?php $title = __('Expenses — ') . e($sm['name']); $tab = 'expense'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Vehicle / Petrol') ?></div><div class="sc-val"><?= money($allTime['vehicle'] ?? 0) ?></div><div class="sc-sub"><?= __('this month') ?>: <?= money($monthTime['vehicle'] ?? 0) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Food') ?></div><div class="sc-val"><?= money($allTime['food'] ?? 0) ?></div><div class="sc-sub"><?= __('this month') ?>: <?= money($monthTime['food'] ?? 0) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Other Expenses') ?></div><div class="sc-val"><?= money($allTime['other'] ?? 0) ?></div><div class="sc-sub"><?= __('this month') ?>: <?= money($monthTime['other'] ?? 0) ?></div></div>
  <div class="stat-chip" style="border-color:#bfe3cd"><div class="sc-lbl"><?= __('Total Expenses') ?></div><div class="sc-val" style="color:#0a4d29"><?= money($allTime['total'] ?? 0) ?></div><div class="sc-sub"><?= __('this month') ?>: <?= money($monthTime['total'] ?? 0) ?></div></div>
</div>

<form method="post" enctype="multipart/form-data" class="simple-form" style="max-width:980px"><?= csrf_field() ?><h2><?= __('Add Expense') ?></h2>
<p class="help"><?= __('Company pays: the selected company cash/bank account is debited through the normal approval system. Salesman pays from collection: no company cash moves — the approved amount is deducted from his collections in the Hisab settlement instead.') ?></p>
<div class="big-fields">
  <div class="big-field"><label><?= __('Date') ?></label><input class="f" name="date" type="date" value="<?= e(today()) ?>" required></div>
  <div class="big-field"><label><?= __('Category *') ?></label><select class="f" name="expense_type" required>
    <option value="vehicle"><?= __('Vehicle / Petrol') ?></option>
    <option value="food"><?= __('Food') ?></option>
    <option value="other"><?= __('Other Expenses') ?></option>
  </select></div>
  <div class="big-field"><label><?= __('Amount (Rs) *') ?></label><input class="f" name="amount" type="number" step="0.01" min="0.01" required placeholder="500"></div>
  <div class="big-field"><label><?= __('Paid By') ?></label><select class="f" name="paid_from" id="paidFrom" required>
    <option value="company"><?= __('Company pays (cash / bank)') ?></option>
    <option value="collection"><?= __('Salesman pays from collection') ?></option>
  </select></div>
  <div class="big-field" id="accountWrap"><label><?= __('Paid From') ?></label><select class="f" name="account_id" id="accountId" required><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div class="big-field" id="paymentTypeWrap"><label><?= __('Payment') ?></label><select class="f" name="payment_type" id="paymentType"><option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option></select></div>
  <div class="span-4"><label><?= __('Description *') ?></label><input class="f" name="description" required placeholder="<?= e(__('e.g. diesel for Rickshaw LEA-1234')) ?>"></div>
  <div class="span-4"><label><?= __('Receipt Photo (optional)') ?></label><input class="f" type="file" name="receipt" accept="image/*,application/pdf"></div>
  <div class="span-4"><label><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('optional')) ?>"></div>
</div>
<button class="btn btn-primary btn-xl mt" type="submit"><?= __('Save Expense') ?></button>
</form>

<div class="panel" style="margin-top:14px">
  <h2><?= __('Expense History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Category') ?></th><th><?= __('Description') ?></th><th><?= __('Paid By') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($expenses as $e): $catDisplay = match(true) { in_array($e['expense_type'], ['diesel','vehicle','repair'], true) => __('Vehicle / Petrol'), in_array($e['expense_type'], ['food','hotel'], true) => __('Food'), default => __('Other Expenses') }; $fromCollection = (($e['paid_from'] ?? 'company') === 'collection'); ?><tr><td><?= date_display($e['date']) ?></td><td><?= e($catDisplay) ?></td><td><?= e($e['description']) ?></td><td><span class="badge <?= $fromCollection ? 'amber' : 'gray' ?>"><?= $fromCollection ? e(__('Collection')) : e(__('Company')) ?></span></td><td class="num"><?= money($e['amount']) ?></td><td><span class="badge <?= $e['status'] === 'posted' ? 'green' : ($e['status'] === 'pending' ? 'amber' : 'gray') ?>"><?= e(strtoupper($e['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$expenses): ?><tr><td colspan="6" class="muted"><?= __('No expenses yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>

<script>
(function () {
  var paidFrom = document.getElementById('paidFrom'), accountId = document.getElementById('accountId');
  var accountWrap = document.getElementById('accountWrap'), paymentTypeWrap = document.getElementById('paymentTypeWrap');
  function sync() {
    var collection = paidFrom.value === 'collection';
    if (accountId) accountId.disabled = collection;
    if (accountId) accountId.required = !collection;
    if (accountWrap) accountWrap.style.display = collection ? 'none' : '';
    if (paymentTypeWrap) paymentTypeWrap.style.display = collection ? 'none' : '';
  }
  paidFrom.addEventListener('change', sync);
  sync();
})();
</script>
