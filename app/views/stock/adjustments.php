<?php $title = __('Stock Adjustments'); ?>
<div class="toolbar"><span class="muted"><?= __('Adjustments affect stock and post journals only after approval.') ?></span><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('stock/adjustments/new')) ?>"><?= __('+ New Adjustment') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl">
  <thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Warehouse') ?></th><th><?= __('Reason') ?></th><th><?= __('Status') ?></th><th><?= __('Created By') ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $a): ?>
    <tr><td class="mono"><?= e($a['adjustment_no']) ?></td><td><?= date_display($a['date']) ?></td><td><?= e($a['warehouse']) ?></td><td><?= e($a['reason']) ?></td>
        <td><span class="badge <?= $a['status'] === 'approved' ? 'green' : ($a['status'] === 'rejected' ? 'red' : 'amber') ?>"><?= e(strtoupper($a['status'])) ?></span></td>
        <td><?= e($a['creator'] ?? '—') ?></td>
        <td><?php if ($a['status'] === 'pending' && Auth::can('inventory.adjust_approve')): ?>
          <form method="post" style="display:inline" action="<?= e(url('stock/adjustments/' . (int)$a['id'] . '/approve')) ?>" data-confirm="<?= e(__('Approve and apply this adjustment? Stock and journals will be posted.')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm" type="submit"><?= __('Approve') ?></button></form>
          <form method="post" style="display:inline" action="<?= e(url('stock/adjustments/' . (int)$a['id'] . '/approve')) ?>" data-confirm="<?= e(__('Reject this adjustment?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button class="btn btn-danger btn-sm" type="submit"><?= __('Reject') ?></button></form>
        <?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= __('No adjustments yet.') ?></td></tr><?php endif; ?></tbody>
</table></div></div>
