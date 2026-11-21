<?php $title = __('Management Console'); ?>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Today\'s Sales') ?></div><div class="val"><?= money($salesToday) ?></div><div class="sub"><?= date_display($today) ?><?= __('· This month') ?><?= money($salesMonth) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Collections Today') ?></div><div class="val"><?= money($collectionsToday) ?></div></div>
  <div class="card <?= $pendingApprovals > 0 ? 'gold' : 'accent' ?>"><div class="lbl"><?= __('Awaiting Admin Approval') ?></div><div class="val"><?= (int)$pendingApprovals ?></div><div class="sub"><?= __('owner/admin decides in the Admin Console') ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Unpaid Payroll') ?></div><div class="val"><?= money($unpaidPayroll['t'] ?? 0) ?></div><div class="sub"><?= (int)($unpaidPayroll['n'] ?? 0) ?><?= __('payslip(s) in draft/approval') ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Customer Receivable') ?></div><div class="val"><?= money($outstandingCustomers) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Low Stock Items') ?></div><div class="val"><?= (int)$lowStock ?></div></div>
</div>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Salesman Performance — This Month') ?></h2>
    <?php if (!$salesmen): ?><p class="muted"><?= __('No salesmen on file.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Salesman') ?></th><th class="num"><?= __('Invoices') ?></th><th class="num"><?= __('Sales') ?></th></tr></thead><tbody><?php foreach ($salesmen as $s): ?><tr><td><?= e($s['name']) ?></td><td class="num"><?= (int)$s['invoices'] ?></td><td class="num"><?= money($s['sales']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Quick Links') ?></h2>
    <div class="btn-group">
      <a class="btn btn-outline" href="<?= e(url('sales/invoices')) ?>"><?= __('Sales Invoices') ?></a>
      <a class="btn btn-outline" href="<?= e(url('cashbank/receipts')) ?>"><?= __('Receipts') ?></a>
      <a class="btn btn-outline" href="<?= e(url('customers')) ?>"><?= __('Customers') ?></a>
      <a class="btn btn-outline" href="<?= e(url('salesmen')) ?>"><?= __('Salesmen') ?></a>
      <a class="btn btn-outline" href="<?= e(url('accounting/ledger')) ?>"><?= __('General Ledger') ?></a>
      <a class="btn btn-outline" href="<?= e(url('accounting/pnl')) ?>"><?= __('Profit & Loss') ?></a>
      <a class="btn btn-outline" href="<?= e(url('accounting/trial-balance')) ?>"><?= __('Trial Balance') ?></a>
      <a class="btn btn-outline" href="<?= e(url('reports')) ?>"><?= __('Reports Hub') ?></a>
      <a class="btn btn-outline" href="<?= e(url('employees')) ?>"><?= __('Employees') ?></a>
      <a class="btn btn-outline" href="<?= e(url('payroll')) ?>"><?= __('Payroll') ?></a>
    </div>
    <p class="help"><?= __('Payments, transfers, expenses, salary and loans post only after Owner/Admin approval (Admin Console → Approvals). Ask Admin when something is pending.') ?></p>
  </div>
</div>
