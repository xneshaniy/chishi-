<?php $title = __('Stock Ledger'); $f = $filters; ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="stock/movements">
  <input type="date" name="from" value="<?= e($f['from']) ?>"><input type="date" name="to" value="<?= e($f['to']) ?>">
  <select name="product_id"><option value="0"><?= __('All products') ?></option><?php foreach ($products as $pr): ?><option value="<?= (int)$pr['id'] ?>" <?= $f['productId'] === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['sku'] . ' — ' . $pr['name']) ?></option><?php endforeach; ?></select>
  <select name="warehouse_id"><option value="0"><?= __('All warehouses') ?></option><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $f['wh'] === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
  <select name="type"><option value=""><?= __('All types') ?></option><?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('stock/movements', array_merge(['from' => $f['from'], 'to' => $f['to'], 'product_id' => $f['productId'], 'warehouse_id' => $f['wh'], 'type' => $f['type'], 'export' => 'csv']))) ?>"><?= __('Export CSV') ?></a>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>
<div class="panel print-area"><div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Stock Ledger') ?> · <?= date_display($f['from']) ?> – <?= date_display($f['to']) ?></span><hr></div><div class="table-wrap"><table class="tbl">
  <thead><tr><th><?= __('Date') ?></th><th><?= __('SKU / Product') ?></th><th><?= __('Warehouse') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Unit Cost') ?></th><th class="num"><?= __('Total') ?></th><th class="num"><?= __('Balance') ?></th><th><?= __('Reference') ?></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $r): ?>
    <tr><td style="white-space:nowrap"><?= dt_display($r['created_at']) ?></td>
        <td><?= e($r['sku']) ?> · <?= e($r['product']) ?><?= $r['batch_no'] ? ' <span class="badge gray">' . e($r['batch_no']) . '</span>' : '' ?></td>
        <td><?= e($r['warehouse']) ?></td>
        <td><span class="badge <?= (float)$r['qty'] >= 0 ? 'green' : 'amber' ?>"><?= e($r['type']) ?></span></td>
        <td class="num"><?= ((float)$r['qty'] >= 0 ? '+' : '') . qty($r['qty']) ?> <?= e($r['kind'] === 'finished' ? __('Packets') : ($r['unit_short'] ?? '')) ?></td>
        <td class="num"><?= money($r['unit_cost'], false) ?></td>
        <td class="num"><?= money(abs($r['total_cost']), false) ?></td>
        <td class="num"><?= $r['balance_qty'] !== null ? qty($r['balance_qty']) : '—' ?></td>
        <td><?= e(($r['ref_type'] ?? '—') . ($r['ref_id'] ? ' #' . $r['ref_id'] : '')) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$p['rows']): ?><tr><td colspan="9" class="muted"><?= __('No movements in range.') ?></td></tr><?php endif; ?></tbody>
</table></div><?= pager_html($p, 'stock/movements', ['from' => $f['from'], 'to' => $f['to'], 'product_id' => $f['productId'], 'warehouse_id' => $f['wh'], 'type' => $f['type']]) ?></div>
