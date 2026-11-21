<?php $title = 'Stock Issue Receipt'; $thermalV = !empty($thermal); $paidOnly = (float)$totBonus === 0.0; ?>
<div class="print-area <?= $thermalV ? 'thermal-sheet' : '' ?>">
<?= partial('partials/doc_head', ['docTitle' => __('STOCK ISSUE RECEIPT'), 'docNo' => $issue['issue_no'], 'docDate' => date_display($issue['date'])]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('ISSUED TO') ?></div>
    <strong style="font-size:16px"><?= e($sm['name']) ?></strong><br>
    <span class="muted"><?= __('Code:') ?><?= e($sm['code']) ?></span><br>
    <span class="muted"><?= __('Area:') ?><?= e($sm['area'] ?? '—') ?></span><br>
    <span class="muted"><?= __('Type:') ?><?= strtoupper(e($issue['issue_type'])) ?></span>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('RECEIPT') ?></div>
    <strong class="mono" style="font-size:18px"><?= e($issue['issue_no']) ?></strong><br>
    <span class="muted"><?= __('Date:') ?><?= date_display($issue['date']) ?></span><br>
    <span class="muted"><?= __('From warehouse:') ?><?= e($issue['warehouse']) ?></span><br>
    <span class="muted"><?= __('Status:') ?><strong><?= strtoupper(e($issue['status'])) ?></strong></span>
  </div>
</div>
<div class="table-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>#</th><th><?= __('Product') ?></th><th class="num"><?= __('Paid Qty (Packets)') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th><th class="num"><?= __('Total Qty (Packets)') ?></th><th class="num"><?= __('Issue Rate') ?></th><th class="num"><?= __('Payable') ?></th></tr></thead>
<tbody><?php $i = 1; foreach ($items as $it): ?><tr><td><?= $i++ ?></td><td><?= e($it['sku']) ?> — <?= e($it['name']) ?></td><td class="num"><?= qty($it['paid_qty']) ?></td><td class="num"><?= qty($it['bonus_qty']) ?></td><td class="num"><?= qty($it['qty']) ?></td><td class="num"><?= number_format($it['issue_rate'], 2) ?></td><td class="num"><?= number_format($it['line_payable'], 2) ?></td></tr><?php endforeach; ?></tbody>
<tfoot>
  <tr><th colspan="2" style="text-align:right"><?= __('TOTALS') ?></th><th class="num"><?= qty($totPaid) ?></th><th class="num"><?= qty($totBonus) ?></th><th class="num"><?= qty($totUnits) ?></th><th></th><th class="num"><?= number_format($totPayable, 2) ?></th></tr>
</tfoot></table></div>
<table class="tbl" style="margin-top:8px"><tbody>
  <tr><td style="width:55%"><?= __('Paid products (qty in Packets × issue rate)') ?></td><td><strong><?= qty($totPaid) ?></strong> × <?= __('issue rate') ?></td></tr>
  <tr><td><?= __('Free bonus (not payable)') ?></td><td><strong><?= qty($totBonus) ?></strong></td></tr>
  <tr><td><?= __('Salesman payable') ?></td><td><strong><?= __('Rs') ?><?= number_format($totPayable, 2) ?></strong></td></tr>
  <?php if ((float)$totBonusCost > 0): ?><tr><td><?= __('Bonus cost to company') ?></td><td><?= __('Rs') ?><?= number_format($totBonusCost, 2) ?></td></tr><?php endif; ?>
</tbody></table>
<?php if (!empty($issue['bonus_reason'])): ?><p class="help"><strong><?= __('Bonus reason') ?>:</strong> <?= e($issue['bonus_reason']) ?></p><?php endif; ?>
<?php if (!empty($issue['bonus_scheme'])): ?><p class="help"><strong><?= __('Bonus scheme') ?>:</strong> <?= e($issue['bonus_scheme']) ?></p><?php endif; ?>
<?php if (!empty($issue['notes'])): ?><p class="help"><?= e($issue['notes']) ?></p><?php endif; ?>
<p class="help"><?= __('Amount in words') ?>: <?= e(amount_in_words((float)$totPayable)) ?> · <?= __('Issued by') ?>: <?= e($issue['issuer'] ?: '—') ?><?= !empty($issue['approver']) ? ' · ' . __('Approved by') . ': ' . e($issue['approver']) : '' ?><?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
<div style="display:flex;justify-content:space-between;margin-top:30px;gap:40px">
  <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Issued by') ?></div>
  <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Driver') ?></div>
  <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Salesman signature') ?></div>
</div>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issues/' . (int)$issue['id'] . '/view', ['thermal' => 1])) ?>"><?= __('Thermal 80mm') ?></a>
  <a class="btn btn-outline" href="<?= e(url('salesmen')) ?>"><?= __('Back to Salesmen') ?></a>
</div>
