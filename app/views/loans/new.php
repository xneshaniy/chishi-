<?php $title = __('New Employee Loan'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Employee Loan') ?><span class="muted" style="font-size:12px"><?= __('requires Owner/Admin approval — disburses after approval') ?></span></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Employee *') ?></label><select class="f" name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['code'] . ' — ' . $e['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Loan Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" id="loanAmount" required></div>
  <div><label class="f"><?= __('Installment Type') ?></label><select class="f" name="installment_type"><option value="monthly"><?= __('Monthly') ?></option><option value="weekly"><?= __('Weekly') ?></option></select></div>
  <div><label class="f"><?= __('Installment Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="installment_amount" id="instAmount" required></div>
  <div><label class="f"><?= __('Number of Installments') ?></label><input class="f" id="numInst" readonly value="—"></div>
  <div><label class="f"><?= __('Pay From *') ?></label><select class="f" name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
</div>
<label class="f mt"><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. medical emergency, house deposit')) ?>">
<p class="help">Ledger effect after approval: Dr Employee Loans (asset) → Cr Cash/Bank. Installments auto-deduct from salary payroll once approved. Separate from salary advances.</p>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Request Loan (goes to Admin for approval)') ?></button><a class="btn btn-outline" href="<?= e(url('loans')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const amt = document.getElementById('loanAmount'), inst = document.getElementById('instAmount'), n = document.getElementById('numInst');
function calc(){ const a = parseFloat(amt.value||0), i = parseFloat(inst.value||0); n.value = (i > 0 && a > 0) ? Math.ceil(a / i) : '—'; }
amt.addEventListener('input', calc); inst.addEventListener('input', calc);
</script>
