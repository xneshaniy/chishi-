<?php $title = __('Payslip —') . e($p['employee']); ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none;max-width:820px;margin:0 auto">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px double var(--charcoal);padding-bottom:10px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="CTC" style="height:90px">
    <div style="text-align:right"><strong style="font-size:18px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= e(setting('address')) ?></span><br><?php if (setting('ntn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?></span><?php endif; ?></div>
  </div>
  <h2 style="justify-content:center"><?= __('SALARY PAYSLIP') ?></h2>
  <div class="form-grid" style="margin-bottom:10px">
    <div><strong><?= __('Employee:') ?></strong> <?= e($p['employee']) ?> (<?= e($p['code']) ?>)</div>
    <div><strong><?= __('Designation:') ?></strong> <?= e($p['designation'] ?? '—') ?></div>
    <div><strong><?= __('Department:') ?></strong> <?= e($p['department'] ?? '—') ?></div>
    <div><strong><?= __('Salary Type:') ?></strong> <?= e(strtoupper($p['salary_type'])) ?></div>
    <div><strong><?= __('Period:') ?></strong> <?= date_display($p['period_start']) ?> → <?= date_display($p['period_end']) ?></div>
    <div><strong><?= __('Payslip No:') ?></strong> #<?= (int)$p['id'] ?></div>
  </div>
  <div class="two-col">
    <div><h3 class="help" style="font-size:12px;text-transform:uppercase"><?= __('Earnings') ?></h3>
      <table class="tbl"><tbody>
        <tr><td><?= __('Basic Salary / Wages') ?></td><td class="num"><?= number_format($p['basic'], 2) ?></td></tr>
        <tr><td><?= __('Overtime (') ?><?= qty($p['overtime_hours']) ?><?= __('hrs)') ?></td><td class="num"><?= number_format($p['overtime'], 2) ?></td></tr>
        <tr><td><?= __('Allowances') ?></td><td class="num"><?= number_format($p['allowances'], 2) ?></td></tr>
        <tr><td><?= __('Bonus') ?></td><td class="num"><?= number_format($p['bonus'], 2) ?></td></tr>
        <tr><th><?= __('Gross Earnings') ?></th><th class="num"><?= number_format($p['basic'] + $p['overtime'] + $p['allowances'] + $p['bonus'], 2) ?></th></tr>
      </tbody></table>
    </div>
    <div><h3 class="help" style="font-size:12px;text-transform:uppercase"><?= __('Deductions') ?></h3>
      <table class="tbl"><tbody>
        <?php if ($p['advances_deducted'] > 0): ?><tr><td><?= __('Salary Advances') ?></td><td class="num"><?= number_format($p['advances_deducted'], 2) ?></td></tr><?php endif; ?>
        <?php if ($p['loan_deduction'] > 0): ?><tr><td><?= __('Loan Installments') ?></td><td class="num"><?= number_format($p['loan_deduction'], 2) ?></td></tr><?php endif; ?>
        <?php if ($p['other_deductions'] > 0): ?><tr><td><?= __('Other Deductions') ?></td><td class="num"><?= number_format($p['other_deductions'], 2) ?></td></tr><?php endif; ?>
        <tr><th><?= __('Total Deductions') ?></th><th class="num"><?= number_format($p['advances_deducted'] + $p['loan_deduction'] + $p['other_deductions'], 2) ?></th></tr>
      </tbody></table>
    </div>
  </div>
  <div style="text-align:center;border-top:3px double var(--charcoal);margin-top:14px;padding-top:12px">
    <div class="help"><?= __('NET PAY') ?></div>
    <div style="font-size:30px;font-weight:800;color:var(--green)"><?= __('Rs') ?><?= number_format($p['net_pay'], 2) ?></div>
    <div class="help mt"><?= __('Amount in words:') ?><?= e(amount_in_words((float)$p['net_pay'])) ?></div>
  </div>
  <?php if ($deductions): ?><div class="table-wrap mt"><h3 class="help" style="font-size:12px;text-transform:uppercase"><?= __('Deduction References') ?></h3>
    <table class="tbl"><thead><tr><th><?= __('Source') ?></th><th><?= __('Reference') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
    <tbody><?php foreach ($deductions as $d): ?><tr><td><?= e(strtoupper(str_replace('_', ' ', $d['source_type']))) ?></td><td><?= e($d['reference'] ?? '') ?></td><td class="num"><?= number_format($d['amount'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <?php $paid = (float)($p['amount_paid'] ?? 0); $rem = round(max(0, (float)$p['net_pay'] - $paid), 2); ?>
  <div class="table-wrap mt"><h3 class="help" style="font-size:12px;text-transform:uppercase"><?= __('Payment Status') ?></h3>
    <table class="tbl"><tbody>
      <tr><td><?= __('Amount Paid So Far') ?></td><td class="num"><?= number_format($paid, 2) ?></td></tr>
      <tr><td><?= __('Remaining Payable') ?></td><td class="num"><strong><?= number_format($rem, 2) ?></strong></td></tr>
      <?php if ($payments): foreach ($payments as $py): ?><tr><td><?= date_display($py['date']) ?><?= $py['status'] === 'pending' ? ' · ' . __('awaiting approval') : '' ?></td><td class="num"><?= number_format($py['amount'], 2) ?> <span class="muted">· <?= e($py['account'] ?? '') ?></span></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
  <div class="two-col mt" style="margin-top:40px">
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Employee Signature') ?></span></div>
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Authorized Signatory') ?></span></div>
  </div>
  <p class="help" style="text-align:center;margin-top:12px"><?= __('Computer-generated payslip —') ?><?= e(setting('company_name', cfg('app.company'))) ?> · <?= dt_display(now_local()) ?></p>
</div>
</div>
<div class="btn-group no-print mt"><button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button><a class="btn btn-outline" href="<?= e(url('payroll')) ?>"><?= __('Back') ?></a></div>
