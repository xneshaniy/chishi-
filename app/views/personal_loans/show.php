<?php $title = __('Loan') . ' ' . e((string)($loan['loan_no'] ?? ''));
  $badge = ['pending' => 'amber', 'partial' => 'blue', 'paid' => 'green', 'overdue' => 'red', 'cancelled' => 'gray'][$loan['status']] ?? 'gray';
  $lbl = ['pending' => __('Pending'), 'partial' => __('Partially Paid'), 'paid' => __('Fully Paid'), 'overdue' => __('Overdue'), 'cancelled' => __('Cancelled')][$loan['status']] ?? $loan['status'];
?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('personal-loans')) ?>">&larr; <?= __('Back to Loans') ?></a>
  <?php if (Auth::can('personal_loans.manage') && $loan['status'] !== 'cancelled'): ?><a class="btn btn-outline btn-sm" href="<?= e(url('personal-loans/' . (int)$loan['id'] . '/edit')) ?>"><?= __('Edit Loan') ?></a><?php endif; ?>
  <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</div>
<div class="simple-hero no-print" style="padding:14px 20px;margin-bottom:12px">
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div><span style="font-size:22px;font-weight:800"><?= e($loan['person_name']) ?></span><br><small class="muted"><?= __('Loan') ?> <?= e($loan['loan_no']) ?> · <?= __('Recorded by') ?>: <?= e($loan['created_by_name'] ?? '—') ?></small></div>
    <span class="badge <?= $badge ?>" style="font-size:14px;padding:6px 14px"><?= e(strtoupper($lbl)) ?></span>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Original Loan Amount') ?></div><div class="sc-val" style="font-size:24px"><?= money($loan['original_amount']) ?></div></div>
  <div class="stat-chip" style="border-color:var(--green,#2e7d4f)"><div class="sc-lbl"><?= __('Total Repaid') ?></div><div class="sc-val" style="color:var(--green,#2e7d4f)"><?= money($loan['total_repaid']) ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Remaining Balance') ?></div><div class="sc-val" style="color:#8f1d13;font-size:24px;font-weight:800"><?= money($loan['remaining_amount']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Loan Date') ?></div><div class="sc-val" style="font-size:16px"><?= e(date_display($loan['loan_date'])) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Expected Return Date') ?></div><div class="sc-val" style="font-size:16px;color:<?= (!empty($loan['expected_date']) && strtotime($loan['expected_date']) < strtotime(date('Y-m-d')) && $loan['remaining_amount'] > 0.004) ? '#b91c1c;font-weight:800' : 'inherit' ?>"><?= $loan['expected_date'] ? e(date_display($loan['expected_date'])) : '—' ?></div></div>
  <?php if (!empty($loan['account_name'])): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Disbursed From') ?></div><div class="sc-val" style="font-size:15px"><?= e($loan['account_name']) ?></div></div><?php endif; ?>
  <?php if ($loan['phone']): ?><div class="stat-chip"><div class="sc-lbl"><?= __('Contact') ?></div><div class="sc-val" style="font-size:15px"><?= e($loan['phone']) ?></div></div><?php endif; ?>
</div>
<?php if (!empty($loan['description'])): ?><p class="help"><strong><?= __('Description') ?>:</strong> <?= e($loan['description']) ?></p><?php endif; ?>
<?php if (!empty($loan['notes'])): ?><p class="help"><strong><?= __('Notes') ?>:</strong><br><?= nl2br(e($loan['notes'])) ?></p><?php endif; ?>

