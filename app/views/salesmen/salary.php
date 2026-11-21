<?php $title = __('Salary —') . e($sm['name']); $tab = 'salary'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<div class="simple-hero" style="padding:16px 20px;margin-bottom:14px">
  <div class="greet" style="display:flex;gap:16px;align-items:center">
    <?php if (!empty($sm['photo_path'])): ?><img class="emp-photo" src="<?= e(photo_data_uri($sm['photo_path'])) ?>" alt="<?= e($sm['name']) ?>" style="width:72px;height:72px;margin:0"><?php else: ?><div class="emp-avatar" style="width:72px;height:72px;font-size:26px;margin:0"><?= e(mb_strtoupper(mb_substr($sm['name'], 0, 1))) ?></div><?php endif; ?>
    <span><span style="font-size:24px;font-weight:800"><?= e($sm['name']) ?></span><br><small><?= e($sm['code'] ?? '') ?> · <?= __('Monthly Salary') ?><?= money($monthly) ?><?= !empty($sm['join_date']) ? ' · ' . __('Since') . ' ' . date_display($sm['join_date']) : '' ?></small></span>
  </div>
  <div style="display:flex;gap:14px;flex-wrap:wrap">
    <div><div class="h-label"><?= __('Earned') ?></div><div class="h-value" style="font-size:20px"><?= money($accrued) ?></div></div>
    <div><div class="h-label"><?= __('Paid') ?></div><div class="h-value" style="font-size:20px"><?= money($paid) ?></div></div>
    <div><div class="h-label"><?= __('Advances Outstanding') ?></div><div class="h-value" style="font-size:20px"><?= money($advancesOut) ?></div></div>
    <div><div class="h-label"><?= __('Remaining') ?></div><div class="h-value" style="font-size:22px;color:var(--gold)"><?= money($util) ?></div></div>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Pay Salary') ?><span class="muted" style="font-size:11px;font-weight:400"><?= __('full or partial') ?></span></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
    <div class="big-fields">
      <div class="big-field span-2"><label><?= __('Amount (Rs) *') ?></label><input class="f" name="amount" type="number" step="0.01" min="0.01" max="<?= e((string)$util) ?>" required placeholder="<?= e((string)($util > 0 ? $util : $remaining)) ?>"></div>
      <div class="big-field"><label><?= __('Method') ?></label><select class="f" name="method"><?php $typeMap = ['cash' => 'Cash', 'bank' => 'Bank']; foreach ($typeMap as $k => $v): ?><option value="<?= $k ?>"><?= __($v) ?></option><?php endforeach; ?></select></div>
      <div class="big-field span-2"><label><?= __('Pay From') ?></label><select class="f" name="account_id" required><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
      <div class="big-field span-2"><label><?= __('Period (YYYY-MM)') ?></label><input class="f" name="period" type="month" value="<?= date('Y-m') ?>"></div>
      <div class="span-4"><label><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= __('e.g. November salary') ?>"></div>
    </div>
    <button class="btn btn-primary btn-xl mt" type="submit"><?= __('Pay Salary') ?></button>
    </form>
  </div>

  <div class="panel" id="advance">
    <h2><?= __('Advance Payment') ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="advance">
    <div class="big-fields">
      <div class="big-field span-2"><label><?= __('Amount (Rs) *') ?></label><input class="f" name="amount" type="number" step="0.01" min="0.01" required placeholder="5000"></div>
      <div class="big-field"><label><?= __('Method') ?></label><select class="f" name="method"><option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option></select></div>
      <div class="big-field span-2"><label><?= __('Pay From') ?></label><select class="f" name="account_id" required><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
      <div class="span-4"><label><?= __('Reason *') ?></label><input class="f" name="notes" required placeholder="<?= __('e.g. personal emergency') ?>"></div>
    </div>
    <p class="help"><?= __('Advance deducts automatically from future salary (net never goes negative).') ?></p>
    <button class="btn btn-outline btn-xl mt" type="submit"><?= __('Give Advance') ?></button>
    </form>
  </div>
</div>

<div class="panel" style="margin-top:14px">
  <h2><?= __('Salary History') ?><span class="muted" style="font-size:11px;font-weight:400"><?= __('pay slips, full or partial') ?></span></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Period') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Method') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($salaryPayments as $p): ?><tr><td><?= date_display($p['date']) ?></td><td><?= e($p['period'] ?? '—') ?></td><td class="num"><?= money($p['amount']) ?></td><td><?= e(strtoupper($p['method'])) ?></td><td><span class="badge <?= $p['status'] === 'paid' ? 'green' : ($p['status'] === 'pending' ? 'amber' : 'gray') ?>"><?= e(strtoupper($p['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$salaryPayments): ?><tr><td colspan="5" class="muted"><?= __('No salary payments yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>

<div class="panel" style="margin-top:14px">
  <h2><?= __('Advance History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Amount') ?></th><th class="num"><?= __('Deducted') ?></th><th class="num"><?= __('Outstanding') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($advances as $a): ?><tr><td><?= date_display($a['date']) ?></td><td class="num"><?= money($a['amount']) ?></td><td class="num"><?= money($a['deducted']) ?></td><td class="num"><?= money(max(0, (float)$a['amount'] - (float)$a['deducted'])) ?></td><td><span class="badge <?= $a['status'] === 'paid' ? 'blue' : ($a['status'] === 'pending' ? 'amber' : 'gray') ?>"><?= e(strtoupper($a['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$advances): ?><tr><td colspan="5" class="muted"><?= __('No advances yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>
