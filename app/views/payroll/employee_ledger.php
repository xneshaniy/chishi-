<?php $title = __('Salary Ledger —') . e($emp['name']); ?>
<div class="print-area">
<div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Salary Ledger') ?> · <?= e($emp['name']) ?> (<?= e($emp['code']) ?>) · <?= date_display(today()) ?></span><hr></div>
<div class="btn-group no-print" style="justify-content:space-between;margin-bottom:10px">
  <div><strong><?= e($emp['name']) ?></strong><div class="muted" style="font-size:12px"><?= e($emp['designation'] ?? '—') ?> · <?= e($emp['department'] ?? '—') ?> · <?= __('Payment:') ?><?= strtoupper(e($emp['payment_method'] ?? 'cash')) ?></div></div>
  <button class="btn btn-outline" type="button" onclick="window.print()">🖨 <?= __('Print Ledger') ?></button>
</div>
<div class="cards">
  <div class="card amber"><div class="lbl"><?= __('Earned (all periods)') ?></div><div class="val"><?= money($earned) ?></div><div class="sub"><?= __('This month:') ?><?= money($monthEarned) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Paid (all periods)') ?></div><div class="val"><?= money($paid) ?></div><div class="sub"><?= __('This month:') ?><?= money($monthPaid) ?></div></div>
  <div class="card <?= $remaining > 0 ? 'red' : 'green' ?>"><div class="lbl"><?= __('Remaining Payable') ?></div><div class="val"><?= money($remaining) ?></div><div class="sub"><?= __('full or partial payments') ?></div></div>
  <div class="card <?= $advanceOutstanding > 0 ? 'amber' : 'accent' ?>"><div class="lbl"><?= __('Advance Outstanding') ?></div><div class="val"><?= money($advanceOutstanding) ?></div><div class="sub"><?= __('Loans:') ?><?= money($loanOutstanding) ?></div></div>
</div>

<div class="panel print-area"><h2><?= __('Payslips') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Period') ?></th><th class="num"><?= __('Net Pay') ?></th><th class="num"><?= __('Paid') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
  <tbody><?php $totNet = 0; $totPaid = 0; $totRem = 0; foreach ($payslips as $ps): $rem = round(max(0, (float)$ps['net_pay'] - (float)$ps['amount_paid']), 2); if ($ps['status'] !== 'reversed') { $totNet += (float)$ps['net_pay']; $totPaid += (float)$ps['amount_paid']; $totRem += $rem; } ?><tr><td><?= date_display($ps['period_start']) ?> → <?= date_display($ps['period_end']) ?></td><td class="num"><?= money($ps['net_pay'], false) ?></td><td class="num"><?= money($ps['amount_paid'], false) ?></td><td class="num"><strong><?= money($rem, false) ?></strong></td><td><span class="badge <?= ['draft'=>'amber','pending_approval'=>'blue','partial'=>'blue','paid'=>'green','reversed'=>'red'][$ps['status']] ?? 'gray' ?>"><?= e(strtoupper(str_replace('_',' ',$ps['status']))) ?></span></td><td><a class="btn btn-outline btn-sm no-print" href="<?= e(url('payroll/payslip/' . (int)$ps['id'])) ?>"><?= __('Payslip') ?></a></td></tr><?php endforeach; ?><?php if (!$payslips): ?><tr><td colspan="6" class="muted"><?= __('No payslips yet — first payroll run creates them.') ?></td></tr><?php endif; ?></tbody>
  <tfoot><tr><th><?= __('TOTALS') ?></th><th class="num"><?= money($totNet, false) ?></th><th class="num"><?= money($totPaid, false) ?></th><th class="num"><?= money($totRem, false) ?></th><th colspan="2"></th></tr></tfoot></table></div>
</div>

<div class="panel print-area"><h2><?= __('Payment History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Period') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Account') ?></th><th><?= __('Status') ?></th><th><?= __('Notes') ?></th></tr></thead>
  <tbody><?php $totPay = 0; foreach ($payments as $py): if ($py['status'] === 'paid') $totPay += (float)$py['amount']; ?><tr><td><?= date_display($py['date']) ?></td><td><?= date_display($py['period_start']) ?> → <?= date_display($py['period_end']) ?></td><td class="num"><?= money($py['amount'], false) ?></td><td><?= e($py['account'] ?? '—') ?></td><td><span class="badge <?= ['pending'=>'amber','paid'=>'green','rejected'=>'red','reversed'=>'red'][$py['status']] ?? 'gray' ?>"><?= e(strtoupper($py['status'])) ?></span></td><td><?= e($py['notes'] ?? '') ?></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="6" class="muted"><?= __('No payment history yet — first payslip payment creates it.') ?></td></tr><?php endif; ?></tbody>
  <?php if ($payments): ?><tfoot><tr><th colspan="2"><?= __('TOTALS') ?></th><th class="num"><?= money($totPay, false) ?></th><th colspan="3"></th></tr></tfoot><?php endif; ?></table></div>
  <p class="help print-only"><?= __('Payslip payments approved by Owner/Admin are recorded here. Rejected requests never move money.') ?></p>
</div>
</div>
