<?php $title = __('Journal Entries'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="accounting/journal">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <select name="voucher_type"><option value=""><?= __('All types') ?></option><?php foreach ($types as $t): ?><option value="<?= e($t['voucher_type']) ?>" <?= $type === $t['voucher_type'] ? 'selected' : '' ?>><?= e($t['voucher_type']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value=""><?= __('All') ?></option><option value="posted" <?= $status==='posted'?'selected':'' ?>><?= __('Posted') ?></option><option value="draft" <?= $status==='draft'?'selected':'' ?>><?= __('Draft') ?></option><option value="reversed" <?= $status==='reversed'?'selected':'' ?>><?= __('Reversed') ?></option></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('journal.create')): ?><a class="btn btn-primary" href="<?= e(url('accounting/journal/new')) ?>"><?= __('+ Manual Journal') ?></a><?php endif; ?>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Entry No') ?></th><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Reference') ?></th><th><?= __('Memo') ?></th><th><?= __('Status') ?></th><th><?= __('By') ?></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $e): ?><tr><td><a class="mono" href="<?= e(url('accounting/journal/' . (int)$e['id'])) ?>"><?= e($e['entry_no']) ?></a></td><td><?= date_display($e['date']) ?></td>
<td><span class="badge gray"><?= e($e['voucher_type']) ?></span></td><td><?= e(($e['reference_type'] ?? 'manual') . ($e['reference_id'] ? ' #' . $e['reference_id'] : '')) ?></td>
<td style="max-width:300px"><?= e($e['memo'] ?? '') ?></td>
<td><span class="badge <?= ['draft'=>'amber','posted'=>'green','reversed'=>'red'][$e['status']] ?>"><?= e(strtoupper($e['status'])) ?></span></td>
<td><?= e($e['username'] ?? '—') ?></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="7" class="muted"><?= __('No entries in range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'accounting/journal', ['from' => $from, 'to' => $to, 'voucher_type' => $type, 'status' => $status]) ?></div>
