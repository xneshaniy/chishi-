<?php $title = __('Payroll Run'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="payroll">
  <label class="muted"><?= __('Period from') ?></label><input type="date" name="from" value="<?= e($from) ?>"><label class="muted"><?= __('to') ?></label><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-outline" type="submit"><?= __('Show') ?></button>
</form>
<div class="two-col">
  <form method="post" action="<?= e(url('payroll/generate')) ?>" class="panel"><?= csrf_field() ?>
    <input type="hidden" name="period_start" value="<?= e($from) ?>"><input type="hidden" name="period_end" value="<?= e($to) ?>">
    <h2><?= __('Generate Payroll') ?></h2>
    <p class="help"><?= __("Weekly and Monthly staff are generated independently — pick the frequency to generate for that staff only. Weekly pays rate × present days / 6 working days (Half Day 0.5). Monthly pays full rate. Advances and due loan installments deduct automatically. Nothing posts until Owner/Admin approves each payment.") ?></p>
    <div class="toolbar" style="margin-bottom:10px">
      <label class="muted"><?= __('Salary Frequency') ?></label>
      <select class="f" name="frequency"><option value="all"><?= __('All active employees (both frequencies)') ?></option><option value="monthly"><?= __('Monthly employees only') ?></option><option value="weekly"><?= __('Weekly employees only') ?></option></select>
    </div>
    <button class="btn btn-dark" type="submit" <?= $rows ? 'disabled' : '' ?>><?= __('Generate Draft Payroll') ?></button>
    <?php if ($rows): ?><p class="help"><?= __('This period already contains payslips — generating again will skip employees who already have one. Reverse a payslip to re-generate it.') ?></p><?php endif; ?></form>
  <div class="panel"><h2><?= __('Period:') ?><?= money($sum) ?><?= __('net pay') ?></h2>
    <p class="muted"><?= count($rows) ?><?= __('payslip(s) ·') ?><?= e(date_display($from)) ?> → <?= e(date_display($to)) ?></p>
    <p class="muted"><?= __('Paid:') ?><strong><?= money($paidSum) ?></strong><?= __('Remaining payable:') ?><strong><?= money($sum - $paidSum) ?></strong></p>
    <p class="help">Pay buttons submit for Owner/Admin approval — money leaves only after approval. The amount field defaults to the full remaining balance; enter a SMALLER amount for a partial payment (repeat until zero). Rejected payments never affect the ledger; reverse a paid payslip to release deductions.</p>
    <?php if ($rows): ?><p class="help"><strong><?= __('Print payslips:') ?></strong><?= __('each row has a') ?><em><?= __('Print') ?></em><?= __('link (Ctrl+P → Save as PDF).') ?></p><?php endif; ?>
  </div>
</div>
<?php if ($rows): ?>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Employee') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Basic') ?></th><th class="num"><?= __('OT') ?></th><th class="num"><?= __('Adv. Ded.') ?></th><th class="num"><?= __('Loan Ded.') ?></th><th class="num"><?= __('Net Pay') ?></th><th class="num"><?= __('Paid') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($rows as $p): $rem = round(max(0, (float)$p['net_pay'] - (float)$p['amount_paid']), 2); $awaiting = (float)($pendingPay[(int)$p['id']] ?? 0); ?><tr><td class="mono"><?= e($p['code']) ?></td><td><?= e($p['employee']) ?></td><td><?= e($p['salary_type']) ?></td><td class="num"><?= money($p['basic'], false) ?></td><td class="num"><?= $p['overtime'] > 0 ? money($p['overtime'], false) : '—' ?></td><td class="num"><?= $p['advances_deducted'] > 0 ? money($p['advances_deducted'], false) : '—' ?></td><td class="num"><?= $p['loan_deduction'] > 0 ? money($p['loan_deduction'], false) : '—' ?></td><td class="num"><strong><?= money($p['net_pay']) ?></strong></td><td class="num"><?= money($p['amount_paid'], false) ?></td><td class="num"><strong><?= money($rem, false) ?></strong></td>
<td><span class="badge <?= ['draft'=>'amber','pending_approval'=>'blue','partial'=>'blue','paid'=>'green','reversed'=>'red','rejected'=>'gray'][$p['status']] ?? 'gray' ?>"><?= e(strtoupper(str_replace('_',' ',$p['status']))) ?></span></td>
<td class="btn-group">
<?php if ($awaiting > 0): ?>
  <span class="badge blue"><?= __('Awaiting approval:') ?><?= money($awaiting, false) ?></span>
<?php elseif (in_array($p['status'], ['draft','partial']) && $rem > 0): ?>
  <form method="post" action="<?= e(url('payroll/' . (int)$p['id'] . '/pay')) ?>" style="display:flex;gap:4px;flex-wrap:wrap"><?= csrf_field() ?>
    <input class="f" style="width:100px;padding:4px 6px;font-size:12px" type="number" step="0.01" min="0.01" max="<?= e((string)$rem) ?>" name="amount" value="<?= e((string)$rem) ?>" title="<?= __('Leave blank or keep default to pay in full; enter less for a partial payment.') ?>" required>
    <select name="account_id" class="f" style="width:auto;padding:4px 6px;font-size:12px" required><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary btn-sm" type="submit"><?= $rem < (float)$p['net_pay'] ? __('Pay Balance') : __('Submit for Approval') ?></button></form>
<?php elseif ($p['status'] === 'paid'): ?>
  <form method="post" action="<?= e(url('payroll/' . (int)$p['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Reverse this payslip? The journal(s) will be reversed and deductions released to advances/loans.')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= __('Reverse') ?></button></form>
<?php endif; ?>
<a class="btn btn-outline btn-sm" href="<?= e(url('payroll/payslip/' . (int)$p['id'])) ?>"><?= __('Print') ?></a>
</td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
