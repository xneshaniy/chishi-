<?php $title = __('Invoice') . e($inv['invoice_no']); $co = cfg('app.company'); ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none">
  <div style="display:flex;justify-content:space-between;gap:20px;border-bottom:3px double var(--charcoal);padding-bottom:12px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="<?= __('Logo') ?>" style="height:110px">
    <div style="text-align:right">
      <strong style="font-size:20px"><?= e(setting('company_name', $co)) ?></strong><br>
      <span class="muted"><?= e(setting('address')) ?></span><br>
      <?php if (setting('phone')): ?><span class="muted"><?= __('Phone:') ?><?= e(setting('phone')) ?></span><br><?php endif; ?>
      <?php if (setting('ntn') || setting('strn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?> <?= setting('strn') ? '· STRN: ' . e(setting('strn')) : '' ?></span><?php endif; ?>
    </div>
  </div>
  <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
    <div>
      <?php if (!empty($inv['customer_id'])): ?>
        <div class="help"><?= __('BILL TO') ?></div>
        <strong style="font-size:15px"><?= e($inv['customer']) ?></strong><br>
        <?= e($inv['customer_address'] ?? '') ?><?= $inv['area'] ? '<br>Area: ' . e($inv['area']) : '' ?><?= $inv['customer_phone'] ? '<br>Ph: ' . e($inv['customer_phone']) : '' ?><?= $inv['customer_ntn'] ? '<br>NTN: ' . e($inv['customer_ntn']) : '' ?>
      <?php else: ?>
        <div class="help"><?= __('ISSUED BY — SALESMAN') ?></div>
        <?php if (!empty($inv['salesman'])): ?>
          <strong style="font-size:15px"><?= e($inv['salesman']) ?></strong><br>
          <?= e($inv['salesman_code'] ?? '') ?><?= !empty($inv['salesman_area']) ? '<br>Area: ' . e($inv['salesman_area']) : '' ?><br>
          <?= __('Walk-in / counter sale') ?>
        <?php else: ?>
          <strong style="font-size:15px"><?= __('Walk-in / counter sale') ?></strong>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <div style="text-align:right">
      <div class="help"><?= __('INVOICE') ?></div>
      <strong class="mono" style="font-size:20px"><?= e($inv['invoice_no']) ?></strong><br><?= __('Date:') ?><?= date_display($inv['date']) ?><?= $inv['due_date'] ? '<br>Due:) ?><strong>' . date_display($inv['due_date']) . '</strong>' : '' ?><br><?= __('Type:') ?><?= strtoupper(e($inv['payment_type'])) ?><?php if (!empty($inv['bonus_scheme'])): ?><br><?= __('Bonus Scheme:') ?><strong><?= e($inv['bonus_scheme']) ?></strong><?php endif; ?><?= $inv['salesman'] ? '<br>Salesman: ' . e($inv['salesman']) : '' ?>
    </div>
  </div>
  <?php $paidQty = 0.0; $bonusQty = 0.0; foreach ($items as $it) { if ((int)$it['is_bonus'] === 1) $bonusQty += (float)$it['qty']; else $paidQty += (float)$it['qty']; } ?>
  <div class="table-wrap"><table class="tbl" style="margin-top:12px">
    <thead><tr><th>#</th><th><?= __('Description') ?></th><th class="num"><?= __('Qty (Packets)') ?></th><th class="num"><?= __('Rate') ?></th><th class="num"><?= __('Disc.') ?></th><th class="num"><?= __('Tax %') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
    <tbody>
    <?php $i = 1; foreach ($items as $it): ?>
      <tr><td><?= $i++ ?></td><td><?= e($it['name']) ?><?= $it['is_bonus'] ? ' <span class="badge green">BONUS / FREE</span>' : '' ?><br><span class="muted mono" style="font-size:11px"><?= e($it['sku']) ?></span></td>
      <td class="num"><?= qty($it['qty']) ?> <?= __('Packets') ?></td><td class="num"><?= number_format($it['unit_price'], 2) ?></td><td class="num"><?= number_format($it['discount'], 2) ?></td><td class="num"><?= qty($it['tax_rate']) ?></td><td class="num"><?= number_format($it['line_total'], 2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="6" style="text-align:right"><?= __('Subtotal') ?></th><th class="num"><?= number_format($inv['subtotal'], 2) ?></th></tr>
      <?php if ($inv['discount'] > 0): ?><tr><th colspan="6" style="text-align:right"><?= __('Discount') ?></th><th class="num">− <?= number_format($inv['discount'], 2) ?></th></tr><?php endif; ?>
      <tr><th colspan="6" style="text-align:right"><?= __('Sales Tax') ?></th><th class="num"><?= number_format($inv['tax'], 2) ?></th></tr>
      <tr><th colspan="6" style="text-align:right;font-size:16px"><?= __('GRAND TOTAL') ?></th><th class="num" style="font-size:16px"><?= __('Rs') ?><?= number_format($inv['total'], 2) ?></th></tr>
    </tfoot>
  </table></div>
  <?php if ($bonusQty > 0): ?><p class="help" style="margin:6px 0 0"><?= __('Physical quantity') ?>: <strong><?= number_format($paidQty + $bonusQty, 3) ?></strong> <?= __('Packets') ?> (<?= __('paid') ?> <?= number_format($paidQty, 3) ?> + <?= __('bonus') ?> <?= number_format($bonusQty, 3) ?>)</p><?php endif; ?>
  <p style="margin:12px 0 4px"><strong><?= __('Amount in words:') ?></strong> <?= e(amount_in_words((float)$inv['total'])) ?></p>
  <?php if (setting('terms')): ?><p class="help"><strong><?= __('Terms:') ?></strong> <?= e(setting('terms')) ?></p><?php endif; ?>
  <?php if (setting('bank_name')): ?><p class="help"><?= __('Payment by bank transfer:') ?><?= e(setting('bank_name')) ?> — <?= e(setting('bank_account_title')) ?> — <?= e(setting('bank_account_no')) ?></p><?php endif; ?>
  <?php if (setting('invoice_footer')): ?><p class="help" style="border-top:1px solid #ddd;padding-top:6px"><?= e(setting('invoice_footer')) ?></p><?php endif; ?>
      <?php if ($inv['status'] === 'pending'): ?><div class="alert alert-warn no-print"><strong><?= __('PENDING APPROVAL') ?></strong> — this invoice is not posted. Stock will not move and no journal will be created until Owner/Admin approves it in the Admin Console (discount below minimum or restricted bonus).</div><?php endif; ?>
  <?php if ($inv['authorized_by']): $authBy = db_val('SELECT full_name FROM users WHERE id = ?', [(int)$inv['authorized_by']]); ?><p class="help"><?= __('Bonus/discount authorised by:') ?><strong><?= e($authBy) ?></strong></p><?php endif; ?>
  <p class="help"><?= __('Status:') ?><?= e(strtoupper($inv['status'])) ?><?= $inv['status'] === 'reversed' ? ' — THIS INVOICE WAS REVERSED' : '' ?><?= $inv['status'] === 'pending' ? ' — AWAITING ADMIN APPROVAL' : '' ?><?= __('· Generated') ?><?= dt_display(now_local()) ?><?= __('· Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('sales/returns/new', ['invoice_id' => (int)$inv['id']])) ?>"><?= __('Create Return') ?></a>
  <?php if (Auth::can('journal.reverse') && $inv['status'] === 'posted'): ?>
  <form method="post" action="<?= e(url('sales/invoices/' . (int)$inv['id'] . '/reverse')) ?>" data-confirm="<?= e(__('Reverse this invoice? Stock will be restored and a reversal journal posted. This cannot be undone.')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="reason" value="Customer return / correction — <?= e($inv['invoice_no']) ?>"><button class="btn btn-danger" type="submit"><?= __('Reverse Invoice') ?></button></form>
  <?php endif; ?>
  <a class="btn btn-outline" href="<?= e(url('sales/invoices')) ?>"><?= __('Back to List') ?></a>
</div>
