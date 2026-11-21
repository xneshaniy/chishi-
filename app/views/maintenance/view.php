<?php $title = __('Maintenance') . ' ' . ($m['record_no'] ?? ''); $co = cfg('app.company'); $posted = $m['status'] === 'posted'; ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none">
  <div class="doc-head" style="display:flex;justify-content:space-between;gap:20px;border-bottom:3px double var(--charcoal);padding-bottom:12px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="<?= __('Logo') ?>" style="height:100px">
    <div style="text-align:right">
      <strong style="font-size:20px"><?= e(setting('company_name', $co)) ?></strong><br>
      <span class="muted"><?= e(setting('address')) ?></span><br>
      <?php if (setting('phone')): ?><span class="muted"><?= __('Phone:') ?><?= e(setting('phone')) ?></span><br><?php endif; ?>
      <?php if (setting('ntn') || setting('strn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?> <?= setting('strn') ? '· STRN: ' . e(setting('strn')) : '' ?></span><?php endif; ?>
    </div>
  </div>
  <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
    <div>
      <div class="help"><?= __('MAINTENANCE / REPAIR RECORD') ?></div>
      <strong style="font-size:18px"><?= e($m['machine_name']) ?></strong><br>
      <?php if (!empty($m['asset_no'])): ?><span class="muted"><?= __('Asset ID:') ?></span> <span class="mono"><?= e($m['asset_no']) ?></span><br><?php endif; ?>
      <?php if (!empty($m['department'])): ?><span class="muted"><?= __('Department:') ?></span> <?= e($m['department']) ?><br><?php endif; ?>
      <?php if (!empty($m['location'])): ?><span class="muted"><?= __('Location:') ?></span> <?= e($m['location']) ?><br><?php endif; ?>
      <?php if (!empty($m['vendor'])): ?><span class="muted"><?= __('Technician / Repair shop:') ?></span> <?= e($m['vendor']) ?><br><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div class="help"><?= __('RECORD NO') ?></div>
      <strong class="mono" style="font-size:20px"><?= e($m['record_no']) ?></strong><br>
      <?= __('Problem date:') ?><?= date_display($m['problem_date']) ?><br>
      <?= $m['work_date'] ? __('Work date:') . date_display($m['work_date']) . '<br>' : '' ?>
      <?= __('Status:') ?><strong><?= strtoupper(e($m['status'])) ?></strong>
    </div>
  </div>
  <div style="margin-top:12px">
    <div class="help"><?= __('Problem / Damage') ?></div>
    <p style="white-space:pre-line"><?= e($m['problem']) ?></p>
    <?php if (!empty($m['work_performed'])): ?><div class="help"><?= __('Repair / Work Performed') ?></div><p style="white-space:pre-line"><?= e($m['work_performed']) ?></p><?php endif; ?>
  </div>
  <?php if ($parts): ?><div class="table-wrap"><table class="tbl" style="margin-top:8px"><thead><tr><th><?= __('Spare Part') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Unit Cost') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
  <tbody><?php foreach ($parts as $p): ?><tr><td><?= e($p['name']) ?></td><td class="num"><?= qty($p['qty']) ?></td><td class="num"><?= number_format($p['unit_cost'], 2) ?></td><td class="num"><?= number_format($p['line_total'], 2) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><th colspan="3" style="text-align:right"><?= __('Spare Parts Total') ?></th><th class="num"><?= number_format($m['parts_cost'], 2) ?></th></tr></tfoot></table></div><?php endif; ?>
  <table class="tbl" style="margin-top:8px"><tbody>
    <tr><td style="text-align:right;width:70%"><?= __('Spare parts total') ?></td><td class="num"><?= number_format($m['parts_cost'], 2) ?></td></tr>
    <tr><td style="text-align:right"><?= __('Labour charges') ?></td><td class="num"><?= number_format($m['labour_cost'], 2) ?></td></tr>
    <tr><td style="text-align:right"><?= __('Other expenses') ?></td><td class="num"><?= number_format($m['other_cost'], 2) ?></td></tr>
    <tr><td style="text-align:right;font-size:16px"><strong><?= __('TOTAL MAINTENANCE COST') ?></strong></td><td class="num" style="font-size:16px"><strong><?= __('Rs') ?><?= number_format($m['total_cost'], 2) ?></strong></td></tr>
  </tbody></table>
  <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-top:6px">
    <div><span class="muted"><?= __('Payment method:') ?></span> <strong><?= strtoupper(e($m['payment_type'])) ?></strong><?= $m['payment_type'] !== 'credit' && !empty($m['account_id']) ? ' — ' . e(db_val('SELECT name FROM cash_bank_accounts WHERE id = ?', [(int)$m['account_id']])) : '' ?></div>
    <div><span class="muted"><?= __('Payment status:') ?></span> <strong><?= $posted ? __('Paid') : ($m['status'] === 'pending' ? __('PENDING APPROVAL') : strtoupper(e($m['status']))) ?></strong></div>
    <div><span class="muted"><?= __('Recorded by:') ?></span> <?= e(db_val('SELECT full_name FROM users WHERE id = ?', [(int)($m['created_by'] ?? 0)]) ?: 'system') ?></div>
    <?php if (!empty($m['approved_at'])): ?><div><span class="muted"><?= __('Approved by:') ?></span> <?= e(db_val('SELECT full_name FROM users WHERE id = ?', [(int)$m['approved_by']]) ?: 'system') ?> · <?= dt_display($m['approved_at']) ?></div><?php endif; ?>
  </div>
  <?php if (!empty($m['attachment'])): ?><p class="help no-print"><?= __('Attachment:') ?> <a href="<?= e(url('maintenance/' . (int)$m['id'] . '/attachment')) ?>" target="_blank">📎 <?= e($m['record_no']) ?></a></p><?php endif; ?>
  <p class="help"><?= __('Status:') ?> <?= strtoupper(e($m['status'])) ?><?= $m['expense_id'] ? ' · ' . __('Expense linked') . ' #' . (int)$m['expense_id'] : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>

  <?php if ($assetHistory): ?><div class="help"><?= __('Previous history for this asset') ?></div>
  <table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Problem') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($assetHistory as $h): ?><tr><td class="mono"><?= e($h['record_no']) ?></td><td><?= date_display($h['problem_date']) ?></td><td><?= e($h['problem']) ?></td><td class="num"><?= money($h['total_cost'], false) ?></td><td><?= e(strtoupper($h['status'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</div>
</div>
<div class="btn-group btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <?php if ($m['status'] === 'pending' && Auth::can('approvals.decide')): ?>
    <form method="post" action="<?= e(url('maintenance/' . (int)$m['id'] . '/approve')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= __('Approve & Post') ?></button></form>
  <?php endif; ?>
  <?php if ($m['status'] === 'posted' && Auth::can('approvals.decide')): ?>
    <form method="post" action="<?= e(url('maintenance/' . (int)$m['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Reverse this maintenance record? The expense and its journal will be reversed.')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Maintenance reversal — <?= e($m['record_no']) ?>"><button class="btn btn-danger" type="submit"><?= __('Reverse') ?></button></form>
  <?php endif; ?>
  <a class="btn btn-outline" href="<?= e(url('maintenance')) ?>"><?= __('Back to Maintenance') ?></a>
</div>
