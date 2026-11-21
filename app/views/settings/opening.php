<?php $title = __('Opening Balances'); ?>
<div class="alert alert-warn"><strong><?= __('One-time setup.') ?></strong> Enter opening balances on each master record first (Customers, Suppliers, Cash &amp; Bank Accounts), then post them here. Posting is idempotent — re-running skips already-posted openings. Entries post against Owner Capital as the balancing figure; review the ledger afterwards.</div>

<?php if ($postedCount > 0): ?><div class="alert alert-info"><?= (int)$postedCount ?><?= __('opening-balance journal entr') ?><?= $postedCount === 1 ? 'y' : 'ies' ?><?= __('already posted.') ?></div><?php endif; ?>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Customers with Opening Balance (') ?><?= count($customers) ?>)</h2>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Customer') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
      <tbody><?php foreach ($customers as $c): ?><tr><td><?= e($c['code']) ?></td><td><?= e($c['name']) ?></td><td><span class="badge <?= $c['opening_type']==='debit'?'blue':'amber' ?>"><?= e(strtoupper($c['opening_type'])) ?></span></td><td class="num"><?= money($c['opening_balance']) ?></td></tr><?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="4" class="muted"><?= __('None — set opening balance when creating/editing customers.') ?></td></tr><?php endif; ?></tbody></table></div>
    <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="scope" value="customers"><button class="btn btn-primary" <?= $customers ? '' : 'disabled' ?>><?= __('Post Customer Openings') ?></button></form>
  </div>

  <div class="panel">
    <h2><?= __('Suppliers with Opening Balance (') ?><?= count($suppliers) ?>)</h2>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Supplier') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
      <tbody><?php foreach ($suppliers as $s): ?><tr><td><?= e($s['code']) ?></td><td><?= e($s['name']) ?></td><td><span class="badge <?= $s['opening_type']==='credit'?'blue':'amber' ?>"><?= e(strtoupper($s['opening_type'])) ?></span></td><td class="num"><?= money($s['opening_balance']) ?></td></tr><?php endforeach; ?>
      <?php if (!$suppliers): ?><tr><td colspan="4" class="muted"><?= __('None — set opening balance on supplier records.') ?></td></tr><?php endif; ?></tbody></table></div>
    <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="scope" value="suppliers"><button class="btn btn-primary" <?= $suppliers ? '' : 'disabled' ?>><?= __('Post Supplier Openings') ?></button></form>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Cash & Bank Opening Balances (') ?><?= count($accounts) ?>)</h2>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Account') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Opening') ?></th></tr></thead>
      <tbody><?php foreach ($accounts as $a): ?><tr><td><?= e($a['name']) ?></td><td><?= e(strtoupper($a['type'])) ?></td><td class="num"><?= money($a['opening_balance']) ?></td></tr><?php endforeach; ?>
      <?php if (!$accounts): ?><tr><td colspan="3" class="muted"><?= __('None — set opening balance on Cash & Bank accounts.') ?></td></tr><?php endif; ?></tbody></table></div>
    <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="scope" value="cashbank"><button class="btn btn-primary" <?= $accounts ? '' : 'disabled' ?>><?= __('Post Cash/Bank Openings') ?></button></form>
  </div>
  <div class="panel">
    <h2><?= __('Everything at once') ?></h2>
    <p><?= __('Posts customer + supplier + cash/bank openings in one go. Safe to repeat — already-posted items are skipped.') ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="scope" value="all"><button class="btn btn-dark"><?= __('Post All Opening Balances') ?></button></form>
    <p class="help mt"><?= __('Opening') ?><em><?= __('stock') ?></em><?= __('(quantities & cost) is handled under') ?><a href="<?= e(url('stock/opening')) ?>"><?= __('Inventory → Opening Stock') ?></a><?= __(', which also posts the Dr Inventory / Cr Capital entry automatically.') ?></p>
  </div>
</div>
