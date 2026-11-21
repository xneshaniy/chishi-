<?php $title = 'Recovery Receipt — ' . ($p['receipt_no'] ?: $p['id']); ?>
<div class="print-area">
<?= partial('partials/doc_head', ['docTitle' => __('SALESMAN RECOVERY RECEIPT'), 'docNo' => $p['receipt_no'] ?: ('SP-' . $p['id']), 'docDate' => date_display($p['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('RECEIVED FROM (SALESMAN)') ?></div>
    <strong style="font-size:16px"><?= e($p['salesman_name']) ?></strong><br>
    <span class="muted mono"><?= __('Code:') ?><?= e($p['salesman_code']) ?></span><br>
    <?php if (!empty($p['salesman_phone'])): ?><span class="muted"><?= __('Phone:') ?><?= e($p['salesman_phone']) ?></span><?php endif; ?>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('RECEIPT') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($p['receipt_no'] ?: ('SP-' . $p['id'])) ?></strong><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($p['date']) ?></span><br>
    <span class="muted"><?= __('Received by:') ?><?= e($p['received_full_name'] ?: $p['received_username'] ?: '—') ?></span>
  </div>
</div>
<table class="tbl" style="margin-top:14px">
  <thead><tr><th><?= __('Payment Method') ?></th><th class="num"><?= __('Cash (Rs)') ?></th><th class="num"><?= __('Bank (Rs)') ?></th><th class="num"><?= __('Total (Rs)') ?></th></tr></thead>
  <tbody>
    <tr>
      <td><?= strtoupper(e($p['payment_method'])) ?></td>
      <td class="num"><?= money($p['cash_amount'], false) ?></td>
      <td class="num"><?= money($p['bank_amount'], false) ?></td>
      <td class="num"><strong><?= money($p['total_amount'], false) ?></strong></td>
    </tr>
  </tbody>
</table>
<?php if (!empty($allocations)): ?>
<table class="tbl" style="margin-top:10px">
  <thead><tr><th><?= __('Invoice Number') ?></th><th><?= __('Invoice Date') ?></th><th class="num"><?= __('Amount Allocated') ?></th></tr></thead>
  <tbody><?php foreach ($allocations as $al): ?><tr>
    <td class="mono"><?= e($al['issue_no']) ?></td><td><?= date_display($al['date']) ?></td><td class="num"><?= money($al['amount']) ?></td>
  </tr><?php endforeach; ?></tbody>
</table>
<?php endif; ?>
<table class="tbl" style="margin-top:10px"><tbody>
  <?php if ((float)$p['cash_amount'] > 0): ?><tr><td style="width:40%"><?= __('Cash Account') ?></td><td><?= e($p['cash_account'] ?: '—') ?></td></tr><?php endif; ?>
  <?php if ((float)$p['bank_amount'] > 0): ?><tr><td><?= __('Bank Account') ?></td><td><?= e($p['bank_account'] ?: '—') ?></td></tr><?php endif; ?>
  <?php if (!empty($p['bank_reference'])): ?><tr><td><?= __('Bank Reference / Transaction No.') ?></td><td><?= e($p['bank_reference']) ?></td></tr><?php endif; ?>
  <tr><td><?= __('Reference / Notes') ?></td><td><?= e($p['notes'] ?? '—') ?></td></tr>
  <tr><td><?= __('Amount in words') ?></td><td><strong><?= e(amount_in_words((float)$p['total_amount'])) ?></strong></td></tr>
  <tr><td style="font-size:16px"><strong><?= __('AMOUNT RECEIVED') ?></strong></td><td style="font-size:16px"><strong><?= __('Rs') ?><?= money($p['total_amount'], false) ?></strong></td></tr>
</tbody></table>
<div style="display:flex;justify-content:space-between;margin-top:34px;gap:40px">
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Received by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Salesman signature') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Manager') ?></div>
</div>
<p class="help"><?= __('Status:') ?><?= strtoupper(e($p['status'])) ?><?= !empty($p['journal_entry_id']) ? ' · ' . __('Journal posted') : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)($p['salesman_id'] ?? 0) . '/account')) ?>"><?= __('Back to Account') ?></a>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)($p['salesman_id'] ?? 0) . '/recovery')) ?>"><?= __('Back to Recovery') ?></a>
</div>
