<?php $title = __('Payment Approval'); ?>
<?php
  $src = fn($r) => $r['src'] ?? [];
  $baseRoute = $baseRoute ?? 'admin/approvals'; $canDecide = $canDecide ?? true;
  $partyKindLabel = ['employee' => __('employees'), 'salesman' => __('salesmen'), 'supplier' => __('suppliers'), 'customer' => __('customers')];
?>
<div class="simple-hero" style="padding:16px 20px;margin-bottom:12px">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Payment Approval') ?></span><br>
  <small><?= __('Every outgoing payment from the ERP — approve or reject from one place. Approved documents post exactly once; rejected ones never touch balances.') ?></small></div>
  <?php if (empty($canDecide)): ?><div class="alert alert-info" style="margin:10px 0 0"><?= __('Manager view — you can track every request, status, approval history and rejection reasons here. Only Owner/Admin can approve or reject payments; your own submitted requests appear alongside all others.') ?></div><?php endif; ?>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr));margin-bottom:12px">
  <div class="stat-chip" style="border-color:var(--amber,#e8a13a)"><div class="sc-lbl"><?= __('Pending Approvals') ?></div><div class="sc-val" style="font-size:26px"><?= (int)$pendingCount ?></div><div class="sc-sub"><?= __('requests waiting') ?></div></div>
  <div class="stat-chip" style="border-color:var(--amber,#e8a13a)"><div class="sc-lbl"><?= __('Pending Payment Amount') ?></div><div class="sc-val" style="font-size:26px"><?= money($pendingAmount) ?></div><div class="sc-sub"><?= __('total awaiting approval') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Current View') ?></div><div class="sc-val" style="font-size:16px;text-transform:uppercase"><?= e(strtoupper($status)) ?></div><div class="sc-sub"><?= (int)$p['total'] . ' ' . __('shown') ?></div></div>
</div>

