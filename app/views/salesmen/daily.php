<?php $title = __('Daily Settlement — ') . e($sm['name']); $tab = 'daily'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<div class="simple-hero" style="padding:14px 20px;margin-bottom:12px">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Daily Settlement — ') ?><?= e($sm['name']) ?></span><br><small><?= __('Goods issued, returns, collections, handovers and approved expenses for the day — everything live from transactions.') ?></small></div>
  <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="r" value="salesmen/<?= (int)$sm['id'] ?>/daily">
    <button class="btn btn-outline btn-sm" type="submit" name="date" value="<?= e(date('Y-m-d', strtotime($date . ' -1 day'))) ?>">←</button>
    <input class="f" type="date" name="date" id="dayPicker" value="<?= e($date) ?>" style="max-width:170px">
    <button class="btn btn-outline btn-sm" type="submit" name="date" value="<?= e(date('Y-m-d', strtotime($date . ' +1 day'))) ?>">→</button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/daily')) ?>"><?= __('Today') ?></a>
  </form>
</div>

<!-- Status strip -->
<div class="stat-strip" style="grid-template-columns:1.2fr repeat(4,1fr);margin-bottom:12px">
  <div class="stat-chip" style="border-color:<?= $summary['outstanding'] > 0 ? '#f5c6c6' : '#bfe3cd' ?>">
    <div class="sc-lbl"><?= __('Remaining — Salesman Must Pay Company') ?></div>
    <div class="sc-val" style="color:<?= $summary['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' ?>;font-size:26px"><?= money(abs($summary['outstanding']), false) ?></div>
    <div class="sc-sub"><?= $summary['outstanding'] > 0 ? __('outstanding as of now (all-time)') : __('nothing outstanding — accounts reconciled') ?></div>
  </div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Issue Value') ?></div><div class="sc-val"><?= money($summary['issueValue']) ?></div><div class="sc-sub"><?= qty($summary['issueUnits']) ?> <?= __('Packets issued today') ?> <?= $summary['issueBonusQty'] > 0 ? '(+ ' . qty($summary['issueBonusQty']) . ' ' . __('bonus Packets') . ')' : '' ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Collections Today') ?></div><div class="sc-val"><?= money($summary['collTotal']) ?></div><div class="sc-sub"><?= __('cash') ?> <?= money($summary['collCash']) ?> · <?= __('bank') ?> <?= money($summary['collBank']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Handed Over Today') ?></div><div class="sc-val"><?= money($summary['handCash'] + $summary['handBank']) ?></div><div class="sc-sub"><?= __('cash') ?> <?= money($summary['handCash']) ?> · <?= __('bank') ?> <?= money($summary['handBank']) ?></div></div>
  <div class="stat-chip" style="border-color:<?= $summary['pending'] > 0 ? '#f3ddb8' : 'var(--line)' ?>"><div class="sc-lbl"><?= __('Approvals Pending') ?></div><div class="sc-val" style="color:<?= $summary['pending'] > 0 ? 'var(--amber, #b4530a)' : 'var(--green)' ?>"><?= (int)$summary['pending'] ?></div><div class="sc-sub"><?= $summary['pending'] > 0 ? __('issues/expenses awaiting approval') : __('no pending approvals') ?></div></div>
</div>
<div style="margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  <?php
    $chip = ['idle' => ['gray', __('Settled / no activity')], 'settled' => ['green', __('Fully Settled')], 'partial' => ['amber', __('Partially Settled')], 'outstanding' => ['red', __('Amount Outstanding')], 'pending' => ['amber', __('Pending Approval')]][$summary['status']] ?? ['gray', __('Settled')];
  ?>
  <span class="badge <?= $chip[0] ?>" style="font-size:15px;padding:8px 18px"><?= e($chip[1]) ?><?= $summary['status'] === 'outstanding' ? ' — Rs ' . number_format($summary['outstanding'], 2) : '' ?></span>
  <span class="muted" style="font-size:12px"><?= __('Status reflects actual posted transactions, not typed-in figures.') ?></span>
</div>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Goods Issued Today') ?></h2>
    <?php if (!$issues): ?><p class="muted"><?= __('No goods issued on this date.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Time') ?></th><th><?= __('Issue No.') ?></th><th class="num"><?= __('Packets') ?></th><th class="num"><?= __('Value (Rs)') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($issues as $i): ?><tr><td><?= e(substr((string)$i['created_at'], 11, 5)) ?></td><td class="mono"><?= e($i['issue_no']) ?></td><td class="num"><?= qty($i['units']) ?></td><td class="num"><?= money($i['value']) ?></td><td><?= $i['status'] === 'posted' ? '<span class="badge green">POSTED</span>' : '<span class="badge amber">' . e(strtoupper($i['status'])) . '</span>' ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><th colspan="2"><?= __('Total') ?></th><th class="num"><?= qty($summary['issueUnits']) ?></th><th class="num"><?= money($summary['issueValue'], false) ?></th><th></th></tr></tfoot></table></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2><?= __('Goods Returns Accepted Today') ?></h2>
    <div class="stat-strip" style="grid-template-columns:1fr 1fr;margin:0 0 10px">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Goods returned') ?></div><div class="sc-val"><?= qty($summary['retGoodUnits']) ?></div><div class="sc-sub"><?= __('Packets accepted') ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Credit for returns') ?></div><div class="sc-val"><?= money($summary['retValue']) ?></div><div class="sc-sub"><?= qty($summary['retDamagedUnits']) ?> <?= __('Packets damaged (written off)') ?></div></div>
    </div>
    <?php if (!$returnsList): ?><p class="muted"><?= __('No returns on this date.') ?></p><?php else: ?><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Time') ?></th><th><?= __('Return No.') ?></th><th class="num"><?= __('Packets') ?></th><th class="num"><?= __('Credit (Rs)') ?></th></tr></thead>
      <tbody><?php foreach ($returnsList as $r): ?><tr><td><?= e(substr((string)$r['created_at'], 11, 5)) ?></td><td class="mono"><?= e($r['issue_no']) ?></td><td class="num"><?= qty($r['units']) ?></td><td class="num"><?= money($r['value']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
</div>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Customer Collections Today') ?></h2>
    <?php if (!$collections): ?><p class="muted"><?= __('No collections recorded on this date.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Time') ?></th><th><?= __('Customer') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th class="num"><?= __('Total') ?></th></tr></thead>
        <tbody><?php foreach ($collections as $c): ?><tr><td><?= e(substr((string)$c['created_at'], 11, 5)) ?></td><td><?= e($c['customer_name'] ?: '—') ?></td><td class="num"><?= money($c['cash_amount']) ?></td><td class="num"><?= money($c['bank_amount']) ?></td><td class="num"><?= money($c['amount']) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><th colspan="2"><?= __('Total') ?></th><th class="num"><?= money($summary['collCash'], false) ?></th><th class="num"><?= money($summary['collBank'], false) ?></th><th class="num"><?= money($summary['collTotal'], false) ?></th></tr></tfoot></table></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2><?= __('Handover to Company Today') ?></h2>
    <?php if (!$payments): ?><p class="muted"><?= __('No handover recorded on this date. Use the Payments tab to record money received.') ?></p>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/recovery')) ?>"><?= __('Record Handover →') ?></a><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Time') ?></th><th><?= __('Receipt') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th><?= __('Account') ?></th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?><tr><td><?= e(substr((string)$p['created_at'], 11, 5)) ?></td><td class="mono"><?= e($p['receipt_no'] ?: ('SP-' . $p['id'])) ?></td><td class="num"><?= money($p['cash_amount']) ?></td><td class="num"><?= money($p['bank_amount']) ?></td><td><?= e($p['account_name'] ?? '—') ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><th colspan="2"><?= __('Total') ?></th><th class="num"><?= money($summary['handCash'], false) ?></th><th class="num"><?= money($summary['handBank'], false) ?></th><th></th></tr></tfoot></table></div>
    <?php endif; ?>
    <div class="stat-strip" style="grid-template-columns:repeat(3,1fr);margin:10px 0 0">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Vehicle / Petrol·Diesel') ?></div><div class="sc-val"><?= money($summary['expVehicle']) ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Food / Meals') ?></div><div class="sc-val"><?= money($summary['expFood']) ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Other Expenses') ?></div><div class="sc-val"><?= money($summary['expOther']) ?></div></div>
    </div>
    <p class="help"><?= __('Approved expenses paid from collected money on this date:') ?> <?= money($summary['expCollTotal']) ?> <?= __('(company-paid expenses excluded).') ?></p>
  </div>
