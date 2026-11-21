<?php $title = __('Company Stock Daily'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="reports/company-stock">
  <label class="muted"><?= __('Date') ?></label><input type="date" class="f" name="date" value="<?= e($date) ?>">
  <select class="f" name="salesman_id"><option value="0"><?= __('All salesmen') ?></option><?php foreach ($salespeople as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $smId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <select class="f" name="product_id"><option value="0"><?= __('All finished products') ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-outline" type="button" onclick="window.location='<?= e(url('reports/company-stock', ['date' => $date, 'salesman_id' => $smId, 'product_id' => $productId, 'export' => 'factory'])) ?>'"><?= __('Factory CSV') ?></button>
  <button class="btn btn-outline" type="button" onclick="window.location='<?= e(url('reports/company-stock', ['date' => $date, 'salesman_id' => $smId, 'product_id' => $productId, 'export' => 'salesman'])) ?>'"><?= __('Salesmen CSV') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Factory closing (Packets)') ?></div><div class="sc-val" style="font-size:24px"><?= qty($factoryTot['closing']) ?></div><div class="sc-sub"><?= __('company warehouses') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('All salesmen vans combined (Packets)') ?></div><div class="sc-val" style="font-size:24px"><?= qty($vanTot['closing']) ?></div><div class="sc-sub"><?= __('saleable van stock') ?></div></div>
  <div class="stat-chip" style="border-color:#bfe3cd"><div class="sc-lbl"><?= __('Company total (Packets, no double count)') ?></div><div class="sc-val" style="font-size:24px;color:#0a4d29"><?= qty($factoryNow + $vansNow) ?></div><div class="sc-sub"><?= __('factory') ?> <?= qty($factoryNow) ?> + <?= __('vans') ?> <?= qty($vansNow) ?> · <?= e(__('now on hand')) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Factory produced (day, Packets)') ?></div><div class="sc-val" style="font-size:24px"><?= qty($factoryTot['produced']) ?></div><div class="sc-sub"><?= e(date_display($date)) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Issued to salesmen (day, Packets)') ?></div><div class="sc-val" style="font-size:24px"><?= qty($factoryTot['issued']) ?></div><div class="sc-sub"><?= e(date_display($date)) ?></div></div>
</div>

<div class="panel print-area">
  <h2 style="margin-top:0"><?= __('Factory Finished Stock —') . ' ' . e(date_display($date)) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('SKU') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Opening (Packets)') ?></th><th class="num"><?= __('Produced (Packets)') ?></th><th class="num"><?= __('Issued to Salesmen (Packets)') ?></th><th class="num"><?= __('Sold (Packets)') ?></th><th class="num"><?= __('Returns (Packets)') ?></th><th class="num"><?= __('Closing (Packets)') ?></th><th class="num"><?= __('Bundle Eq.') ?></th></tr></thead>
  <tbody>
    <?php foreach ($factoryRows as $r): ?><tr>
      <td class="mono"><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td>
      <td class="num"><?= qty($r['opening']) ?></td><td class="num"><strong><?= qty($r['produced']) ?></strong></td>
      <td class="num"><?= qty($r['issued']) ?></td><td class="num"><?= qty($r['sold']) ?></td><td class="num"><?= qty($r['returns']) ?></td>
      <td class="num"><strong><?= qty($r['closing']) ?></strong></td><td class="num"><?= number_format(UnitConverter::packetsToBundles((float)$r['closing']), 2) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$factoryRows): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:18px"><?= __('No finished-stock rows for this filter.') ?></td></tr><?php else: ?><tr style="font-weight:800;background:#faf7ee"><td colspan="2"><?= __('TOTAL') ?></td><td class="num"><?= qty($factoryTot['opening']) ?></td><td class="num"><?= qty($factoryTot['produced']) ?></td><td class="num"><?= qty($factoryTot['issued']) ?></td><td class="num"><?= qty($factoryTot['sold']) ?></td><td class="num"><?= qty($factoryTot['returns']) ?></td><td class="num"><?= qty($factoryTot['closing']) ?></td><td class="num"><?= number_format(UnitConverter::packetsToBundles((float)$factoryTot['closing']), 2) ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<div class="panel print-area" style="margin-top:14px">
  <h2 style="margin-top:0"><?= __('Salesman Van Stock —') . ' ' . e(date_display($date)) ?><?php if ($smId): ?><span class="muted" style="font-size:12px"><?= __(' — filtered') ?></span><?php endif; ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Salesman') ?></th><th><?= __('Product') ?></th><th class="num"><?= __('Opening (Packets)') ?></th><th class="num"><?= __('Received (Packets)') ?></th><th class="num"><?= __('Bonus Rcvd (Packets)') ?></th><th class="num"><?= __('Sold (Packets)') ?></th><th class="num"><?= __('Returned (Packets)') ?></th><th class="num"><?= __('Closing (Packets)') ?></th><th class="num"><?= __('Bundle Eq.') ?></th></tr></thead>
  <tbody>
    <?php foreach ($salesmanRows as $r): ?><tr>
      <td class="mono"><?= e($r['salesman_code']) ?></td><td><?= e($r['salesman_name']) ?></td><td><?= e($r['sku'] . ' — ' . $r['product']) ?></td>
      <td class="num"><?= qty($r['opening']) ?></td><td class="num"><strong><?= qty($r['received']) ?></strong><div class="muted" style="font-size:10px"><?= qty($r['paid_in']) ?> + <?= qty($r['bonus_in']) ?> bonus</div></td><td class="num"><?= qty($r['bonus_in']) ?></td><td class="num"><?= qty($r['sold']) ?></td><td class="num"><?= qty($r['returned']) ?></td><td class="num"><strong><?= qty($r['closing']) ?></strong></td><td class="num"><?= number_format(UnitConverter::packetsToBundles((float)$r['closing']), 2) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$salesmanRows): ?><tr><td colspan="10" class="muted" style="text-align:center;padding:18px"><?= __('No salesman van stock movements for this filter.') ?></td></tr><?php else: ?><tr style="font-weight:800;background:#faf7ee"><td colspan="3"><?= __('ALL SALESMEN (COMBINED)') ?></td><td class="num"><?= qty($vanTot['opening']) ?></td><td class="num"><?= qty($vanTot['received']) ?></td><td class="num"><?= qty($vanTot['bonus_in']) ?></td><td class="num"><?= qty($vanTot['sold']) ?></td><td class="num"><?= qty($vanTot['returned']) ?></td><td class="num"><?= qty($vanTot['closing']) ?></td><td class="num"><?= number_format(UnitConverter::packetsToBundles((float)$vanTot['closing']), 2) ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<p class="help"><?= __('Opening/closing are computed from the stock ledger (stock_movements) dated on this day; salesman vans and factory warehouses are disjoint, so the combined company total double-counts nothing. Live comment: every issue, sale, return and production move writes exactly one signed movement per warehouse.') ?></p>