<div class="btn-group no-print" style="margin-bottom:10px;gap:6px;flex-wrap:wrap">
  <?php foreach (['pending' => __('Pending Approvals'), 'approved' => __('Approved Payments'), 'rejected' => __('Rejected Payments'), 'all' => __('All Requests')] as $k => $label): ?>
    <a class="btn <?= $status === $k ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:999px;padding:8px 18px" href="<?= e(url($baseRoute, ['status' => $k, 'type' => $type, 'q' => $q])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form method="get" class="toolbar"><input type="hidden" name="r" value="<?= e($baseRoute) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
  <select name="type" onchange="this.form.submit()"><option value=""><?= __('All types') ?></option><?php foreach (['registration','payment','transfer','expense','payroll','payroll_payment','advance','loan','bonus','employee_bonus','discount','refund','other','salesman_issue','claim','purchase_request','purchase_invoice','maintenance','rm_consumption','other_income'] as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(approval_label($t)) ?></option><?php endforeach; ?></select>
  <input name="q" placeholder="<?= e(__('Search requester / reason…')) ?>" value="<?= e($q) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
</form>
<div class="alert alert-info"><?= __('Approvals post the source document exactly once when approved. Rejections never touch balances. Requesters cannot approve their own requests.') ?></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th><?= __('Party') ?></th><th><?= __('Type') ?></th><th><?= __('Requested By') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Payment Method') ?></th><th><?= __('Requested') ?></th><th><?= __('Status') ?></th><th></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $r): $s = $r['src'] ?? []; $kind = $s['party_kind'] ?? null; ?><tr>
<td class="mono">#<?= (int)$r['id'] ?><br><span class="muted" style="font-size:10px"><?= e(approval_reference_label((string)$r['reference_type'])) ?> #<?= (int)$r['reference_id'] ?></span></td>
<td>
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($kind === 'employee' || $kind === 'salesman'): ?>
      <?php if (!empty($s['party_photo'])): ?><img class="emp-photo" src="<?= e(photo_data_uri($s['party_photo'])) ?>" alt="<?= e($s['party'] ?? '') ?>" style="width:44px;height:44px"><?php else: ?><div class="emp-avatar" style="width:44px;height:44px;font-size:16px"><?= e(mb_strtoupper(mb_substr((string)($s['party'] ?? '?'), 0, 1))) ?></div><?php endif; ?>
    <?php else: ?>
      <div class="emp-avatar" style="width:44px;height:44px;font-size:16px;background:var(--amber-soft,#f6ead8);color:#7a5a17"><?= e(mb_strtoupper(mb_substr((string)($s['party'] ?? '?'), 0, 1))) ?></div>
    <?php endif; ?>
    <div>
      <strong><?= e($s['party'] ?? '—') ?></strong>
      <?php if (!empty($s['party_code'])): ?><span class="muted mono" style="font-size:11px"> (<?= e($s['party_code']) ?>)</span><?php endif; ?><br>
      <span class="badge gray" style="text-transform:uppercase;font-size:10px"><?= e($kind ? ($partyKindLabel[$kind] ?? $kind) : __('Account')) ?></span>
      <?php if (!empty($s['description'])): ?><br><span class="muted" style="font-size:11px;max-width:220px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($s['description']) ?>"><?= e($s['description']) ?></span><?php endif; ?>
    </div>
  </div>
</td>
<td><span class="badge amber"><?= e(approval_label((string)$r['request_type'])) ?></span></td>
<td><?= e($r['requester_name'] ?: ($r['requester'] ?? '—')) ?><br><span class="muted mono" style="font-size:11px">@<?= e($r['requester'] ?? '—') ?></span></td>
<td class="num"><strong><?= money($r['amount']) ?></strong></td>
<td><strong><?= e($s['method'] ?? '—') ?></strong><?php if (!empty($s['account'])): ?><br><span class="muted" style="font-size:11px"><?= e($s['account']) ?></span><?php endif; ?></td>
<td style="white-space:nowrap"><?= !empty($s['date']) ? e(date_display($s['date'])) : dt_display($r['requested_at']) ?></td>
<td><span class="badge <?= $r['status'] === 'approved' ? 'green' : ($r['status'] === 'rejected' ? 'red' : 'amber') ?>"><?= e(strtoupper($r['status'])) ?></span></td>
<td><a class="btn btn-outline btn-sm" href="<?= e(url($baseRoute . '/' . (int)$r['id'])) ?>"><?= __('Details →') ?></a></td>
<td>
<?php if ($canDecide && $r['status'] === 'pending'):
  $docInfo = [
    ['label' => __('Document #'), 'value' => approval_reference_label((string)$r['reference_type']) . ' #' . $r['reference_id']],
    ['label' => __('Document Type'), 'value' => approval_label((string)$r['request_type'])],
    ['label' => __('Amount'), 'value' => money($r['amount'])],
    ['label' => __('Requester'), 'value' => ($r['requester_name'] ?? '') ?: ($r['requester'] ?? '—')],
  ];
  $docJson = e(json_encode($docInfo, JSON_UNESCAPED_UNICODE));
  $noteSel = '#note-' . (int)$r['id'];
?>
  <form method="post" action="<?= e(url('admin/approvals/decide')) ?>" style="display:flex;gap:4px;align-items:center">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <input class="f" name="note" id="note-<?= (int)$r['id'] ?>" placeholder="<?= e(__('note (required to reject)')) ?>" style="width:150px;padding:4px 6px;font-size:12px">
    <button class="btn btn-primary btn-sm" name="decision" value="approve" type="submit" data-confirm="<?= e(__('Are you sure you want to approve this document?')) ?>" data-confirm-sub="<?= e(__('This document will be posted to the system. This action cannot be undone.')) ?>" data-confirm-title="<?= e(__('Confirm Approval')) ?>" data-confirm-ok="<?= e(__('Confirm & Approve')) ?>" data-confirm-variant="approve" data-confirm-doc="<?= $docJson ?>"><?= __('Approve') ?></button>
    <button class="btn btn-danger btn-sm" name="decision" value="reject" type="submit" data-confirm="<?= e(__('Reject without posting?')) ?>" data-confirm-title="<?= e(__('Confirm Rejection')) ?>" data-confirm-ok="<?= e(__('Reject')) ?>" data-confirm-variant="danger" data-confirm-doc="<?= $docJson ?>" data-confirm-require-note="<?= e($noteSel) ?>"><?= __('Reject') ?></button>
  </form>
<?php elseif ($r['status'] === 'pending'): ?><span class="badge amber" style="font-size:11px"><?= __('PENDING') ?></span><br><span class="muted" style="font-size:11px"><?= __('Awaiting Owner/Admin approval') ?></span>
<?php else: ?><span class="muted" style="font-size:11px"><?= __('by') ?> <?= e($r['decider'] ?? '—') ?> · <?= dt_display($r['decided_at']) ?><?= $r['decision_note'] ? ' · ' . e($r['decision_note']) : '' ?></span><?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php if (!$p['rows']): ?><tr><td colspan="10" class="muted" style="text-align:center;padding:30px"><?= __('Nothing here.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, $baseRoute, ['status' => $status, 'type' => $type, 'q' => $q]) ?></div>
