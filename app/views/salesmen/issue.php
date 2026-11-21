<?php $title = __('Goods Issued / Stock Received') . ' — ' . e($sm['name']); $tab = 'issue'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<?php
$products = db_all('SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name');
$sumPaid = array_sum(array_column($rows, 'paid_qty')); $sumBonus = array_sum(array_column($rows, 'bonus_qty')); $sumTotal = array_sum(array_column($rows, 'total_qty')); $sumPayable = array_sum(array_column($rows, 'line_payable'));
?>
<form method="get" class="toolbar" style="flex-wrap:wrap;margin-bottom:10px">
  <input type="hidden" name="r" value="salesmen/<?= (int)$sm['id'] ?>/issue">
  <input type="date" name="from" value="<?= e($from) ?>">
  <input type="date" name="to" value="<?= e($to) ?>">
  <select name="product_id"><option value="0"><?= __('All products') ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $product_id === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['sku']) ?> — <?= e($p['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issue', ['from' => $from, 'to' => $to, 'product_id' => $product_id])) ?>" target="_blank"><?= __('Print / Save as PDF') ?></a>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issue', ['from' => $from, 'to' => $to, 'product_id' => $product_id, 'export' => 'csv'])) ?>"><?= __('CSV') ?></a>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issue')) ?>"><?= __('Reset') ?></a>
</form>
<div class="panel print-area">
  <h2><?= __('Goods Issued / Stock Received — ') ?><span style="color:var(--green-deep)"><?= e($sm['name']) ?></span></h2>
  <p class="help" style="margin:0 0 6px"><?= __('Issuing goods to a salesman is a stock transfer, not a customer sale. Stock decreases from Main Warehouse and increases in the salesman van.') ?></p>
  <div class="cards" style="margin:10px 0">
    <div class="card blue"><div class="lbl"><?= __('Regular (Paid, Packets)') ?></div><div class="val"><?= qty($sumPaid) ?></div></div>
    <div class="card gold"><div class="lbl"><?= __('Bonus (Packets)') ?></div><div class="val"><?= qty($sumBonus) ?></div></div>
    <div class="card accent"><div class="lbl"><?= __('Total Received (Packets)') ?></div><div class="val"><?= qty($sumTotal) ?></div></div>
    <div class="card orange"><div class="lbl"><?= __('Payable') ?></div><div class="val"><?= money($sumPayable) ?></div></div>
  </div>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Reference') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Regular (Packets)') ?></th><th class="num"><?= __('Bonus (Packets)') ?></th><th class="num"><?= __('Total (Packets)') ?></th><th class="num"><?= __('Issue Rate') ?></th><th class="num"><?= __('Payable') ?></th></tr></thead><tbody><?php foreach ($rows as $r): ?>
    <tr><td><?= date_display($r['date']) ?></td><td class="mono"><?= e($r['issue_no']) ?></td><td><?= e($r['sku']) ?> — <?= e($r['product']) ?></td><td class="num"><?= qty($r['paid_qty']) ?></td><td class="num"><?= qty($r['bonus_qty']) ?></td><td class="num"><strong><?= qty($r['total_qty']) ?></strong></td><td class="num"><?= number_format($r['issue_rate'], 2) ?></td><td class="num"><?= money($r['line_payable']) ?></td></tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:24px"><?= __('No entries in this period.') ?></td></tr><?php endif; ?></tbody>
  <tfoot><tr style="font-weight:800"><td colspan="3"><?= __('Totals') ?></td><td class="num"><?= qty($sumPaid) ?></td><td class="num"><?= qty($sumBonus) ?></td><td class="num"><?= qty($sumTotal) ?></td><td></td><td class="num"><?= money($sumPayable) ?></td></tr></tfoot></table></div>
</div>
