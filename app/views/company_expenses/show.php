<?php $title = __('Company Expense') . ' — ' . e((string)$row['expense_no']); ?>
<?php $isOwnerAdmin = is_owner_or_admin(); $canEdit = $isOwnerAdmin || Auth::can('company_expenses.create');
  $badge = ['pending' => 'amber', 'posted' => 'green', 'rejected' => 'red', 'reversed' => 'red'][$row['status']] ?? 'gray';
?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('company-expenses')) ?>">&larr; <?= __('Back to Company Expenses') ?></a>
  <?php if ($canEdit && $row['status'] === 'pending'): ?><a class="btn btn-outline btn-sm" href="<?= e(url('company-expenses/' . (int)$row['id'] . '/edit')) ?>"><?= __('Edit') ?></a><?php endif; ?>
  <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</div>
<div class="simple-hero" style="padding:14px 20px;margin-bottom:12px">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div><span style="font-size:22px;font-weight:800"><?= e($row['item_name']) ?></span><br><small class="muted"><?= e($row['expense_no']) ?> · <?= e(date_display($row['expense_date'])) ?> · <?= e($row['category'] ?? '—') ?> · <?= __('Recorded by') ?>: <?= e($row['recorded_by_username'] ?? '—') ?></small></div>
    <span class="badge <?= $badge ?>" style="font-size:14px;padding:6px 14px"><?= e(strtoupper($row['status'])) ?></span>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Amount') ?></div><div class="sc-val" style="font-size:24px"><?= money($row['amount']) ?></div></div>
  <?php if ($row['quantity']): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Quantity') ?></div><div class="sc-val" style="font-size:22px"><?= number_format((float)$row['quantity'], 3) ?></div><div class="sc-sub"><?= e($row['unit'] ?? '') ?><?php if ($row['rate']): ?> · Rs <?= number_format((float)$row['rate'], 2) ?>/<?= e($row['unit'] ?? '') ?><?php endif; ?></div></div><?php endif; ?>
  <?php if ($row['supplier']): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Supplier') ?></div><div class="sc-val" style="font-size:16px"><?= e($row['supplier']) ?></div></div><?php endif; ?>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Payment') ?></div><div class="sc-val" style="font-size:16px"><?= e(strtoupper($row['payment_type'])) ?><?php if ($row['payment_type'] === 'bank' && !empty($row['account'])): ?> · <?= e($row['account']) ?><?php endif; ?></div></div>
</div>
<?php if ($row['description']): ?><p class="help"><strong><?= __('Description') ?>:</strong> <?= e($row['description']) ?></p><?php endif; ?>
<?php if ($row['notes']): ?><p class="help"><strong><?= __('Notes') ?>:</strong><br><?= nl2br(e($row['notes'])) ?></p><?php endif; ?>
<?php if (!empty($row['attachment'])): ?><p class="help"><a href="<?= e(url('admin/attachment', ['path' => $row['attachment']])) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><?= __('View attachment') ?></a></p><?php endif; ?>

<?php if ($isOwnerAdmin && in_array($row['status'], ['pending','posted'], true)): ?>
<div class="panel no-print" style="margin-top:12px;border:1px solid #f2b8b5">
  <h2 style="margin-top:0"><?= __('Cancel this expense') ?></h2>
  <p class="help"><?= __('Cancelling a posted expense reverses its ledger posting automatically; cancelling a pending expense just records it as rejected. The record stays for history either way.') ?></p>
  <form method="post" action="<?= e(url('company-expenses/' . (int)$row['id'] . '/cancel')) ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?= csrf_field() ?>
    <input class="f" name="reason" placeholder="<?= e(__('reason')) ?>" required style="flex:1;min-width:220px">
    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('<?= e(__('Cancel this company expense? This reverses its ledger entry.')) ?>')"><?= __('Cancel expense') ?></button>
  </form>
</div>
<?php endif; ?>

<div class="panel"><h2><?= __('Audit Trail') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('When') ?></th><th><?= __('Action') ?></th><th><?= __('Details') ?></th></tr></thead>
<tbody><?php foreach ($auditRows as $a): ?><tr><td><?= dt_display($a['created_at']) ?></td><td><?= e($a['action']) ?></td><td><?= e(mb_substr(friendly_audit_details((string)$a['details']), 0, 250)) ?></td></tr><?php endforeach; ?>
<?php if (!$auditRows): ?><tr><td colspan="3" class="muted"><?= __('No audit events.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
