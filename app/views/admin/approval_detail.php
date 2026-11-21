<?php $title = 'Approval #' . (int)$req['id']; ?>
<?php $baseRoute = $baseRoute ?? 'admin/approvals'; $canDecide = $canDecide ?? true; ?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url($baseRoute)) ?>">&larr; <?= __('Back to Approvals') ?></a>
</div>
<div class="panel">
  <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:12px;margin-bottom:12px">
    <div>
      <div class="help"><?= __('APPROVAL REQUEST') ?></div>
      <div style="font-size:22px;font-weight:800">#<?= (int)$req['id'] ?> · <?= e(approval_label((string)$req['request_type'])) ?></div>
      <div class="muted"><?= e(approval_reference_label((string)$req['reference_type'])) ?> #<?= (int)$req['reference_id'] ?></div>
    </div>
    <div>
      <?php if ($req['status'] === 'pending'): ?>
        <span class="badge amber" style="font-size:14px;padding:6px 14px"><?= __('PENDING') ?></span>
      <?php elseif ($req['status'] === 'approved'): ?>
        <span class="badge green" style="font-size:14px;padding:6px 14px"><?= __('APPROVED') ?></span>
      <?php else: ?>
        <span class="badge red" style="font-size:14px;padding:6px 14px"><?= __('REJECTED') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));margin:0 0 14px">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Amount') ?></div><div class="sc-val" style="font-size:22px"><?= money($req['amount']) ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Requested By') ?></div><div class="sc-val" style="font-size:16px"><?= e($req['requester_name'] ?: $req['requester']) ?></div><div class="sc-sub">@<?= e($req['requester']) ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Requested At') ?></div><div class="sc-val" style="font-size:15px"><?= dt_display($req['requested_at'] ?? $req['created_at']) ?></div></div>
    <?php if (!empty($src['date'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Document Date') ?></div><div class="sc-val" style="font-size:15px"><?= date_display($src['date']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['method'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Payment Method') ?></div><div class="sc-val" style="font-size:16px"><?= e($src['method']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['account'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Account') ?></div><div class="sc-val" style="font-size:15px"><?= e($src['account']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['party'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Related Party') ?></div><div class="sc-val" style="font-size:15px"><?= e($src['party']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['status'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Source Status') ?></div><div class="sc-val" style="font-size:15px"><?= e(strtoupper((string)$src['status'])) ?></div></div><?php endif; ?>
  </div>

  <?php if (in_array((string)($req['reference_type'] ?? ''), ['payroll_payments', 'salesman_salary_payments'], true) && !empty($src['party'])): ?>
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--line)">
    <?php if (!empty($src['party_photo'])): ?>
      <img src="<?= e(photo_data_uri($src['party_photo'])) ?>" alt="<?= e($src['party'] ?? '') ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:1px solid var(--line)">
    <?php else: ?>
      <div class="profile-avatar" style="width:96px;height:96px;border-radius:50%;font-size:36px"><?= e(mb_strtoupper(mb_substr((string)($src['party'] ?? '?'), 0, 1))) ?></div>
    <?php endif; ?>
    <div>
      <h3 style="margin:0"><?= e($src['party']) ?></h3>
      <?php if (!empty($src['description'])): ?><div class="muted" style="font-size:13px;margin-top:2px"><?= e($src['description']) ?></div><?php endif; ?>
      <?php if (!empty($src['account'])): ?><div class="help" style="margin-top:2px"><?= __('Paid from') ?> <?= e($src['account']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($src['registration'])): $reg = $src['registration']; ?>
  <h3 style="margin:4px 0 8px"><?= __('Registration Profile') ?></h3>
  <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
    <div style="text-align:center">
      <?php if (!empty($src['party_photo'])): ?><img src="<?= e(photo_data_uri($src['party_photo'])) ?>" alt="<?= e($src['party'] ?? '') ?>" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:1px solid var(--line)"><?php else: ?><div class="profile-avatar" style="width:110px;height:110px;border-radius:50%;font-size:40px"><?= e(mb_strtoupper(mb_substr((string)($src['party'] ?? '?'), 0, 1))) ?></div><?php endif; ?>
      <div class="muted" style="margin-top:6px"><?= e(ucfirst((string)$src['type'])) ?></div>
    </div>
    <div class="table-wrap" style="flex:1;min-width:280px"><table class="tbl"><tbody>
      <tr><td style="width:38%"><?= __('ID') ?></td><td class="mono"><?= e($reg['code'] ?? '—') ?></td></tr>
      <tr><td><?= __('Full Name') ?></td><td><strong><?= e($reg['name'] ?? '—') ?></strong></td></tr>
      <tr><td><?= __('Contact Number') ?></td><td><?= e($reg['phone'] ?: '—') ?></td></tr>
      <tr><td><?= __('CNIC') ?></td><td><?= e($reg['cnic'] ?: '—') ?></td></tr>
      <tr><td><?= __('Address') ?></td><td><?= e($reg['address'] ?: '—') ?></td></tr>
      <?php if (!empty($reg['department']) || !empty($reg['designation'])): ?><tr><td><?= __('Job Title') ?></td><td><?= e(trim(($reg['designation'] ?? '') . ($reg['department'] ? ' · ' . $reg['department'] : '')) ?: '—') ?></td></tr><?php endif; ?>
      <?php if (!empty($reg['area'])): ?><tr><td><?= __('Assigned Area') ?></td><td><?= e($reg['area']) ?></td></tr><?php endif; ?>
      <?php if (isset($reg['vehicle_type']) && $reg['vehicle_type'] !== ''): ?><tr><td><?= __('Vehicle') ?></td><td><?= e(trim(($reg['vehicle_type'] ?? '') . ' ' . ($reg['vehicle_reg'] ?? ''))) ?></td></tr><?php endif; ?>
      <tr><td><?= __('Salary Payment Frequency') ?></td><td><strong><?= e(ucfirst($reg['salary_frequency'] ?? 'monthly')) ?></strong></td></tr>
      <tr><td><?= __('Salary') ?></td><td><strong><?= money($reg['salary_rate'] ?? ($reg['salary'] ?? 0)) ?></strong> <span class="muted">/ <?= e(__(($reg['salary_frequency'] ?? 'monthly') === 'weekly' ? 'week' : 'month')) ?></span></td></tr>
      <?php if (isset($reg['commission_percent'])): ?><tr><td><?= __('Commission') ?></td><td><?= number_format((float)$reg['commission_percent'], 2) ?>%</td></tr><?php endif; ?>
      <tr><td><?= __('Join Date') ?></td><td><?= e($reg['join_date'] ? date_display($reg['join_date']) : '—') ?></td></tr>
      <tr><td><?= __('Requester') ?></td><td><?= e(($req['requester_name'] ?? '') ?: ($req['requester'] ?? '—')) ?></td></tr>
      <tr><td><?= __('Requested At') ?></td><td><?= dt_display($req['requested_at'] ?? $req['created_at']) ?></td></tr>
    </tbody></table></div>
  </div>
  <?php endif; ?>

  <h3 style="margin:4px 0 8px"><?= __('Supporting Details') ?></h3>
  <div class="table-wrap"><table class="tbl">
    <tbody>
      <tr><td style="width:35%"><?= __('Request Type') ?></td><td><?= e(approval_label((string)$req['request_type'])) ?></td></tr>
      <tr><td><?= __('Description') ?></td><td><?= e($src['description'] ?: ($req['reason'] ?: '—')) ?></td></tr>
      <?php if (!empty($src['attachment'])): ?><tr><td><?= __('Attachment') ?></td><td><a href="<?= e(url('admin/attachment', ['path' => $src['attachment']])) ?>" target="_blank" rel="noopener">📎 <?= e(basename($src['attachment'])) ?></a></td></tr><?php endif; ?>
      <?php if ($req['reason']): ?><tr><td><?= __('Reason / Notes') ?></td><td><?= e($req['reason']) ?></td></tr><?php endif; ?>
      <?php if ($req['approver_id']): ?><tr><td><?= __('Decided By') ?></td><td><?= e($decider['full_name'] ?: $decider['username']) ?> · <?= dt_display($req['decided_at']) ?></td></tr><?php endif; ?>
      <?php if ($req['decision_note']): ?><tr><td><?= __('Decision Note') ?></td><td><?= e($req['decision_note']) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php if ($auditRows): ?>
  <h3 style="margin:14px 0 8px"><?= __('Audit Trail') ?></h3>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('When') ?></th><th><?= __('Action') ?></th><th><?= __('Details') ?></th></tr></thead>
    <tbody><?php foreach ($auditRows as $a): ?><tr><td><?= dt_display($a['created_at']) ?></td><td><?= e($a['action']) ?></td><td><?= e(mb_substr(friendly_audit_details((string)$a['details']), 0, 200)) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>

  <?php if ($req['status'] === 'pending' && !$canDecide): ?>
  <div class="panel" style="margin:14px 0 0;background:var(--yellow-soft, #fff8e6);border:1px solid #e8d48a">
    <h2 style="margin-top:0"><?= __('Awaiting Owner/Admin decision') ?></h2>
    <p class="help"><?= __('This request is pending approval. Only Owner/Admin can approve or reject it — Managers cannot decide approvals. You will be notified when a decision is made.') ?></p>
  </div>
  <?php elseif ($req['status'] === 'pending'): ?>
  <div class="panel" style="margin:14px 0 0;background:var(--yellow-soft, #fff8e6);border:1px solid #e8d48a">
    <h2 style="margin-top:0"><?= __('Decision') ?></h2>
    <p class="help"><?= __('Approving posts the source document exactly once. Rejecting does not touch any balances. The requester is notified either way.') ?></p>
    <form method="post" action="<?= e(url('admin/approvals/decide')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
      <label class="f" style="margin-top:8px"><?= __('Note (required for rejection)') ?></label>
      <textarea class="f" name="note" id="decisionNote" rows="2" placeholder="<?= e(__('Add a note for the requester…')) ?>" style="width:100%"></textarea>
      <?php
        $docInfo = [
          ['label' => __('Document #'), 'value' => approval_reference_label((string)$req['reference_type']) . ' #' . $req['reference_id']],
          ['label' => __('Document Type'), 'value' => approval_label((string)$req['request_type'])],
          ['label' => __('Amount'), 'value' => money($req['amount'])],
          ['label' => __('Requester'), 'value' => ($req['requester_name'] ?? '') ?: ($req['requester'] ?? '—')],
        ];
        $docJson = e(json_encode($docInfo, JSON_UNESCAPED_UNICODE));
      ?>
      <div class="btn-group mt">
        <button class="btn btn-primary btn-xl" type="submit" name="decision" value="approve" data-confirm="<?= e(__('Are you sure you want to approve this document?')) ?>" data-confirm-sub="<?= e(__('This document will be posted to the system. This action cannot be undone.')) ?>" data-confirm-title="<?= e(__('Confirm Approval')) ?>" data-confirm-ok="<?= e(__('Confirm & Approve')) ?>" data-confirm-variant="approve" data-confirm-doc="<?= $docJson ?>"><?= __('Approve & Post') ?></button>
        <button class="btn btn-danger btn-xl" type="submit" name="decision" value="reject" data-confirm="<?= e(__('Reject without posting?')) ?>" data-confirm-title="<?= e(__('Confirm Rejection')) ?>" data-confirm-ok="<?= e(__('Reject')) ?>" data-confirm-variant="danger" data-confirm-doc="<?= $docJson ?>" data-confirm-require-note="#decisionNote"><?= __('Reject') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
