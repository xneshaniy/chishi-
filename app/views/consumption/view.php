<?php $title = __('Consumption Voucher —') . e($issue['issue_no']); ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none;max-width:800px;margin:0 auto">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px double var(--charcoal);padding-bottom:10px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="CTC" style="height:80px">
    <div style="text-align:right"><strong style="font-size:17px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= e(setting('address')) ?></span></div>
  </div>
  <h2 style="justify-content:center"><?= __('FACTORY CONSUMPTION VOUCHER') ?></h2>
  <div class="form-grid" style="margin-bottom:10px">
    <div><strong><?= __('Voucher No:') ?></strong> <?= e($issue['issue_no']) ?></div>
    <div><strong><?= __('Date:') ?></strong> <?= date_display($issue['date']) ?></div>
    <div><strong><?= __('Batch No:') ?></strong> <?= e($issue['batch_no'] ?: '—') ?></div>
    <div><strong><?= __('Status:') ?></strong> <?= strtoupper(e($issue['status'])) ?></div>
    <div><strong><?= __('Issued By:') ?></strong> <?= e($issue['issuer'] ?? '—') ?></div>
    <div><strong><?= __('Approved By:') ?></strong> <?= e($issue['approver'] ?? '—') ?></div>
    <div style="grid-column:span 2"><strong><?= __('Details:') ?></strong><?= e($issue['details'] ?? '—') ?></div>
  </div>
  <table class="tbl"><thead><tr><th>#</th><th><?= __('Raw Material') ?></th><th class="num"><?= __('Qty (KG)') ?></th><th class="num"><?= __('Cost / kg') ?></th><th class="num"><?= __('Cost') ?></th></tr></thead>
  <tbody><?php $totQty = 0; $totCost = 0; $i = 1; foreach ($items as $it): $totQty += (float)$it['qty_kg']; $totCost += (float)$it['total_cost']; ?><tr><td><?= $i++ ?></td><td><?= e($it['sku']) ?> — <?= e($it['name']) ?></td><td class="num"><?= number_format($it['qty_kg'], 3) ?></td><td class="num"><?= number_format($it['unit_cost'], 2) ?></td><td class="num"><?= number_format($it['total_cost'], 2) ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><th colspan="2"><?= __('TOTALS') ?></th><th class="num"><?= number_format($totQty, 3) ?></th><th></th><th class="num"><?= number_format($totCost, 2) ?></th></tr></tfoot></table>
  <?php if ($issue['notes']): ?><p class="help mt"><strong><?= __('Notes:') ?></strong> <?= e($issue['notes']) ?></p><?php endif; ?>
  <div class="two-col mt" style="margin-top:40px">
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Receiver Signature') ?></span></div>
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Storekeeper Signature') ?></span></div>
  </div>
  <p class="help" style="text-align:center;margin-top:12px"><?= __('Computer-generated voucher —') ?><?= e(setting('company_name', cfg('app.company'))) ?></p>
</div>
</div>
<div class="btn-group no-print mt"><button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button><a class="btn btn-outline" href="<?= e(url('stock/consumption')) ?>"><?= __('Back') ?></a></div>
