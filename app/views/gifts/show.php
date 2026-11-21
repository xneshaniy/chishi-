<?php $title = __('Gift') . ' ' . e((string)$gift['gift_no']); ?>
<?php $isOwnerAdmin = is_owner_or_admin(); $isPending = $gift['status'] === 'pending'; $type = $gift['gift_type'];
  $badge = ['pending' => 'amber', 'posted' => 'green', 'rejected' => 'red', 'cancelled' => 'gray'][$gift['status']] ?? 'gray';
?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('gifts')) ?>">&larr; <?= __('Back to Gifts') ?></a>
  <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</div>
<div class="simple-hero" style="padding:14px 18px;margin-bottom:12px">
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div><span style="font-size:22px;font-weight:800"><?= e($gift['recipient_name']) ?></span><br><small class="muted"><?= e($gift['gift_no']) ?> · <?= $gift['recipient_type'] ? e(strtoupper($gift['recipient_type'])) : '—' ?> · <?= e(date_display($gift['gift_date'])) ?> · <?= __('Recorded by') ?>: <?= e($gift['recorded_by_username'] ?? '—') ?></small></div>
    <span class="badge <?= $badge ?>" style="font-size:14px;padding:6px 14px"><?= e(strtoupper($gift['status'])) ?></span>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Gift Type') ?></div><div class="sc-val" style="font-size:18px"><?= e(strtoupper($type)) ?></div></div>
  <?php if ($type === 'money'): ?>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Amount') ?></div><div class="sc-val" style="font-size:22px"><?= money($gift['amount']) ?></div></div>
    <?php if (!empty($gift['expense_no'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Linked Expense') ?></div><div class="sc-val" style="font-size:15px"><?= e($gift['expense_no']) ?></div></div><?php endif; ?>
  <?php else: ?>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Product') ?></div><div class="sc-val" style="font-size:15px"><?= e($gift['product_name'] ?? '—') ?></div><div class="sc-sub"><?= e($gift['sku'] ?? '') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Quantity') ?></div><div class="sc-val" style="font-size:22px"><?= e(rtrim(rtrim(number_format((float)$gift['qty'], 3, '.', ''), '0'), '.')) ?></div><div class="sc-sub"><?= e(strtoupper($gift['qty_unit'])) ?> · <?= qty($gift['qty_pieces']) ?> <?= __('Packets') ?> · <?= number_format(UnitConverter::packetsToBundles((float)$gift['qty_pieces']), 2) ?> <?= __('Bundles') ?></div></div>
  <?php endif; ?>
</div>
<?php if (!empty($gift['reason'])): ?><p class="help"><strong><?= __('Reason') ?>:</strong> <?= e($gift['reason']) ?></p><?php endif; ?>
<?php if (!empty($gift['description'])): ?><p class="help"><strong><?= __('Description') ?>:</strong><br><?= nl2br(e($gift['description'])) ?></p><?php endif; ?>
<?php if (!empty($gift['notes'])): ?><p class="help"><strong><?= __('Notes') ?>:</strong><br><?= nl2br(e($gift['notes'])) ?></p><?php endif; ?>

<?php if ($isPending && $isOwnerAdmin): ?>
<div class="panel no-print" style="margin-top:14px"><h2><?= __('Cancel gift') ?></h2>
  <form method="post" action="<?= e(url('gifts/' . (int)$gift['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('<?= e(__('Cancel this gift? It will be recorded as cancelled (stock NOT deducted).')) ?>')"><?= __('Cancel this gift') ?></button></form>
</div>
<?php endif; ?>

<div class="panel"><h2><?= __('Audit Trail') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('When') ?></th><th><?= __('Action') ?></th><th><?= __('Details') ?></th></tr></thead>
<tbody><?php foreach ($auditRows as $a): ?><tr><td><?= dt_display($a['created_at']) ?></td><td><?= e($a['action']) ?></td><td><?= e(mb_substr(friendly_audit_details((string)$a['details']), 0, 250)) ?></td></tr><?php endforeach; ?>
<?php if (!$auditRows): ?><tr><td colspan="3" class="muted"><?= __('No audit events.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
