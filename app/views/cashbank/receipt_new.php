<?php $title = __('New Customer Receipt'); ?>
<form method="post" class="panel"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Customer Payment Received') ?></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Customer') ?></label><select class="f" name="customer_id" id="custSel"><option value=""><?= __('— not registered / walk-in —') ?></option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" data-balance="<?= e(number_format($balances[(int)$c['id']] ?? 0, 2, '.', '')) ?>"><?= e($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?></select><span class="muted" style="font-size:11px"><?= __('Optional — for walk-in cash, enter who paid below.') ?></span></div>
  <div style="grid-column:span 2" id="contactNameWrap"><label class="f"><?= __('Paid By (person / shop name)') ?></label><input class="f" name="contact_name" id="contactName" placeholder="<?= e(__('e.g. Ahmed Traders / Counter sale')) ?>"></div>
  <div><label class="f"><?= __('Outstanding (excl. openings)') ?></label><input class="f" id="custBalance" readonly value="0.00"></div>
  <div><label class="f"><?= __('Deposit Into *') ?></label><select class="f" name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Amount Received (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label class="f"><?= __('Reference (cheque / slip no)') ?></label><input class="f" name="reference"></div>
</div>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes">
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Receipt') ?></button><a class="btn btn-outline" href="<?= e(url('cashbank/receipts')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const sel = document.getElementById('custSel'), out = document.getElementById('custBalance');
function upd(){ out.value = (parseFloat(sel.selectedOptions[0]?.dataset.balance || '0')).toFixed(2); }
sel.addEventListener('change', upd); upd();
</script>
