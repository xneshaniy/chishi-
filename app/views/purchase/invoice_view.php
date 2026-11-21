<?php $title = __('Purchase Invoice') . e($inv['invoice_no'] ?? ''); $co = cfg('app.company'); ?>
<div class="print-area">
<?= partial('partials/doc_head', ['docTitle' => __('PURCHASE INVOICE / GRN'), 'docNo' => $inv['invoice_no'], 'docDate' => date_display($inv['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('SUPPLIER') ?></div>
    <strong style="font-size:16px"><?= e($inv['supplier']) ?></strong><br>
    <?php if (!empty($inv['supplier_code'])): ?><span class="muted mono"><?= __('Code:') ?><?= e($inv['supplier_code']) ?></span><br><?php endif; ?>
    <?php if (!empty($inv['supplier_address'])): ?><span class="muted"><?= e($inv['supplier_address']) ?></span><br><?php endif; ?>
    <?php if (!empty($inv['supplier_phone'])): ?><span class="muted"><?= __('Phone:') ?><?= e($inv['supplier_phone']) ?></span><br><?php endif; ?>
    <?php if (!empty($inv['supplier_ntn'])): ?><span class="muted"><?= __('NTN:') ?><?= e($inv['supplier_ntn']) ?></span><?php endif; ?>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('DOCUMENT') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($inv['invoice_no']) ?></strong><br>
    <span class="muted"><?= __('Supplier invoice:') ?><?= e($inv['supplier_invoice_no'] ?? '—') ?></span><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($inv['date']) ?></span><br>
    <span class="muted"><?= __('Warehouse:') ?><?= e($inv['warehouse']) ?></span><br>
    <span class="muted"><?= __('Method:') ?><?= strtoupper(e($inv['payment_type'])) ?> · <?= __('Status:') ?><?= strtoupper(e($inv['status'])) ?></span>
  </div>
</div>
<div class="table-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>#</th><th><?= __('Product') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Rate') ?></th><th class="num"><?= __('Tax %') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
<tbody><?php $i = 1; foreach ($items as $it): ?><tr><td><?= $i++ ?></td><td><?= e($it['sku']) ?> — <?= e($it['name']) ?><?php if ((int)$it['is_bonus']): ?> <span class="badge green"><?= __('BONUS') ?></span><?php endif; ?></td><td class="num"><?= qty($it['qty']) ?></td><td class="num"><?= number_format($it['unit_cost'], 2) ?></td><td class="num"><?= qty($it['tax_rate']) ?></td><td class="num"><?= number_format($it['line_total'], 2) ?></td></tr><?php endforeach; ?></tbody>
<tfoot>
  <tr><th colspan="5" style="text-align:right"><?= __('Subtotal') ?></th><th class="num"><?= number_format($inv['subtotal'], 2) ?></th></tr>
  <?php if ((float)$inv['discount'] > 0): ?><tr><th colspan="5" style="text-align:right"><?= __('Discount') ?></th><th class="num"><?= number_format($inv['discount'], 2) ?></th></tr><?php endif; ?>
  <tr><th colspan="5" style="text-align:right"><?= __('Sales Tax') ?></th><th class="num"><?= number_format($inv['tax'], 2) ?></th></tr>
  <tr><th colspan="5" style="text-align:right;font-size:16px"><?= __('GRAND TOTAL') ?></th><th class="num" style="font-size:16px"><?= __('Rs') ?><?= number_format($inv['total'], 2) ?></th></tr>
</tfoot></table></div>
<p class="help"><strong><?= __('Amount in words:') ?></strong> <?= e(amount_in_words((float)$inv['total'])) ?></p>
<p class="help"><?= __('Payment status:') ?> <strong><?= e(strtoupper($inv['payment_status'])) ?></strong> — <?= money($inv['amount_paid'], false) ?> <?= __('of') ?> <?= money($inv['total'], false) ?> <?= __('paid') ?><br>
<?= __('Status:') ?> <?= strtoupper(e($inv['status'])) ?><?= !empty($inv['journal_entry_id']) ? ' · ' . __('Journal posted') : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('purchase/invoices')) ?>"><?= __('Back to Invoices') ?></a>
</div>
