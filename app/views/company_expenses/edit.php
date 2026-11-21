<?php $title = __('Edit Company Expense') . ' — ' . e((string)($row['expense_no'] ?? '')); ?>
<div class="alert alert-info"><?= __('You can only edit expenses that are still pending approval. Posted or rejected expenses stay locked for accounting safety.') ?></div>
<form method="post" enctype="multipart/form-data" class="panel simple-form print-area" id="ceForm"><?= csrf_field() ?>
  <h2><?= __('Edit') . ' ' . e($row['expense_no']) ?></h2>
  <div class="form-grid">
    <div><label class="f"><?= __('Expense Date *') ?></label><input class="f" type="date" name="expense_date" required value="<?= e($row['expense_date']) ?>"></div>
    <div><label class="f"><?= __('Expense Category *') ?></label><select class="f" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="span-2"><label class="f"><?= __('Expense Title / Item Name *') ?></label><input class="f" name="item_name" required value="<?= e($row['item_name']) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description') ?></label><input class="f" name="description" value="<?= e($row['description'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Quantity') ?></label><input class="f" type="number" step="0.001" min="0" name="quantity" id="qty" value="<?= e($row['quantity'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Unit') ?></label><input class="f" type="text" name="unit" value="<?= e($row['unit'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Rate (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="rate" id="rate" value="<?= e($row['rate'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Total Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" id="amount" required value="<?= e($row['amount']) ?>"></div>
    <div><label class="f"><?= __('Supplier / Shop Name') ?></label><input class="f" name="supplier" value="<?= e($row['supplier'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Payment Method *') ?></label><select class="f" name="payment_method" id="paymentMethod" required><option value="cash" <?= $row['payment_type'] === 'cash' ? 'selected' : '' ?>><?= __('Cash') ?></option><option value="bank" <?= $row['payment_type'] === 'bank' ? 'selected' : '' ?>><?= __('Bank') ?></option></select></div>
    <div id="cashAccountWrapper" style="<?= $row['payment_type'] === 'bank' ? 'display:none' : '' ?>"><label class="f"><?= __('Cash Account *') ?></label><select class="f" name="account_id_cash"><?php foreach ($accounts as $a): ?><?php if ($a['type'] === 'cash'): ?><option value="<?= (int)$a['id'] ?>" <?= (int)($row['account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
    <div id="bankAccountWrapper" style="<?= $row['payment_type'] === 'bank' ? '' : 'display:none' ?>"><label class="f"><?= __('Bank Account *') ?></label><select class="f" name="account_id_bank"><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><?php if ($a['type'] === 'bank'): ?><option value="<?= (int)$a['id'] ?>" <?= (int)($row['account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Invoice / Receipt Photo or Document') ?></label><input class="f" type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf"><?php if (!empty($row['attachment'])): ?><span class="muted" style="font-size:11px"><a href="<?= e(url('admin/attachment', ['path' => $row['attachment']])) ?>" target="_blank" rel="noopener"><?= __('View current attachment') ?></a></span><?php endif; ?></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2"><?= e($row['notes'] ?? '') ?></textarea></div>
  </div>
  <div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Save Changes') ?></button><a class="btn btn-outline" href="<?= e(url('company-expenses/' . (int)$row['id'])) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
(function(){
  var qty = document.getElementById('qty'), rate = document.getElementById('rate'), amt = document.getElementById('amount');
  function recalc(){ var q = parseFloat(qty.value)||0, r = parseFloat(rate.value)||0; if (q > 0 && r > 0) amt.value = (q*r).toFixed(2); }
  qty.addEventListener('input', recalc); rate.addEventListener('input', recalc);
  var pm = document.getElementById('paymentMethod'), bw = document.getElementById('bankAccountWrapper'), cw = document.getElementById('cashAccountWrapper');
  function toggleBank(){ bw.style.display = pm.value === 'bank' ? '' : 'none'; cw.style.display = pm.value === 'bank' ? 'none' : ''; }
  pm.addEventListener('change', toggleBank); toggleBank();
})();
</script>
