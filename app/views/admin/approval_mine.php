<?php $title = 'My Request #' . (int)$req['id']; ?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('notifications')) ?>">&larr; <?= __('Back to Notifications') ?></a>
</div>
<div class="panel">
  <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:12px;margin-bottom:12px">
    <div>
      <div class="help"><?= __('MY APPROVAL REQUEST') ?></div>
      <div style="font-size:22px;font-weight:800">#<?= (int)$req['id'] ?> · <?= e(approval_label((string)$req['request_type'])) ?></div>
      <div class="muted"><?= e(approval_reference_label((string)$req['reference_type'])) ?> #<?= (int)$req['reference_id'] ?></div>
    </div>
    <div>
      <?php if ($req['status'] === 'pending'): ?>
        <span class="badge amber" style="font-size:14px;padding:6px 14px"><?= __('PENDING APPROVAL') ?></span>
      <?php elseif ($req['status'] === 'approved'): ?>
        <span class="badge green" style="font-size:14px;padding:6px 14px"><?= __('APPROVED') ?></span>
      <?php else: ?>
        <span class="badge red" style="font-size:14px;padding:6px 14px"><?= __('REJECTED') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));margin:0 0 14px">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Amount') ?></div><div class="sc-val" style="font-size:22px"><?= money($req['amount']) ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Requested At') ?></div><div class="sc-val" style="font-size:15px"><?= dt_display($req['requested_at'] ?? $req['created_at']) ?></div></div>
    <?php if (!empty($src['date'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Document Date') ?></div><div class="sc-val" style="font-size:15px"><?= date_display($src['date']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['method'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Payment Method') ?></div><div class="sc-val" style="font-size:16px"><?= e($src['method']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['account'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Account') ?></div><div class="sc-val" style="font-size:15px"><?= e($src['account']) ?></div></div><?php endif; ?>
    <?php if (!empty($src['party'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Related Party') ?></div><div class="sc-val" style="font-size:15px"><?= e($src['party']) ?></div></div><?php endif; ?>
  </div>

  <h3 style="margin:4px 0 8px"><?= __('Details') ?></h3>
  <div class="table-wrap"><table class="tbl">
    <tbody>
      <tr><td style="width:35%"><?= __('Request Type') ?></td><td><?= e(approval_label((string)$req['request_type'])) ?></td></tr>
      <tr><td><?= __('Description') ?></td><td><?= e($src['description'] ?: ($req['reason'] ?: '—')) ?></td></tr>
      <?php if ($req['reason'] && $src['description'] !== $req['reason']): ?><tr><td><?= __('Reason / Notes') ?></td><td><?= e($req['reason']) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php if ($req['status'] !== 'pending'): ?>
  <h3 style="margin:14px 0 8px"><?= __('Decision') ?></h3>
  <div class="table-wrap"><table class="tbl">
    <tbody>
      <tr><td><strong><?= __('Outcome') ?></strong></td><td><?= $req['status'] === 'approved' ? '<span class="badge green">' . __('APPROVED') . '</span>' : '<span class="badge red">' . __('REJECTED') . '</span>' ?></td></tr>
      <tr><td><?= __('Decided By') ?></td><td><?= e($decider ? ($decider['full_name'] ?: $decider['username']) : '—') ?> · <?= dt_display($req['decided_at']) ?></td></tr>
      <?php if ($req['decision_note']): ?><tr><td><?= __('Decision Note') ?></td><td><?= e($req['decision_note']) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="panel" style="margin:14px 0 0;background:var(--yellow-soft, #fff8e6);border:1px solid #e8d48a">
    <p class="help" style="margin:0"><?= __('This request is awaiting Owner/Admin approval. You will be notified the moment a decision is made.') ?></p>
  </div>
  <?php endif; ?>
</div>
