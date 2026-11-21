<?php $title = __('Company Expenses'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="company-expenses">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="q" placeholder="<?= e(__('Search item / supplier / invoice…')) ?>" value="<?= e($q) ?>" style="min-width:220px">
  <select class="f" name="category_id"><option value="0"><?= __('All categories') ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select class="f" name="status"><option value="all"><?= __('All statuses') ?></option><?php foreach (['pending','posted','rejected','reversed'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(strtoupper($s)) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  <span style="flex:1"></span>
  <?php if (Auth::can('company_expenses.create')): ?><a class="btn btn-primary" href="<?= e(url('company-expenses/new')) ?>"><?= __('+ New Company Expense') ?></a><?php endif; ?>
</form>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total (filtered)') ?></div><div class="sc-val" style="font-size:24px"><?= money($sum) ?></div></div>
  <div class="stat-chip" style="border-color:var(--amber,#e8a13a)"><div class="sc-lbl"><?= __('Pending approvals') ?></div><div class="sc-val"><?= (int)$pending ?></div></div>
</div>
<?php if (is_owner_or_admin() && $pending > 0): ?>
  <div class="alert alert-info no-print"><?= __('Pending company expenses are decided in the Admin Console (/admin/approvals) — exactly once per approval. Approved cash/bank payments update balances; rejected ones never touch anything.') ?></div>
<?php endif; ?>
<div class="panel print-area">
  <h2 style="margin-top:0"><?= __('Company Expenses —') . ' ' . e(date_display($from)) . ' — ' . e(date_display($to)) ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('No.') ?></th><th><?= __('Date') ?></th><th><?= __('Category') ?></th><th><?= __('Item') ?></th><th><?= __('Supplier') ?></th><th><?= __('Via') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $x): ?><tr>
    <td class="mono"><?= e($x['expense_no']) ?></td>
    <td><?= date_display($x['expense_date']) ?></td>
    <td><?= e($x['category']) ?></td>
    <td><strong><?= e($x['item_name']) ?></strong><?php if ($x['description']): ?><br><span class="muted" style="font-size:11px"><?= e(mb_substr($x['description'], 0, 60)) ?></span><?php endif; ?></td>
    <td><?= e($x['supplier'] ?? '—') ?></td>
    <td><span class="badge gray"><?= e(strtoupper($x['payment_type'])) ?></span><?php if ($x['payment_type'] === 'bank' && !empty($x['account'])): ?><br><span class="muted" style="font-size:11px"><?= e($x['account'] ?? '') ?></span><?php endif; ?></td>
    <td class="num"><strong><?= money($x['amount']) ?></strong></td>
    <td><span class="badge <?= ['pending'=>'amber','posted'=>'green','rejected'=>'red','reversed'=>'red'][$x['status']] ?? 'gray' ?>"><?= e(strtoupper($x['status'])) ?></span></td>
    <td class="no-print"><a class="btn btn-outline btn-sm" href="<?= e(url('company-expenses/' . (int)$x['id'])) ?>"><?= __('View →') ?></a></td>
  </tr><?php endforeach; ?>
  <?php if (!$p['rows']): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:30px"><?= __('No company expenses in this period.') ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?= pager_html($p, 'company-expenses', ['from' => $from, 'to' => $to, 'q' => $q, 'category_id' => $cat, 'status' => $status]) ?>
