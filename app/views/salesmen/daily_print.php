<?php $title = 'Settlement ' . ($st['reference'] ?: $st['id']); ?>
<div class="print-area">
<?= partial('partials/doc_head', ['docTitle' => __('DAILY SALESMAN SETTLEMENT'), 'docNo' => $st['reference'] ?: ('DS-' . $st['id']), 'docDate' => date_display($st['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('SETTLED BY (SALESMAN)') ?></div>
    <strong style="font-size:16px"><?= e($sm['name']) ?></strong><br>
    <span class="muted mono"><?= __('Code:') ?><?= e($sm['code']) ?></span>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('SETTLEMENT RECEIPT') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($st['reference'] ?: ('DS-' . $st['id'])) ?></strong><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($st['date']) ?></span><br>
    <span class="muted"><?= __('Received by:') ?><?= e($st['received_full_name'] ?: ($st['received_username'] ?: '—')) ?></span>
  </div>
</div>

<h3 style="margin:16px 0 6px"><?= __('Goods Movement') ?></h3>
<table class="tbl">
  <thead><tr><th><?= __('Description') ?></th><th class="num"><?= __('Amount (Rs)') ?></th></tr></thead>
  <tbody>
    <tr><td><?= __('Opening goods issue value (issued this date)') ?></td><td class="num"><?= money($st['issued_value'], false) ?></td></tr>
    <tr><td><?= __('Goods returned and accepted (credit)') ?></td><td class="num">-<?= money($st['returns_value'], false) ?></td></tr>
  </tbody>
</table>

<h3 style="margin:16px 0 6px"><?= __('Money Movement') ?></h3>
<table class="tbl">
  <thead><tr><th><?= __('Description') ?></th><th class="num"><?= __('Amount (Rs)') ?></th></tr></thead>
  <tbody>
    <tr><td><?= __('Total customer collections') ?></td><td class="num"><?= money($st['collections_total'], false) ?></td></tr>
    <tr><td><?= __('Cash handed over to company') ?></td><td class="num"><?= money($st['cash_received'], false) ?></td></tr>
    <tr><td><?= __('Bank deposits / transfers received') ?></td><td class="num"><?= money($st['bank_received'], false) ?></td></tr>
    <tr><td><?= __('Approved expenses paid from collections') ?></td><td class="num">-<?= money($st['expenses_total'], false) ?></td></tr>
  </tbody>
</table>

<h3 style="margin:16px 0 6px"><?= __('Verification') ?></h3>
<table class="tbl">
  <tbody>
    <tr><td style="width:40%"><?= __('Cash counted (physical)') ?></td><td class="num"><?= money($st['cash_counted'], false) ?></td></tr>
    <tr><td><?= __('Posted cash handover') ?></td><td class="num"><?= money($st['expected_cash'], false) ?></td></tr>
    <tr><td><strong><?= __('Variance (+ short / - excess)') ?></strong></td><td class="num" style="color:<?= (float)$st['variance'] < 0 ? '#8f1d13' : '#1d6530' ?>;font-weight:800"><?= money($st['variance'], false) ?></td></tr>
  </tbody>
</table>
<div style="background:<?= (float)$st['remaining_payable'] > 0 ? '#fff5f5' : '#f2fbf5' ?>;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('REMAINING PAYABLE TO COMPANY (all-time)') ?></div>
    <div style="font-size:30px;font-weight:800;color:<?= (float)$st['remaining_payable'] > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money(abs((float)$st['remaining_payable']), false) ?></div>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('AMOUNT IN WORDS') ?></div>
    <strong><?= e(amount_in_words(abs((float)$st['remaining_payable']))) ?></strong>
  </div>
</div>

<?php if (!empty($details['issues'])): ?>
<h3 style="margin:16px 0 6px"><?= __('Issues This Settlement') ?></h3>
<table class="tbl"><thead><tr><th><?= __('Issue No.') ?></th><th class="num"><?= __('Packets') ?></th><th class="num"><?= __('Value (Rs)') ?></th></tr></thead><tbody><?php foreach ($details['issues'] as $i): ?><tr><td class="mono"><?= e($i['issue_no']) ?></td><td class="num"><?= qty($i['units']) ?></td><td class="num"><?= money($i['value'], false) ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<?php if (!empty($details['collections'])): ?>
<h3 style="margin:16px 0 6px"><?= __('Collections This Settlement') ?></h3>
<table class="tbl"><thead><tr><th><?= __('Receipt') ?></th><th><?= __('Customer') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th></tr></thead><tbody><?php foreach ($details['collections'] as $c): ?><tr><td class="mono"><?= e($c['collection_no']) ?></td><td><?= e($c['customer']) ?></td><td class="num"><?= money($c['cash_amount'], false) ?></td><td class="num"><?= money($c['bank_amount'], false) ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>

<?php if (!empty($st['notes'])): ?><p class="help" style="margin-top:12px"><?= __('Notes:') ?><?= e($st['notes']) ?></p><?php endif; ?>
<div style="display:flex;justify-content:space-between;margin-top:34px;gap:40px">
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Received by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Salesman signature') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Manager') ?></div>
</div>
<p class="help"><?= __('Status:') ?>POSTED<?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()">🖨 <?= __('Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/daily')) ?>"><?= __('Back to Daily Settlement') ?></a>
</div>
