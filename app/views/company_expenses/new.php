<?php $title = __('New Company Expense'); ?>
<div class="alert alert-info"><?= __('All outgoing payments go through the existing Payment Approval — no cash/bank moves until Owner/Admin approves. Pick from preset company expense categories or add a custom one in Expense Categories.') ?></div>
<form method="post" enctype="multipart/form-data" class="panel simple-form print-area" id="ceForm"><?= csrf_field() ?><?= idempotency_input() ?>
  <h2><?= __('Record Company / Factory Expense') ?></h2>
  <div class="form-grid">
    <div><label class="f"><?= __('Expense Date *') ?></label><input class="f" type="date" name="expense_date" required value="<?= e(today()) ?>"></div>
    <div><label class="f"><?= __('Expense Category *') ?></label><select class="f" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="span-2"><label class="f"><?= __('Expense Title / Item Name *') ?></label><input class="f" name="item_name" required placeholder="<?= e(__('e.g. Plastic Bags')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description') ?></label><input class="f" name="description" placeholder="<?= e(__('e.g. Purchased 5,000 plastic bags — Packaging supplier')) ?>"></div>
    <div><label class="f"><?= __('Quantity') ?></label><input class="f" type="number" step="0.001" min="0" name="quantity" id="qty" placeholder="<?= e(__('e.g. 5000')) ?>"></div>
    <div><label class="f"><?= __('Unit') ?></label><input class="f" type="text" name="unit" id="unit" placeholder="<?= e(__('e.g. pieces / bags / rolls / units')) ?>"></div>
    <div><label class="f"><?= __('Rate (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="rate" id="rate" placeholder="<?= e(__('e.g. 2')) ?>"></div>
    <div><label class="f"><?= __('Total Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" id="amount" required placeholder="<?= e(__('auto-calculated')) ?>"></div>
    <div class="span-2"><span class="muted" id="calcHint" style="font-size:11px"></span></div>
    <div><label class="f"><?= __('Supplier / Shop Name') ?></label><input class="f" name="supplier" placeholder="<?= e(__('e.g. Kamal Plastic, City Market')) ?>"></div>
    <div><label class="f"><?= __('Payment Method *') ?></label><select class="f" name="payment_method" id="paymentMethod" required><option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option></select></div>
    <div id="cashAccountWrapper"><label class="f"><?= __('Cash Account *') ?></label><select class="f" name="account_id_cash"><?php foreach ($accounts as $a): ?><?php if ($a['type'] === 'cash'): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
    <div id="bankAccountWrapper" style="display:none"><label class="f"><?= __('Bank Account *') ?></label><select class="f" name="account_id_bank"><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><?php if ($a['type'] === 'bank'): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Invoice / Receipt Photo or Document') ?></label><input class="f" type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf"><span class="muted" style="font-size:11px"><?= __('JPG / PNG / WebP / PDF, max 5 MB') ?></span></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2" placeholder="<?= e(__('Optional remarks')) ?>"></textarea></div>
  </div>
  <p class="help"><?= __('Total auto-calculates from quantity × rate when quantity and rate are filled — otherwise enter amount manually. Paid only after Owner/Admin approves in the Admin Console (/admin/approvals) — every expense posts exactly once.') ?></p>
  <div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save Company Expense (pending approval)') ?></button><a class="btn btn-outline" href="<?= e(url('company-expenses')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
(function(){
  var qty = document.getElementById('qty'), rate = document.getElementById('rate'), amt = document.getElementById('amount'), hint = document.getElementById('calcHint');
  function recalc(){
    var q = parseFloat(qty.value)||0, r = parseFloat(rate.value)||0;
    if (q > 0 && r > 0) { amt.value = (q * r).toFixed(2); hint.textContent = '(' + q.toLocaleString() + ' × Rs ' + r.toLocaleString() + ' = Rs ' + (q*r).toFixed(2) + ')'; }
    else { hint.textContent = ''; }
  }
  qty.addEventListener('input', recalc);
  rate.addEventListener('input', recalc);
  var pm = document.getElementById('paymentMethod'), bw = document.getElementById('bankAccountWrapper'), cw = document.getElementById('cashAccountWrapper');
  function toggleBank(){ bw.style.display = pm.value === 'bank' ? '' : 'none'; cw.style.display = pm.value === 'bank' ? 'none' : ''; }
  pm.addEventListener('change', toggleBank); toggleBank();
})();
</script>
