<?php $title = __('Loan') . e($loan['loan_no']); ?>
<div class="cards">
  <div class="card blue"><div class="lbl"><?= __('Loan Amount') ?></div><div class="val"><?= money($loan['amount']) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Recovered') ?></div><div class="val"><?= money($loan['recovered']) ?></div><div class="sub"><?= (int)$loan['paid_installments'] ?><?= __('of') ?><?= (int)$loan['total_installments'] ?><?= __('installments') ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Outstanding') ?></div><div class="val"><?= money($loan['outstanding']) ?></div></div>
  <div class="card <?= ['pending'=>'gold','active'=>'blue','completed'=>'accent','rejected'=>'red'][$loan['status']] ?>"><div class="lbl"><?= __('Status') ?></div><div class="val" style="font-size:17px"><?= e(strtoupper($loan['status'])) ?></div></div>
</div>
<div class="panel">
  <h2><?= e($loan['employee']) ?> <span class="muted"><?= e($loan['code']) ?> · <?= e($loan['designation'] ?? '') ?></span></h2>
  <p><strong><?= __('Date:') ?></strong> <?= date_display($loan['date']) ?> · <strong><?= __('Reason:') ?></strong> <?= e($loan['reason']) ?> · <strong><?= __('Plan:') ?></strong> <?= e(strtoupper($loan['installment_type'])) ?><?= __('— Rs') ?><?= number_format($loan['installment_amount'], 2) ?> × <?= (int)$loan['total_installments'] ?></p>
  <h3 style="font-size:13px;margin:12px 0 6px"><?= __('Installment Schedule') ?></h3>
  <div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th><?= __('Due Date') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($installments as $i): ?><tr><td><?= (int)$i['installment_no'] ?></td><td><?= date_display($i['due_date']) ?></td><td class="num"><?= money($i['amount']) ?></td>
  <td><span class="badge <?= ['due'=>'amber','deducted'=>'blue','paid'=>'green'][$i['status']] ?>"><?= e(strtoupper($i['status'])) ?></span></td></tr><?php endforeach; ?>
  <?php if (!$installments): ?><tr><td colspan="4" class="muted"><?= __('No installments generated yet (created when Admin approves disbursement).') ?></td></tr><?php endif; ?></tbody></table></div>
  <?php if ($loan['status'] === 'active'): ?>
  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Record Manual Repayment') ?></h3>
  <form method="post" action="<?= e(url('loans/' . (int)$loan['id'] . '/repay')) ?>" class="form-grid"><?= csrf_field() ?>
    <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" max="<?= e($loan['outstanding']) ?>" name="amount" required></div>
    <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
    <div><label class="f"><?= __('Received Into *') ?></label><select class="f" name="account_id" required><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
    <div style="display:flex;align-items:flex-end"><button class="btn btn-primary" type="submit"><?= __('Record Repayment') ?></button></div>
  </form>
  <p class="help"><?= __('Payroll-deducted installments show as DEDUCTed; manual repayments as PAID. Outstanding = amount − recovered.') ?></p>
  <?php endif; ?>
</div>
