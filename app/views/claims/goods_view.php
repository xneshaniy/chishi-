<?php $title = 'Claim ' . $cg['claim_no'] ?>
<div class="pill-toggle" style="margin-bottom:14px">
  <a href="<?= e(url('claims')) ?>"><?= __('Customer Claims') ?></a>
  <a href="<?= e(url('claims/goods')) ?>" class="on"><?= __('Claim Goods') ?></a>
</div>
<?php if ($cg['status'] === 'pending_approval'): ?><div class="alert alert-warn"><strong><?= __('PENDING APPROVAL') ?></strong> — this claim is not posted. Stock will not move, and no cash/payable entry will be created, until Owner/Admin approves it in the Admin Console (Admin Console → Approvals).</div><?php endif; ?>
<?php if ($cg['status'] === 'rejected'): ?><div class="alert alert-error"><strong><?= __('REJECTED') ?></strong> — this claim was rejected. Nothing was posted.</div><?php endif; ?>
<div class="cards">
  <div class="card accent"><div class="lbl"><?= __('Good / Accepted') ?></div><div class="val"><?= qty($cg['good_qty']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Damaged') ?></div><div class="val"><?= qty($cg['damaged_qty']) ?></div></div>
  <div class="card red"><div class="lbl"><?= __('Rejected') ?></div><div class="val"><?= qty($cg['rejected_qty']) ?></div></div>
  <div class="card gold"><div class="lbl"><?= __('Eligible Claim') ?></div><div class="val"><?= money($cg['claim_amount']) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Cash Paid') ?></div><div class="val"><?= money($cg['cash_paid']) ?></div></div>
  <div class="card <?= $outstanding > 0 ? 'red' : 'accent' ?>"><div class="lbl"><?= __('Outstanding') ?></div><div class="val"><?= money($outstanding) ?></div></div>
</div>

<div class="panel">
  <h2><?= e($cg['supplier']) ?> <span class="muted"><?= e($cg['supplier_code']) ?> · <?= e($cg['warehouse']) ?></span></h2>
  <p><strong><?= __('Claim No:') ?></strong> <?= e($cg['claim_no']) ?> · <strong><?= __('Date:') ?></strong> <?= date_display($cg['date']) ?> · <strong><?= __('Payment:') ?></strong> <?= $cg['payment_type'] === 'cash' ? 'Cash (pay now)' : 'Outstanding — pay later' ?><br>
     <strong><?= __('Status:') ?></strong> <span class="badge <?= ['pending_approval' => 'amber', 'posted' => 'green', 'rejected' => 'red', 'reversed' => 'gray'][$cg['status']] ?? 'gray' ?>"><?= e(strtoupper(str_replace('_', ' ', $cg['status']))) ?></span>
     <?= $cg['approved_by'] ? ' · <strong>' . __('Approved by:') . '</strong> ' . e(db_val('SELECT full_name FROM users WHERE id = ?', [(int)$cg['approved_by']])) : '' ?>
     <?php if (!empty($cg['reason'])): ?> · <strong><?= __('Reason:') ?></strong> <?= e($cg['reason']) ?><?php endif; ?></p>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Good Qty') ?></th><th class="num"><?= __('Damaged Qty') ?></th><th class="num"><?= __('Rejected Qty') ?></th><th class="num"><?= __('Claim Rate') ?></th><th class="num"><?= __('Line Claim') ?></th></tr></thead>
  <tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['sku']) ?> — <?= e($it['product']) ?></td><td class="num"><?= qty($it['good_qty']) ?></td><td class="num"><?= qty($it['damaged_qty']) ?></td><td class="num"><?= qty($it['rejected_qty']) ?></td><td class="num"><?= money($it['claim_rate'], false) ?></td><td class="num"><?= money($it['line_claim']) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><th><?= __('TOTAL') ?></th><th class="num"><?= qty(array_sum(array_column($items, 'good_qty'))) ?></th><th class="num"><?= qty(array_sum(array_column($items, 'damaged_qty'))) ?></th><th class="num"><?= qty(array_sum(array_column($items, 'rejected_qty'))) ?></th><th></th><th class="num"><?= money($cg['claim_amount']) ?></th></tr></tfoot></table></div>
  <p class="help"><?= __('Damaged and rejected lines never enter saleable stock or stock valuation. Only accepted quantities were (or will be) received into') ?> <?= e($cg['warehouse']) ?> <?= __('valued at the claim rate.') ?> <?= $cg['journal_entry_id'] ? 'Journal #' . e((string)$cg['journal_entry_id']) . ' posted.' : 'Journal not posted yet (pending approval).' ?></p>
  <div class="btn-group no-print mt"><a class="btn btn-dark" href="<?= e(url('claims/goods/' . (int)$cg['id'] . '/receipt')) ?>"><?= __('Print Receipt') ?></a><a class="btn btn-outline" href="<?= e(url('claims/goods')) ?>"><?= __('Back') ?></a></div>
</div>

<?php if ($cg['status'] === 'posted'): ?>
<div class="panel">
  <h2><?= __('Payments Against This Claim') ?> <span class="muted" style="font-size:12px"><?= __('outstanding') ?> <?= money($outstanding) ?></span></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th><?= __('Date') ?></th><th><?= __('Account') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($payments as $p): ?><tr><td class="mono"><?= e($p['payment_no'] ?? '#' . $p['id']) ?></td><td><?= date_display($p['date']) ?></td><td><?= e($p['account']) ?></td><td class="num"><?= money($p['amount']) ?></td><td><span class="badge <?= ['pending'=>'amber','posted'=>'green','rejected'=>'red'][$p['status']] ?? 'gray' ?>"><?= e(strtoupper($p['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="5" class="muted"><?= __('No payments recorded yet.') ?></td></tr><?php endif; ?></tbody></table></div>
  <?php if ($outstanding > 0): ?>
  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Pay Toward Claim (partial or full)') ?></h3>
  <form method="post" action="<?= e(url('claims/goods/' . (int)$cg['id'] . '/pay')) ?>" class="form-grid"><?= csrf_field() ?>
    <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" max="<?= e($outstanding) ?>" name="amount" required></div>
    <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
    <div><label class="f"><?= __('Pay From *') ?></label><select class="f" name="account_id" required><option value=""><?= __('— select —') ?></option><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
    <div style="display:flex;align-items:flex-end"><button class="btn btn-primary" type="submit"><?= __('Submit Payment (for Admin approval)') ?></button></div>
  </form>
  <p class="help"><?= __('Payments post exactly once after Owner/Admin approval: Dr Accounts Payable — supplier, Cr Cash/Bank. The outstanding balance drops when each payment posts.') ?></p>
  <?php else: ?><div class="alert alert-success mt"><?= __('This claim is fully settled. ✓') ?></div><?php endif; ?>
</div>
<?php endif; ?>
