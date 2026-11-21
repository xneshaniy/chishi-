<?php
$title = __('Claims — ') . e($sm['name']);
$tab = 'claims';
require __DIR__ . '/../partials/sm_tabs.php';
?>
<h2 style="margin-bottom:6px"><?= __('Claims') ?></h2>
<p class="help"><?= __('Customer claims filed by your customers, and supplier claim goods from purchases. Existing claims records are reused — nothing duplicated.') ?></p>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Customer Claims') ?></h2>
    <?php if (!$custClaims): ?><p class="muted"><?= __('No customer claims for your customers yet.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Customer') ?></th><th><?= __('Invoice') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
        <tbody><?php foreach ($custClaims as $cl): ?><tr>
          <td><?= date_display($cl['date']) ?></td>
          <td><div><strong><?= e($cl['customer']) ?></strong> <span class="muted">(<?= e($cl['customer_code']) ?>)</span></div></td>
          <td class="mono"><?= e($cl['invoice_no'] ?? '—') ?></td>
          <td><?= e($cl['product'] ?? '—') ?></td>
          <td class="num"><?= money($cl['amount'], false) ?></td>
          <td><span class="badge <?= $cl['status'] === 'approved' ? 'green' : ($cl['status'] === 'pending' ? 'amber' : 'red') ?>"><?= e(strtoupper($cl['status'])) ?></span></td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Supplier Claim Goods') ?></h2>
    <p class="help" style="margin-top:0"><?= __('Goods returned to suppliers for credit — from the same Purchase → Claim Goods module.') ?></p>
    <?php if (!$supplierClaims): ?><p class="muted"><?= __('No supplier claim goods recorded yet.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Claim #') ?></th><th><?= __('Supplier') ?></th><th><?= __('Product') ?></th><th class="num">Good</th><th class="num">Damaged</th><th class="num">Rejected</th><th class="num"><?= __('Claim Amount') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($supplierClaims as $r): ?><tr>
          <td><?= date_display($r['date']) ?></td>
          <td class="mono"><?= e($r['claim_no']) ?></td>
          <td><?= e($r['supplier']) ?></td>
          <td><?= e($r['product']) ?> <span class="muted"><?= e($r['sku']) ?></span></td>
          <td class="num"><?= number_format((float)$r['good_qty'], 0) ?></td>
          <td class="num"><?= number_format((float)$r['damaged_qty'], 0) ?></td>
          <td class="num"><?= number_format((float)$r['rejected_qty'], 0) ?></td>
          <td class="num"><?= money($r['claim_amount'], false) ?></td>
          <td><span class="badge <?= $r['status'] === 'approved' || $r['status'] === 'posted' ? 'green' : ($r['status'] === 'pending_approval' ? 'amber' : 'gray') ?>"><?= e(strtoupper($r['status'])) ?></span></td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>
