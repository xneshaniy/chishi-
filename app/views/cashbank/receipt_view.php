<?php $title = 'Payment Receipt'; $thermalV = !empty($thermal); ?>
<div class="print-area <?= $thermalV ? 'thermal-sheet' : '' ?>">
<?= partial('partials/doc_head', ['docTitle' => __('RECEIPT'), 'docNo' => $r['receipt_no'], 'docDate' => date_display($r['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('RECEIVED FROM') ?></div>
    <strong style="font-size:16px"><?= e($r['customer']) ?></strong><br>
    <?php if (!empty($r['customer_code'])): ?><span class="muted mono"><?= __('Code:') ?><?= e($r['customer_code']) ?></span><br><?php endif; ?>
    <?php if (!empty($r['customer_address'])): ?><span class="muted"><?= e($r['customer_address']) ?></span><br><?php endif; ?>
    <?php if (!empty($r['customer_phone'])): ?><span class="muted"><?= __('Phone:') ?><?= e($r['customer_phone']) ?></span><?php endif; ?>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('RECEIPT') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($r['receipt_no']) ?></strong><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($r['date']) ?></span><br>
    <span class="muted"><?= __('Received into:') ?><?= e($r['account']) ?></span>
  </div>
</div>
<table class="tbl" style="margin-top:14px"><tbody>
  <tr><td style="width:55%"><?= __('Reference / Notes') ?></td><td><?= e($r['reference'] ?? '—') ?> <?= e($r['notes'] ?? '') ?></td></tr>
  <tr><td><?= __('Amount in words') ?></td><td><strong><?= e(amount_in_words((float)$r['amount'])) ?></strong></td></tr>
  <tr><td style="font-size:16px"><strong><?= __('AMOUNT RECEIVED') ?></strong></td><td style="font-size:16px"><strong><?= __('Rs') ?><?= money($r['amount'], false) ?></strong></td></tr>
</tbody></table>
<div style="display:flex;justify-content:space-between;margin-top:34px;gap:40px">
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Received by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Customer signature') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Manager') ?></div>
</div>
<p class="help"><?= __('Status:') ?><?= strtoupper(e($r['status'])) ?><?= !empty($r['journal_entry_id']) ? ' · ' . __('Journal posted') : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('cashbank/receipts/' . (int)$r['id'], ['thermal' => 1])) ?>"><?= __('Thermal 80mm') ?></a>
  <a class="btn btn-outline" href="<?= e(url('cashbank/receipts')) ?>"><?= __('Back to Receipts') ?></a>
</div>
