<?php $title = 'Expense Voucher'; $thermalV = !empty($thermal); ?>
<div class="print-area <?= $thermalV ? 'thermal-sheet' : '' ?>">
<?= partial('partials/doc_head', ['docTitle' => __('EXPENSE VOUCHER'), 'docNo' => $x['expense_no'], 'docDate' => date_display($x['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('PAID TO / FOR') ?></div>
    <strong style="font-size:16px"><?= e($x['payee'] ?: '—') ?></strong><br>
    <span class="muted"><?= __('Category:') ?><?= e($x['category']) ?></span>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('VOUCHER') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($x['expense_no']) ?></strong><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($x['date']) ?></span><br>
    <span class="muted"><?= __('Paid from:') ?><?= e($x['account'] ?? '—') ?></span><br>
    <span class="muted"><?= __('Method:') ?><?= strtoupper(e($x['payment_type'])) ?></span>
  </div>
</div>
<table class="tbl" style="margin-top:14px"><tbody>
  <tr><td style="width:55%"><?= __('Description') ?></td><td><?= e($x['description'] ?? '') ?></td></tr>
  <tr><td><?= __('Amount in words') ?></td><td><strong><?= e(amount_in_words((float)$x['amount'])) ?></strong></td></tr>
  <tr><td style="font-size:16px"><strong><?= __('AMOUNT') ?></strong></td><td style="font-size:16px"><strong><?= __('Rs') ?><?= money($x['amount'], false) ?></strong></td></tr>
</tbody></table>
<div style="display:flex;justify-content:space-between;margin-top:34px;gap:40px">
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Prepared by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Checked by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Approved by') ?></div>
</div>
<p class="help"><?= __('Status:') ?><?= strtoupper(e($x['status'])) ?><?= !empty($x['journal_entry_id']) ? ' · ' . __('Journal posted') : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('expenses/' . (int)$x['id'], ['thermal' => 1])) ?>"><?= __('Thermal 80mm') ?></a>
  <a class="btn btn-outline" href="<?= e(url('expenses')) ?>"><?= __('Back to Expenses') ?></a>
</div>
