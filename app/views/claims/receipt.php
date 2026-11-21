<?php $title = 'Claim Receipt ' . e($cg['claim_no']) ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none;max-width:820px;margin:0 auto">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px double var(--charcoal);padding-bottom:10px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="CTC" style="height:90px">
    <div style="text-align:right"><strong style="font-size:18px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= e(setting('address')) ?></span><br><?php if (setting('ntn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?></span><?php endif; ?></div>
  </div>
  <h2 style="justify-content:center"><?= __('CLAIM RECEIPT') ?></h2>
  <div class="form-grid" style="margin-bottom:10px">
    <div><strong><?= __('Receipt No:') ?></strong> <?= e($cg['claim_no']) ?></div>
    <div><strong><?= __('Date:') ?></strong> <?= date_display($cg['date']) ?></div>
    <div><strong><?= __('Supplier:') ?></strong> <?= e($cg['supplier']) ?> (<?= e($cg['supplier_code']) ?>)</div>
    <div><strong><?= __('Warehouse:') ?></strong> <?= e($cg['warehouse']) ?></div>
    <div><strong><?= __('Payment:') ?></strong> <?= $cg['payment_type'] === 'cash' ? 'CASH (paid now)' : 'OUTSTANDING (pay later)' ?></div>
    <div><strong><?= __('Status:') ?></strong> <?= e(strtoupper(str_replace('_', ' ', $cg['status']))) ?></div>
  </div>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Good Qty') ?></th><th class="num"><?= __('Damaged Qty') ?></th><th class="num"><?= __('Rejected Qty') ?></th><th class="num"><?= __('Claim Rate') ?></th><th class="num"><?= __('Line Claim') ?></th></tr></thead>
  <tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['sku']) ?> — <?= e($it['product']) ?></td><td class="num"><?= qty($it['good_qty']) ?></td><td class="num"><?= qty($it['damaged_qty']) ?></td><td class="num"><?= qty($it['rejected_qty']) ?></td><td class="num"><?= number_format($it['claim_rate'], 2) ?></td><td class="num"><?= number_format($it['line_claim'], 2) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><th><?= __('TOTAL') ?></th><th class="num"><?= qty(array_sum(array_column($items, 'good_qty'))) ?></th><th class="num"><?= qty(array_sum(array_column($items, 'damaged_qty'))) ?></th><th class="num"><?= qty(array_sum(array_column($items, 'rejected_qty'))) ?></th><th></th><th class="num"><?= __('Rs') ?><?= number_format($cg['claim_amount'], 2) ?></th></tr></tfoot></table></div>
  <div style="margin-top:14px;text-align:right">
    <div><?= __('Total Good Qty:') ?><strong><?= qty($cg['good_qty']) ?></strong></div>
    <div><?= __('Damaged (not stocked):') ?><strong><?= qty($cg['damaged_qty']) ?></strong></div>
    <div><?= __('Rejected (not stocked):') ?><strong><?= qty($cg['rejected_qty']) ?></strong></div>
    <div style="font-size:18px;margin-top:6px"><strong><?= __('Eligible Claim: Rs') ?><?= number_format($cg['claim_amount'], 2) ?></strong></div>
    <div><?= __('Cash Paid:') ?><strong><?= __('Rs') ?><?= number_format($cg['cash_paid'], 2) ?></strong><?= __('· Outstanding:') ?><strong><?= __('Rs') ?><?= number_format(max(0, $cg['claim_amount'] - $cg['cash_paid']), 2) ?></strong></div>
    <div class="help"><?= __('Amount in words:') ?><?= e(amount_in_words((float)$cg['claim_amount'])) ?></div>
  </div>
  <?php if ($payments): ?><div class="table-wrap" style="margin-top:12px"><h3 class="help" style="font-size:12px;text-transform:uppercase"><?= __('Payments Posted') ?></h3>
    <table class="tbl"><thead><tr><th>#</th><th><?= __('Date') ?></th><th><?= __('Account') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
    <tbody><?php foreach ($payments as $p): ?><tr><td class="mono"><?= e($p['payment_no'] ?? '#' . (int)$p['id']) ?></td><td><?= date_display($p['date']) ?></td><td><?= e($p['account']) ?></td><td class="num"><?= number_format($p['amount'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <p class="help" style="text-align:center;margin-top:16px"><?= __('Computer-generated claim receipt —') ?><?= e(setting('company_name', cfg('app.company'))) ?> · <?= dt_display(now_local()) ?></p>
</div>
</div>
<div class="btn-group no-print mt"><button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button><a class="btn btn-outline" href="<?= e(url('claims/goods/' . (int)$cg['id'])) ?>"><?= __('Back') ?></a></div>
