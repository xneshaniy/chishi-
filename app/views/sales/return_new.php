<?php $title = __('New Sales Return'); ?>
<?php if (!$invoice): ?>
<form method="get" class="panel"><input type="hidden" name="r" value="sales/returns/new">
<h2><?= __('Select the Invoice to Return Against') ?></h2>
<select class="f" name="invoice_id" onchange="this.form.submit()"><option value=""><?= __('— choose posted invoice —') ?></option><?php foreach ($invoices as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_no'] . ' — ' . $i['customer'] . ' — Rs ' . number_format($i['total'], 2)) ?></option><?php endforeach; ?></select>
</form>
<?php else: ?>
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
<h2><?= __('Return Against') ?><?= e($invoice['invoice_no']) ?> — <?= e($invoice['customer']) ?></h2>
<div class="form-grid"><div><label class="f"><?= __('Return Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div><div style="grid-column:span 2"><label class="f"><?= __('Reason') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. damaged cigarettes, short-dated stock')) ?>"></div></div>
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Enter quantity to return per line (max = sold − already returned)') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Product') ?></th><th class="num"><?= __('Sold (Packets)') ?></th><th class="num"><?= __('Already Returned (Packets)') ?></th><th class="num"><?= __('Return Qty (Packets)') ?></th></tr></thead>
<tbody><?php foreach ($items as $it): $max = (float)$it['qty'] - (float)$it['returned_qty']; ?>
<tr><td><?= e($it['sku'] . ' — ' . $it['name']) ?></td><td class="num"><?= qty($it['qty']) ?></td><td class="num"><?= qty($it['returned_qty']) ?></td>
<td><input type="hidden" name="items[product_id][<?= (int)$it['product_id'] ?>]" value="<?= (int)$it['product_id'] ?>"><input class="f" style="width:90px" type="number" step="0.001" min="0" max="<?= e($max) ?>" name="items[qty][<?= (int)$it['product_id'] ?>]" value="0" <?= $max <= 0 ? 'disabled' : '' ?>></td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Sales Return') ?></button><a class="btn btn-outline" href="<?= e(url('sales/returns')) ?>"><?= __('Cancel') ?></a></div>
</form>
<?php endif; ?>
