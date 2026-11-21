<?php $title = __('Jampur Reports'); ?>
<div class="panel no-print" style="margin-bottom:14px">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="r" value="jampur/report">
    <label class="muted"><?= __('From') ?></label><input class="f" type="date" name="from" value="<?= e($from) ?>">
    <label class="muted"><?= __('To') ?></label><input class="f" type="date" name="to" value="<?= e($to) ?>">
    <select class="f" name="product_id">
      <option value=""><?= __('All materials') ?></option>
      <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['sku']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= __('Show') ?></button>
    <button class="btn btn-primary" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  </form>
</div>

<div class="panel print-area">
  <div class="print-only" style="text-align:center;margin-bottom:10px"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span><?= __('Jampur Grinding Report') ?> — <?= date_display($from) ?> → <?= date_display($to) ?></span></div>
  <h2><?= __('Summary') ?></h2>
  <div class="att-counts">
    <span class="att-count"><?= __('Sent') ?><b><?= number_format($totSent, 2) ?> KG</b></span>
    <span class="att-count"><?= __('Processed') ?><b><?= number_format($totProcessed, 2) ?> KG</b></span>
    <span class="att-count"><?= __('Loss') ?><b><?= number_format($totLoss, 2) ?> KG</b></span>
    <span class="att-count"><?= __('Returned') ?><b><?= number_format($totReturned, 2) ?> KG</b></span>
    <span class="att-count"><?= __('Pending Charges') ?><b><?= money($pendingCharges) ?></b></span>
    <span class="att-count"><?= __('Paid Charges') ?><b><?= money($paidCharges) ?></b></span>
  </div>

  <h2 style="margin-top:18px"><?= __('Daily Sent to Jampur') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th><?= __('Party') ?></th><th class="num"><?= __('KG') ?></th></tr></thead>
    <tbody><?php $t=0; foreach ($sent as $s): $t+=(float)$s['qty_kg']; ?><tr><td class="mono"><?= e($s['send_no']) ?></td><td><?= date_display($s['date']) ?></td><td><?= e($s['product']) ?></td><td><?= e($s['party_name']) ?></td><td class="num"><?= number_format((float)$s['qty_kg'], 2) ?></td></tr><?php endforeach; ?><?php if (!$sent): ?><tr><td colspan="5" class="muted"><?= __('Nothing sent in this period.') ?></td></tr><?php endif; ?></tbody>
    <tfoot><tr style="font-weight:800"><td colspan="4"><?= __('Total') ?></td><td class="num"><?= number_format($t, 2) ?></td></tr></tfoot>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Grinding Completed') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('Received (KG)') ?></th><th class="num"><?= __('Loss (KG)') ?></th><th class="num"><?= __('Charges (Rs)') ?></th><th><?= __('Charges Status') ?></th></tr></thead>
    <tbody><?php $t=0; $l=0; $c=0; foreach ($ground as $g): $t+=(float)$g['qty_received_kg']; $l+=(float)$g['loss_kg']; $c+=(float)$g['charges']; ?><tr><td class="mono"><?= e($g['send_no']) ?></td><td><?= date_display($g['grind_date']) ?></td><td><?= e($g['product']) ?></td><td class="num"><?= number_format((float)$g['qty_received_kg'], 2) ?></td><td class="num"><?= number_format((float)$g['loss_kg'], 2) ?></td><td class="num"><?= number_format((float)$g['charges'], 2) ?></td><td><?= $g['charges_status'] === 'paid' ? __('Paid') : __('Pending') ?></td></tr><?php endforeach; ?><?php if (!$ground): ?><tr><td colspan="7" class="muted"><?= __('No grinding recorded in this period.') ?></td></tr><?php endif; ?></tbody>
    <tfoot><tr style="font-weight:800"><td colspan="3"><?= __('Total') ?></td><td class="num"><?= number_format($t, 2) ?></td><td class="num"><?= number_format($l, 2) ?></td><td class="num"><?= number_format($c, 2) ?></td><td></td></tr></tfoot>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Material Returned to Ahmedpur') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('KG') ?></th></tr></thead>
    <tbody><?php $t=0; foreach ($returns as $g): $t+=(float)$g['qty_kg']; ?><tr><td class="mono"><?= e($g['send_no']) ?></td><td><?= date_display($g['return_date']) ?></td><td><?= e($g['product']) ?></td><td class="num"><?= number_format((float)$g['qty_kg'], 2) ?></td></tr><?php endforeach; ?><?php if (!$returns): ?><tr><td colspan="4" class="muted"><?= __('Nothing returned in this period.') ?></td></tr><?php endif; ?></tbody>
    <tfoot><tr style="font-weight:800"><td colspan="3"><?= __('Total') ?></td><td class="num"><?= number_format($t, 2) ?></td></tr></tfoot>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Remaining at Jampur') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('Remaining (KG)') ?></th></tr></thead>
    <tbody><?php $t=0; foreach ($remaining as $s): $t+=(float)$s['remaining_kg']; ?><tr><td class="mono"><?= e($s['send_no']) ?></td><td><?= date_display($s['date']) ?></td><td><?= e($s['product']) ?></td><td class="num"><?= number_format((float)$s['remaining_kg'], 2) ?></td></tr><?php endforeach; ?><?php if (!$remaining): ?><tr><td colspan="4" class="muted"><?= __('Nothing remaining at Jampur.') ?></td></tr><?php endif; ?></tbody>
    <tfoot><tr style="font-weight:800"><td colspan="3"><?= __('Total') ?></td><td class="num"><?= number_format($t, 2) ?></td></tr></tfoot>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Grinding Charges') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('Charges (Rs)') ?></th><th><?= __('Status') ?></th><th class="no-print"></th></tr></thead>
    <tbody><?php foreach ($chargesList as $g): ?><tr><td class="mono"><?= e($g['send_no']) ?></td><td><?= date_display($g['grind_date']) ?></td><td><?= e($g['product']) ?></td><td class="num"><?= number_format((float)$g['charges'], 2) ?></td><td><?= $g['charges_status'] === 'paid' ? __('Paid') : __('Pending') ?></td>
      <td class="no-print"><form method="post" action="<?= e(url('jampur/charges/' . (int)$g['id'] . '/pay')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $g['charges_status'] === 'paid' ? __('Mark pending') : __('Mark paid') ?></button></form></td></tr><?php endforeach; ?><?php if (!$chargesList): ?><tr><td colspan="6" class="muted"><?= __('No charges recorded.') ?></td></tr><?php endif; ?></tbody>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Complete Jampur History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Event') ?></th><th><?= __('Ref') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('KG') ?></th><th><?= __('Details') ?></th></tr></thead>
    <tbody><?php foreach ($history as $h): ?><tr><td><?= date_display($h['date']) ?></td><td><?= e(__($h['type'])) ?></td><td class="mono"><?= e($h['ref']) ?></td><td><?= e($h['material']) ?></td><td class="num"><?= number_format($h['qty'], 2) ?></td><td><?= e($h['detail']) ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="6" class="muted"><?= __('No history in this period.') ?></td></tr><?php endif; ?></tbody>
  </table></div>
  <p class="help" style="margin-top:10px;text-align:right"><?= __('Generated') ?>: <?= dt_display(now_local()) ?></p>
</div>
