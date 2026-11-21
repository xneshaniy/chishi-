<?php $title = __('Journal') . e($entry['entry_no']); ?>
<div class="panel">
<div class="toolbar"><span class="badge <?= ['draft'=>'amber','posted'=>'green','reversed'=>'red'][$entry['status']] ?>" style="font-size:13px"><?= e(strtoupper($entry['status'])) ?></span>
<span class="muted"><?= __('Type') ?><?= e($entry['voucher_type']) ?><?= __('· Date') ?><?= date_display($entry['date']) ?><?= __('· By') ?><?= e($entry['username'] ?? 'system') ?></span>
<span style="flex:1"></span><button class="btn btn-outline no-print" onclick="window.print()"><?= __('🖨 Print') ?></button></div>
<h2><?= e($entry['entry_no']) ?> <span class="muted" style="font-size:13px"><?= e($entry['reference_type'] ? ('ref: ' . $entry['reference_type'] . ' #' . $entry['reference_id']) : 'manual') ?></span></h2>
<p><?= e($entry['memo'] ?? '') ?></p>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Account') ?></th><th><?= __('Memo') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th></tr></thead>
<tbody><?php foreach ($lines as $l): ?><tr><td><code><?= e($l['code']) ?></code> — <?= e($l['name']) ?></td><td><?= e($l['memo'] ?? '') ?></td><td class="num"><?= $l['debit'] ? number_format($l['debit'], 2) : '' ?></td><td class="num"><?= $l['credit'] ? number_format($l['credit'], 2) : '' ?></td></tr><?php endforeach; ?></tbody>
<tfoot><tr><th colspan="2"><?= __('Totals') ?></th><th class="num"><?= number_format($totals['d'], 2) ?></th><th class="num"><?= number_format($totals['c'], 2) ?></th></tr></tfoot></table></div>
<div class="btn-group no-print mt">
<?php if ($entry['status'] === 'draft' && Auth::can('journal.post')): ?>
  <form method="post" action="<?= e(url('accounting/journal/' . (int)$entry['id'] . '/post')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= __('Post Entry') ?></button></form>
<?php endif; ?>
<?php if ($entry['status'] === 'posted' && Auth::can('journal.reverse')): ?>
  <form method="post" action="<?= e(url('accounting/journal/' . (int)$entry['id'] . '/reverse')) ?>" style="display:inline" data-confirm="<?= e(__('Post a reversal of this journal? The original will be marked REVERSED and a mirror entry created.')) ?>"><?= csrf_field() ?><input type="hidden" name="reason" value="Manual reversal via journal <?= e($entry['entry_no']) ?>"><button class="btn btn-danger" type="submit"><?= __('Reverse Entry') ?></button></form>
<?php endif; ?>
<a class="btn btn-outline" href="<?= e(url('accounting/journal')) ?>"><?= __('Back to Journal') ?></a>
</div>
</div>
