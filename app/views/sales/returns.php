<?php $title = __('Sales Returns'); ?>
<div class="toolbar"><span class="muted"><?= __('Returns restore stock and post reversal journals.') ?></span><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('sales/returns/new')) ?>"><?= __('+ New Return') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Return No.') ?></th><th><?= __('Date') ?></th><th><?= __('Invoice') ?></th><th><?= __('Customer') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $r): ?><tr>
<td class="mono"><?= e($r['return_no']) ?></td><td><?= date_display($r['date']) ?></td><td class="mono"><?= e($r['invoice_no']) ?></td><td><?= e($r['customer']) ?></td><td class="num"><?= money($r['total']) ?></td>
<td><span class="badge <?= $r['status'] === 'posted' ? 'green' : 'red' ?>"><?= e(strtoupper($r['status'])) ?></span></td>
<td><?php if ($r['status'] === 'posted' && Auth::can('journal.reverse')): ?><form method="post" action="<?= e(url('sales/returns/' . (int)$r['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Reverse this return? Stock will be taken out again and the journal reversed.')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Return reversal"><button class="btn btn-danger btn-sm" type="submit"><?= __('Reverse') ?></button></form><?php endif; ?></td>
</tr><?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="7" class="muted"><?= __('No returns yet.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'sales/returns') ?></div>
