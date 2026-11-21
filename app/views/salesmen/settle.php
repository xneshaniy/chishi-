<?php $title = __('Settle —') . e($sm['name']); ?>
<form method="get" class="toolbar no-print"><input type="hidden" name="r" value="salesmen/<?= (int)$sm['id'] ?>/settle"><label class="muted"><?= __('Date:') ?></label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"><span class="muted"><?= e($sm['name']) ?></span><span style="flex:1"></span><button class="btn btn-outline" type="button" onclick="window.print()">🖨 <?= __('Print Settlement') ?></button></form>
<div class="print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Daily Settlement') ?> · <?= e($sm['name']) ?> · <?= date_display($date) ?></span><hr></div>
<div class="cards">
  <div class="card gold"><div class="lbl"><?= __('Sales Today') ?></div><div class="val"><?= money($salesToday) ?></div></div>
  <div class="card accent"><div class="lbl"><?= __('Sales This Month') ?></div><div class="val"><?= money($salesMonth) ?></div></div>
  <div class="card blue"><div class="lbl"><?= __('Cash Collected (his customers)') ?></div><div class="val"><?= money($cashCollected) ?></div></div>
</div>
<div class="two-col">
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>">
<h2><?= __('Daily Settlement —') ?><?= e(date_display($date)) ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Expected Cash (from receipts)') ?></label><input class="f" value="<?= number_format($cashCollected, 2) ?>" readonly></div>
  <div><label class="f"><?= __('Cash Counted (Rs)') ?></label><input class="f" name="cash_counted" type="number" step="0.01" min="0" required placeholder="<?= e(__('Count the physical cash')) ?>"></div>
</div>
<p class="help"><?= __('Variance = counted − expected. Negative variance =') ?><strong><?= __('shortage') ?></strong><?= __(', positive =') ?><strong><?= __('excess') ?></strong><?= __('— both are recorded in the settlement history.') ?></p>
<label class="f mt"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. short by Rs 200 — commiss on held / reason noted')) ?>">
<button class="btn btn-primary mt" type="submit"><?= __('Save Settlement') ?></button>
</form>
<div class="panel"><h2><?= __('Stock Currently With Salesman') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('SKU') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Qty (Packets)') ?></th><th class="num"><?= __('Value') ?></th></tr></thead>
<tbody><?php foreach ($stock as $s): ?><tr><td class="mono"><?= e($s['sku']) ?></td><td><?= e($s['name']) ?></td><td class="num"><?= qty($s['qty']) ?></td><td class="num"><?= money($s['value'], false) ?></td></tr><?php endforeach; ?>
<?php if (!$stock): ?><tr><td colspan="4" class="muted"><?= __('Empty.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
</div>
<h3 style="margin-top:14px"><?= __('Recent Issues — Paid vs Free Bonus') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Issue') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Paid (Packets)') ?></th><th class="num"><?= __('Bonus (Packets)') ?></th><th class="num"><?= __('Total (Packets)') ?></th><th class="num"><?= __('Payable') ?></th><th></th></tr></thead>
<tbody><?php foreach ($issues as $it): ?><tr><td><?= date_display($it['date']) ?></td><td class="mono"><?= e($it['issue_no']) ?></td><td><span class="badge <?= $it['issue_type'] === 'transfer' ? 'blue' : 'amber' ?>"><?= strtoupper(e($it['issue_type'])) ?></span></td><td class="num"><?= qty($it['paid_qty']) ?></td><td class="num"><?= qty($it['bonus_qty']) ?></td><td class="num"><?= qty($it['paid_qty'] + $it['bonus_qty']) ?></td><td class="num"><?= money($it['total_payable'], false) ?></td><td><a class="btn btn-outline btn-sm no-print" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issues/' . (int)$it['id'] . '/view')) ?>"><?= __('View/Print') ?></a></td></tr><?php endforeach; ?><?php if (!$issues): ?><tr><td colspan="8" class="muted"><?= __('No issues yet.') ?></td></tr><?php endif; ?></tbody></table></div>
<div class="panel"><h2><?= __('Settlement History') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Sales') ?></th><th class="num"><?= __('Collected') ?></th><th class="num"><?= __('Counted') ?></th><th class="num"><?= __('Variance') ?></th><th><?= __('Notes') ?></th></tr></thead>
<tbody><?php foreach ($settlements as $s): ?><tr><td><?= date_display($s['date']) ?></td><td class="num"><?= money($s['sales_total'], false) ?></td><td class="num"><?= money($s['collected_total'], false) ?></td><td class="num"><?= money($s['cash_counted'], false) ?></td>
<td class="num" style="color:<?= $s['variance'] < 0 ? 'var(--red)' : ($s['variance'] > 0 ? 'var(--amber)' : 'inherit') ?>"><?= ($s['variance'] >= 0 ? '+' : '') . number_format($s['variance'], 2) ?></td><td><?= e($s['notes'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
</div>
