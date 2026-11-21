<?php $title = __('Cheque Register'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="cashbank/cheques">
<select name="status" onchange="this.form.submit()"><option value=""><?= __('All statuses') ?></option><?php foreach (['pending','cleared','bounced','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
<span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('cashbank/cheques/new')) ?>"><?= __('+ New Cheque') ?></a></form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Cheque No.') ?></th><th><?= __('Bank Account') ?></th><th><?= __('Direction') ?></th><th><?= __('Party') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Issue') ?></th><th><?= __('Due') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $c): ?><tr><td class="mono"><?= e($c['cheque_no']) ?></td><td><?= e($c['account']) ?></td>
<td><span class="badge <?= $c['direction'] === 'received' ? 'blue' : 'amber' ?>"><?= e(strtoupper($c['direction'])) ?></span></td>
<td><?= e($c['party_name'] ?? '—') ?></td><td class="num"><?= money($c['amount']) ?></td><td><?= date_display($c['issue_date']) ?></td><td><?= date_display($c['due_date']) ?></td>
<td><span class="badge <?= ['pending'=>'amber','cleared'=>'green','bounced'=>'red','cancelled'=>'gray'][$c['status']] ?>"><?= e(strtoupper($c['status'])) ?></span></td>
<td><form method="post" action="<?= e(url('cashbank/cheques/' . (int)$c['id'] . '/status')) ?>" style="display:flex;gap:4px"><?= csrf_field() ?>
  <select name="status" class="f" style="width:auto;padding:4px 6px;font-size:12px" onchange="this.form.submit()"><?php foreach (['pending','cleared','bounced','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
</form></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="9" class="muted"><?= __('No cheques recorded.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'cashbank/cheques', ['status' => $status]) ?></div>