</div>

<!-- Evening Settlement Entry -->
<div class="panel" style="margin-bottom:14px">
  <h2><?= __('Evening Settlement Entry') ?></h2>
  <p class="help"><?= __('Sign-off for') ?> <strong><?= date_display($date) ?></strong> — <?= __('saves a settlement receipt (DS-…) and prints it. All values pull from posted transactions; nothing is re-typed.') ?></p>
  <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/daily', ['date' => $date])) ?>" class="form-grid"><?= csrf_field() ?>
    <div class="span-2"><label class="f"><?= __('Salesman (auto)') ?></label><input class="f" value="<?= e($sm['name'] . ' (' . $sm['code'] . ')') ?>" readonly></div>
    <div><label class="f"><?= __('Settlement Date') ?></label><input class="f" type="date" name="settle_date" value="<?= e($date) ?>" readonly></div>
    <div><label class="f"><?= __('Received By') ?></label><input class="f" value="<?= e(($_SESSION['user_name'] ?? ($_SESSION['username'] ?? __('Current user')))) ?>" readonly></div>
    <div><label class="f"><?= __('Total Collected From Customers') ?></label><input class="f" value="<?= e(money($summary['collTotal'], false)) ?>" readonly></div>
    <div><label class="f"><?= __('Cash Received By Company') ?></label><input class="f" value="<?= e(money($summary['handCash'], false)) ?>" readonly></div>
    <div><label class="f"><?= __('Bank Amount Received') ?></label><input class="f" value="<?= e(money($summary['handBank'], false)) ?>" readonly></div>
    <div><label class="f"><?= __('Bank Account') ?></label><input class="f" value="<?= e($payments ? implode(', ', array_unique(array_column($payments, 'account_name'))) : '—') ?>" readonly></div>
    <div><label class="f"><?= __('Approved Vehicle / Petrol·Diesel') ?></label><input class="f" value="<?= e(money($summary['expVehicle'], false)) ?>" readonly></div>
    <div><label class="f"><?= __('Approved Food') ?></label><input class="f" value="<?= e(money($summary['expFood'], false)) ?>" readonly></div>
    <div><label class="f"><?= __('Other Approved Expenses') ?></label><input class="f" value="<?= e(money($summary['expOther'], false)) ?>" readonly></div>
    <div class="span-2"><label class="f"><?= __('Remaining Payable To Company — all-time') ?></label><input class="f" id="remainingPayable" value="<?= e(money($summary['outstanding'], false)) ?>" readonly style="font-weight:800;color:<?= $summary['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' ?>"></div>
    <div><label class="f"><?= __('Counted Cash (optional)') ?></label><input class="f" type="number" step="0.01" min="0" name="cash_counted" id="countedCash" value="<?= e(number_format($summary['handCash'], 2, '.', '')) ?>"></div>
    <div><label class="f"><?= __('Variance vs Posted Handover') ?></label><input class="f" id="cashVariance" readonly value="0.00"></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. evening return, verified by manager')) ?>"></div>
    <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Save Settlement & Print Receipt') ?></button></div>
  </form>
