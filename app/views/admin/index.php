<?php $title = __('Admin Console'); ?>
<div class="cards">
  <div class="card <?= $pending > 0 ? 'red' : 'accent' ?>"><div class="lbl"><?= __('Pending Approvals') ?></div><div class="val"><?= (int)$pending ?></div><div class="sub"><?= __('payments, salary, loans, transfers…') ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Total Pending Payment Amount') ?></div><div class="val"><?= money($pendingAmount) ?></div><div class="sub"><?= __('sum of all pending approval requests') ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Approved Today') ?></div><div class="val"><?= (int)$approvedToday ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Rejected Today') ?></div><div class="val"><?= (int)$rejectedToday ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Today\'s Sales') ?></div><div class="val"><?= money($salesToday) ?></div><div class="sub"><?= date_display($today) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('This Month Sales') ?></div><div class="val"><?= money($salesMonth) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Collections Today') ?></div><div class="val"><?= money($collections) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Supplier Payable') ?></div><div class="val"><?= money($payableDue) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Active Users') ?></div><div class="val"><?= (int)$users ?></div><div class="sub"><?= (int)$failedLogins ?><?= __('failed logins (24h)') ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Low Stock Items') ?></div><div class="val"><?= (int)$lowStock ?></div></div>
  <div class="card <?= $tb['balanced'] ? 'accent' : 'red' ?>"><div class="lbl"><?= __('Trial Balance') ?></div><div class="val" style="font-size:15px"><?= $tb['balanced'] ? '✓ Agrees' : '✗ OUT OF BALANCE' ?></div><div class="sub"><?= __('D') ?><?= number_format($tb['total_debit'], 0) ?><?= __('/ C') ?><?= number_format($tb['total_credit'], 0) ?></div></div>
</div>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Approvals Inbox') ?><a class="btn btn-danger btn-sm" href="<?= e(url('admin/approvals')) ?>"><?= __('Review All (') ?><?= (int)$pending ?>)</a></h2>
    <?php if (!$pendingList): ?><p class="muted"><?= __('Nothing pending. ✔') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Type') ?></th><th><?= __('Ref') ?></th><th><?= __('Amount') ?></th><th><?= __('Requested By') ?></th><th><?= __('Reason') ?></th></tr></thead>
    <tbody><?php foreach ($pendingList as $r): ?><tr><td><span class="badge amber"><?= e(approval_label((string)$r['request_type'])) ?></span></td><td><?= e(approval_reference_label((string)$r['reference_type']) . ' #' . $r['reference_id']) ?></td><td class="num"><?= money($r['amount']) ?></td><td><?= e($r['requester'] ?? '—') ?></td><td><?= e($r['reason'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Quick Actions') ?></h2>
    <div class="btn-group">
      <a class="btn btn-dark" href="<?= e(url('admin/approvals')) ?>"><?= __('✔ Approvals Inbox') ?></a>
      <a class="btn btn-outline" href="<?= e(url('users')) ?>"><?= __('Users & Roles') ?></a>
      <a class="btn btn-outline" href="<?= e(url('admin/team')) ?>"><?= __('Management Team') ?></a>
      <a class="btn btn-dark" href="<?= e(url('admin/notifications')) ?>"><?= __('🔔 All Notifications (Admin)') ?></a>
      <a class="btn btn-dark" href="<?= e(url('admin/archives')) ?>"><?= __('🗄 Archives') ?></a>
      <a class="btn btn-outline" href="<?= e(url('settings/company')) ?>"><?= __('Company Profile') ?></a>
      <a class="btn btn-outline" href="<?= e(url('accounting/trial-balance')) ?>"><?= __('Trial Balance') ?></a>
      <a class="btn btn-outline" href="<?= e(url('accounting/closing')) ?>"><?= __('Fiscal Year Closing') ?></a>
      <a class="btn btn-outline" href="<?= e(url('settings/backup')) ?>"><?= __('Backup & Restore') ?></a>
      <a class="btn btn-outline" href="<?= e(url('audit')) ?>"><?= __('Audit Log') ?></a>
      <a class="btn btn-outline" href="<?= e(url('dashboard')) ?>"><?= __('Go to ERP Dashboard') ?></a>
    </div>
    <p class="help"><?= __('Managers and Accountants cannot open this console — server-side enforced in') ?><code><?= __('index.php') ?></code><?= __('via') ?><code><?= __('Auth::requireArea()') ?></code>.</p>
  </div>
</div>

<div class="panel">
  <h2><?= __('Latest Audit Events') ?></h2>
  <ul class="list-clean"><?php foreach ($latestAudit as $a): ?><li><span><span class="badge gray"><?= e($a['action']) ?></span> <?= e($a['entity']) ?> #<?= e($a['entity_id']) ?> — <?= e(friendly_audit_details((string)$a['details'])) ?></span><span class="muted"><?= dt_display($a['created_at']) ?></span></li><?php endforeach; ?></ul>
</div>
