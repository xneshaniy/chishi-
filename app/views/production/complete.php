<?php $title = __('Complete Production'); ?>
<div class="panel">
  <h2><?= __('Complete') ?><?= e($o['order_no']) ?> — <?= e($product['name']) ?></h2>
  <h3 style="font-size:13px;margin:10px 0 6px"><?= __('Planned Material Consumption') ?></h3>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('SKU') ?></th><th><?= __('Material') ?></th><th class="num"><?= __('Consume Qty') ?></th></tr></thead>
    <tbody><?php foreach ($inputs as $i): ?><tr><td class="mono"><?= e($i['sku']) ?></td><td><?= e($i['name']) ?></td><td class="num"><?= qty($i['qty']) ?> <?= e($i['unit_short']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <p class="help"><?= __('Actual consumption is valued at the current') ?><?= e(cfg('costing_method')) ?><?= __('cost of each material when you post completion.') ?></p>
  <form method="post"><?= csrf_field() ?>
    <div class="form-grid">
      <div><label class="f"><?= __('Qty Produced (Packets) *') ?></label><input class="f" name="qty_produced" type="number" step="0.001" min="0.001" required value="<?= e(qty($o['qty_planned'])) ?>"></div>
      <div><label class="f"><?= __('Waste / Reject Qty (Packets)') ?></label><input class="f" name="waste_qty" type="number" step="0.001" min="0" value="0"></div>
      <div><label class="f"><?= __('Labor Cost (Rs)') ?></label><input class="f" name="labor_cost" type="number" step="0.01" min="0" value="0.00"></div>
      <div><label class="f"><?= __('Overhead Cost (Rs)') ?></label><input class="f" name="overhead_cost" type="number" step="0.01" min="0" value="0.00"></div>
      <div style="grid-column:span 2"><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. day shift, night shift, rework')) ?>"></div>
    </div>
    <p class="help"><?= __('Total cost = materials (auto) + labor + overhead. Finished goods are received at (total ÷ produced qty) so inventory valuation is exact.') ?></p>
    <div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Post Completion → Receive Finished Goods') ?></button><a class="btn btn-outline" href="<?= e(url('production/orders')) ?>"><?= __('Cancel') ?></a></div>
  </form>
</div>