<?php if ($loan['status'] !== 'cancelled' && $loan['remaining_amount'] > 0.004): ?>
<div class="panel" style="margin-top:12px;border:1px solid #bfdbfe;background:#f8faff">
  <h2 style="margin-top:0"><?= __('Record Return / Record Repayment') ?></h2>
  <p class="help"><?= __('Partial repayments allowed. Amount is deposited into the selected cash or bank account.') ?></p>
  <?php if (Auth::can('personal_loans.manage')): ?>
  <form method="post" action="<?= e(url('personal-loans/' . (int)$loan['id'] . '/repay')) ?>" class="form-grid"><?= csrf_field() ?>
    <div><label class="f"><?= __('Repayment Date *') ?></label><input class="f" type="date" name="repayment_date" required value="<?= e(today()) ?>"></div>
    <div><label class="f"><?= __('Amount Received *') ?></label><input class="f num" type="number" step="0.01" min="0.01" max="<?= e((string)$loan['remaining_amount']) ?>" name="amount" required placeholder="<?= e(number_format((float)$loan['remaining_amount'], 2)) ?>" style="font-size:16px;font-weight:700;color:#15803d"></div>
    <div>
      <label class="f"><?= __('Deposit To Account *') ?></label>
      <select class="f" name="account_id" required>
        <?php foreach (($accounts ?? []) as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Payment Note') ?></label><input class="f" name="note" placeholder="<?= e(__('e.g. Cash / online transfer')) ?>"></div>
    <div class="span-2"><button class="btn btn-primary" type="submit"><?= __('Save Repayment — Remaining') ?> <?= money($loan['remaining_amount']) ?> →</button></div>
  </form>
  <?php else: ?><p class="muted"><?= __('You can view this loan but cannot record repayments.') ?></p><?php endif; ?>
</div>
<?php endif; ?>

<div class="panel print-area" style="margin-top:12px">
  <h2 style="margin-top:0"><?= __('Repayment History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('#') ?></th><th><?= __('Date') ?></th><th class="num"><?= __('Amount Received') ?></th><th><?= __('Deposit Account') ?></th><th><?= __('Note') ?></th><th><?= __('Recorded by') ?></th></tr></thead>
  <tbody><?php foreach ($loan['repayments'] as $i => $r): ?><tr>
    <td class="mono"><?= $i + 1 ?></td>
    <td><?= e(date_display($r['repayment_date'])) ?></td>
    <td class="num" style="color:#15803d;font-weight:700"><?= money($r['amount']) ?></td>
    <td><span class="badge gray" style="font-size:11px"><?= e($r['account_name'] ?? '—') ?></span></td>
    <td class="muted"><?= e($r['note'] ?? '') ?></td>
    <td><?= e($r['recorded_by_name'] ?: ($r['recorded_by_username'] ?? '—')) ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$loan['repayments']): ?><tr><td colspan="5" class="muted"><?= __('No repayments yet.') ?></td></tr><?php endif; ?>
  <?php if ($loan['repayments']): ?><tr style="font-weight:800;background:#faf7ee"><td colspan="2"><?= __('Total repaid') ?></td><td class="num"><?= money($loan['total_repaid']) ?></td><td colspan="2"></td></tr>
  <tr style="font-weight:800;background:#faf3ef"><td colspan="2"><?= __('Remaining') ?></td><td class="num"><strong><?= money($loan['remaining_amount']) ?></strong></td><td colspan="2"></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php if (Auth::can('personal_loans.manage') && $loan['status'] !== 'cancelled'): ?>
<div class="panel no-print" style="margin-top:12px;border:1px solid #f2b8b5">
  <h2 style="margin-top:0"><?= __('Cancel Loan') ?></h2>
  <p class="help"><?= __('Cancellation keeps the record for history. Reason is required and the audit log records who cancelled it.') ?></p>
  <form method="post" action="<?= e(url('personal-loans/' . (int)$loan['id'] . '/cancel')) ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?= csrf_field() ?>
    <input class="f" name="reason" placeholder="<?= e(__('reason')) ?>" required style="flex:1;min-width:220px">
    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('<?= e(__('Cancel this loan? The record stays in history.')) ?>')"><?= __('Cancel Loan') ?></button>
  </form>
</div>
<?php endif; ?>