</div>

<!-- Previous settlements -->
<div class="panel">
  <h2><?= __('Previous Settlements') ?></h2>
  <?php if (!$prevSettlements): ?><p class="muted"><?= __('No settlement signed off yet. The first one, once saved, will appear here with a printable receipt.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th class="num"><?= __('Issued') ?></th><th class="num"><?= __('Handed Over') ?></th><th class="num"><?= __('Variance') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Received By') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($prevSettlements as $s): ?><tr>
        <td class="mono"><?= e($s['reference'] ?: ('DS-' . $s['id'])) ?></td>
        <td><?= date_display($s['date']) ?></td>
        <td class="num"><?= money($s['issued_value']) ?></td>
        <td class="num"><?= money((float)$s['cash_received'] + (float)$s['bank_received']) ?></td>
        <td class="num" style="color:<?= (float)$s['variance'] < 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money($s['variance']) ?></td>
        <td class="num"><?= money($s['remaining_payable']) ?></td>
        <td><?= e($s['received_full_name'] ?: ($s['username'] ?? '—')) ?></td>
        <td><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/daily/print/' . (int)$s['id'])) ?>">🖨</a></td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var counted = document.getElementById('countedCash'), variance = document.getElementById('cashVariance');
  var expectedCash = <?= json_encode(round($summary['handCash'], 2)) ?>;
  function sync() {
    var c = parseFloat(counted.value || '0') || 0;
    var diff = Math.round((c - expectedCash) * 100) / 100;
    variance.value = (diff >= 0 ? '+' : '') + diff.toFixed(2);
    variance.style.color = diff >= 0 ? '#1d6530' : '#8f1d13';
  }
  counted.addEventListener('input', sync);
  sync();
})();
</script>
