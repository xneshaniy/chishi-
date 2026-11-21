<?php $title = __('Other Income'); ?>
<div class="stat-strip" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-chip"><div class="sc-lbl"><?= __('This Month (posted)') ?></div><div class="sc-val"><?= money($monthTotal) ?></div><div class="sc-sub"><?= __('Other income received this month') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Pending Approval') ?></div><div class="sc-val"><?= (int)$pendingCount ?></div><div class="sc-sub"><?= __('Awaiting Owner/Admin') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total (posted)') ?></div><div class="sc-val"><?= money($allTime) ?></div><div class="sc-sub"><?= __('All-time other income') ?></div></div>
</div>

<div class="toolbar no-print" style="margin:12px 0">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="r" value="income">
    <label class="muted"><?= __('From') ?></label><input class="f" type="date" name="from" value="<?= e($from) ?>">
    <label class="muted"><?= __('To') ?></label><input class="f" type="date" name="to" value="<?= e($to) ?>">
    <select class="f" name="status"><option value=""><?= __('All statuses') ?></option>
      <?php foreach (['pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e(__($v)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= __('Show') ?></button>
  </form>
  <?php if (Auth::can('income.create')): ?><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('income/new')) ?>"><?= __('+ Record Income') ?></a><?php endif; ?>
</div>

<div class="panel"><div class="table-wrap"><table class="tbl">
<thead><tr><th><?= __('No') ?></th><th><?= __('Date') ?></th><th><?= __('Category') ?></th><th><?= __('Payer / Description') ?></th><th><?= __('Account') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody>
  <?php foreach ($p['rows'] as $i): ?>
  <tr>
    <td class="mono"><?= e($i['income_no']) ?></td>
    <td><?= date_display($i['date']) ?></td>
    <td><?= e(ucfirst((string)$i['category'])) ?></td>
    <td><strong><?= e($i['payer'] ?: '—') ?></strong><?= !empty($i['description']) ? '<br><span class="muted" style="font-size:11px">' . e($i['description']) . '</span>' : '' ?></td>
    <td><?= e($i['account']) ?></td>
    <td class="num"><?= money($i['amount'], false) ?></td>
    <td><span class="badge <?= $i['status'] === 'posted' ? 'green' : ($i['status'] === 'pending' ? 'amber' : ($i['status'] === 'rejected' ? 'red' : 'gray')) ?>"><?= e(strtoupper($i['status'])) ?></span></td>
    <td class="btn-group">
      <?php if ($i['status'] === 'pending' && Auth::can('approvals.decide')): ?>
        <form method="post" action="<?= e(url('income/' . (int)$i['id'] . '/post')) ?>" data-confirm="<?= e(__('Approve and post this income to the ledger?')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-primary btn-sm" type="submit"><?= __('Approve & Post') ?></button></form>
      <?php endif; ?>
      <?php if ($i['status'] === 'posted' && Auth::can('journal.reverse')): ?>
        <form method="post" action="<?= e(url('income/' . (int)$i['id'] . '/reverse')) ?>" data-confirm="<?= e(__('Reverse this income? The journal will be reversed.')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= __('Reverse') ?></button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$p['rows']): ?><tr><td colspan="8" class="muted"><?= __('No other income recorded yet.') ?></td></tr><?php endif; ?>
</tbody>
</table></div><?= pager_html($p, 'income', ['from' => $from, 'to' => $to, 'status' => $status]) ?></div>
