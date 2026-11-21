<?php $title = __('Customer Claims'); ?>
<div class="pill-toggle" style="margin-bottom:14px">
  <a href="<?= e(url('claims')) ?>" class="on"><?= __('Customer Claims') ?></a>
  <a href="<?= e(url('claims/goods')) ?>"><?= __('Claim Goods') ?></a>
</div>
<form method="get" class="toolbar"><input type="hidden" name="r" value="claims">
<select name="status" onchange="this.form.submit()"><option value=""><?= __('All') ?></option><?php foreach (['pending','approved','rejected'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select>
<span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('claims/new')) ?>"><?= __('+ New Claim') ?></a></form>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Customer') ?></th><th><?= __('Invoice') ?></th><th><?= __('Description') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $c): ?><tr><td class="mono"><?= e($c['claim_no']) ?></td><td><?= date_display($c['date']) ?></td><td><?= e($c['customer']) ?></td><td class="mono"><?= e($c['invoice_no'] ?? '—') ?></td><td><?= e($c['description']) ?></td><td class="num"><?= money($c['amount']) ?></td>
<td><span class="badge <?= ['pending'=>'amber','approved'=>'green','rejected'=>'red'][$c['status']] ?>"><?= e(strtoupper($c['status'])) ?></span></td>
<td><?php if ($c['status'] === 'pending' && Auth::can('claims.approve')): ?><form method="post" action="<?= e(url('claims/' . (int)$c['id'] . '/status')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="status" value="approved"><button class="btn btn-primary btn-sm"><?= __('Approve') ?></button></form> <form method="post" action="<?= e(url('claims/' . (int)$c['id'] . '/status')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-danger btn-sm"><?= __('Reject') ?></button></form><?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?><?php if (!$p['rows']): ?><tr><td colspan="8" class="muted"><?= __('No claims.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'claims', ['status' => $status]) ?></div>
