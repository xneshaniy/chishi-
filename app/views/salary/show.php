<?php
$title = 'Salary — ' . e($p['name']);
$imgSrc = !empty($p['photo_path']) ? photo_data_uri($p['photo_path']) : null;
$isSalesman = $p['person_type'] === 'salesman';
$typeLabel = PeopleDirectory::kindLabel($p['kind']);
?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:10px">
  <div class="greet" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <div style="text-align:center;min-width:110px">
      <?php if ($imgSrc): ?><img class="profile-photo" src="<?= e($imgSrc) ?>" alt="<?= e($p['name']) ?>" style="width:110px;height:110px;border-radius:50%;object-fit:cover"><?php else: ?><div class="profile-avatar" style="width:110px;height:110px;border-radius:50%;font-size:40px"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></div><?php endif; ?>
      <div style="margin-top:6px"><span class="badge <?= $p['kind'] === 'salesman' ? 'blue' : ($p['kind'] === 'labour' ? 'amber' : 'green') ?>"><?= e(strtoupper($typeLabel)) ?></span></div>
    </div>
    <div style="flex:1">
      <span style="font-size:22px;font-weight:800"><?= e($p['name']) ?></span><br>
      <small><?= e($p['code']) ?> · <?= e($p['designation']) ?><?= $p['join_date'] ? ' · ' . __('Joined') . ' ' . date_display($p['join_date']) : '' ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></small>
    </div>
    <div class="btn-group" style="flex-wrap:wrap;gap:6px;justify-content:flex-end">
      <?php if (!$isSalesman): ?>
        <?php if (Auth::can('employees.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('employees/' . (int)$p['id'] . '?tab=salary')) ?>"><?= __('Edit Salary') ?></a><?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$p['id'] . '/ledger')) ?>"><?= __('Full Salary Ledger') ?></a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$p['id'] . '/advance')) ?>"><?= __('Request Advance') ?></a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('salesmen/' . (int)$p['id'] . '/salary')) ?>"><?= __('Pay / Give Advance') ?></a>
      <?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salary')) ?>"><?= __('All People') ?></a>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <div class="stat-chip"><div class="sc-lbl"><?= e(ucfirst($stats['type'])) . ' ' . __('Salary') ?></div><div class="sc-val"><?= money($stats['rate']) ?></div><div class="sc-sub"><?= ucfirst($stats['type']) ?> <?= __('rate') ?><?= $month ? ' · ' . e(date_display($month . '-01')) : '' ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Earned') ?></div><div class="sc-val"><?= money($stats['earned']) ?></div><div class="sc-sub"><?= $month ? __('this month (posted payslips)') : __('months since joining') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Paid') ?></div><div class="sc-val"><?= money($stats['paid']) ?></div><div class="sc-sub"><?= $month ? __('paid this month') : __('all-time') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Remaining Balance') ?></div><div class="sc-val" style="color:#8f1d13"><?= money(max(0, $stats['remaining'])) ?></div><div class="sc-sub"><span class="badge <?= $stats['status'] === 'settled' ? 'green' : ($stats['status'] === 'partial' ? 'amber' : 'red') ?>"><?= e(strtoupper(str_replace('_', ' ', $stats['status']))) ?></span></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($stats['advances_out']) ?></div><div class="sc-sub"><?= __('not yet recovered') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Present') ?></div><div class="sc-val"><?= (int)$attendance['present'] ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Half Days') ?></div><div class="sc-val"><?= (int)$attendance['half_day'] ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Absent') ?></div><div class="sc-val" style="color:#8f1d13"><?= (int)$attendance['absent'] ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
</div>

<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0"><?= __('Payment History') ?></h2>
      <?php if ($isSalesman): ?><a class="btn btn-dark btn-sm" href="<?= e(url('salesmen/' . (int)$p['id'] . '/salary')) ?>">+ <?= __('Pay / Advance') ?></a><?php else: ?><a class="btn btn-dark btn-sm" href="<?= e(url('payroll')) ?>">+ <?= __('Pay via Payroll') ?></a><?php endif; ?>
    </div>
    <?php if (!$payments): ?><p class="muted mt"><?= __('No salary payments recorded yet.') ?></p><?php else: ?>
      <div class="table-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Period') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th><th><?= __('Account') ?></th></tr></thead>
        <tbody><?php foreach ($payments as $pay): ?><tr>
          <td><?= date_display($pay['date']) ?></td>
          <td><?= !empty($pay['period_start']) ? date_display($pay['period_start']) . ' → ' . date_display($pay['period_end']) : e($pay['period'] ?: '—') ?></td>
          <td class="num"><?= money($pay['amount']) ?></td>
          <td><span class="badge <?= strtolower($pay['status']) === 'paid' ? 'green' : (strtolower($pay['status']) === 'pending' ? 'amber' : 'red') ?>"><?= e(strtoupper($pay['status'])) ?></span></td>
          <td><?= e($pay['account'] ?: '—') ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0"><?= __('Advances') ?></h2>
      <?php if ($isSalesman): ?><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$p['id'] . '/salary')) ?>"><?= __('Give Advance') ?></a><?php else: ?><a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$p['id'] . '/advance')) ?>"><?= __('Request Advance') ?></a><?php endif; ?>
    </div>
    <?php if (!$advances): ?><p class="muted mt"><?= __('No advances yet.') ?></p><?php else: ?>
      <div class="table-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Amount') ?></th><th class="num"><?= __('Deducted') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($advances as $a): $deducted = (float)($a['deducted'] ?? 0); $outstanding = max(0, (float)$a['amount'] - $deducted); ?>
          <tr><td><?= date_display($a['date']) ?></td><td class="num"><?= money($a['amount']) ?></td><td class="num"><?= money($deducted) ?></td><td><span class="badge <?= ($a['status'] ?? '') === 'disbursed' || ($a['status'] ?? '') === 'paid' ? 'blue' : ($a['status'] === 'pending' ? 'amber' : 'gray') ?>"><?= e(strtoupper($a['status'] ?? 'disbursed')) ?></span></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>

    <h2 style="margin-top:18px"><?= __('Deductions') ?></h2>
    <?php if ($deductions): ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Reason') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
        <tbody><?php foreach ($deductions as $d): ?><tr><td><?= date_display($d['date']) ?></td><td><?= e($d['reason']) ?></td><td class="num"><?= money($d['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted"><?= $isSalesman ? __('Deductions are handled by wage/payroll for employees only.') : __('No deductions yet.') ?></p><?php endif; ?>

    <h2 style="margin-top:18px"><?= __('Bonuses') ?></h2>
    <?php if ($bonusRows): ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Reason') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($bonusRows as $b): ?><tr><td><?= date_display($b['date']) ?></td><td><?= e($b['reason']) ?></td><td class="num"><?= money($b['amount']) ?></td><td><span class="badge <?= $b['status'] === 'posted' ? 'green' : ($b['status'] === 'pending' ? 'amber' : 'red') ?>"><?= e(strtoupper($b['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="muted"><?= $isSalesman ? __('Salesman bonuses are in the Salesman profile → Account / Bonus tabs.') : __('No bonuses yet.') ?></p><?php endif; ?>
  </div>
</div>

<?php if (!$month && $latestPayslip): ?>
<div class="panel" style="margin-top:14px">
  <h2><?= __('Latest Payslip') ?></h2>
  <p class="help"><?= __('Period:') ?> <strong><?= date_display($latestPayslip['period_start']) ?> → <?= date_display($latestPayslip['period_end']) ?></strong> · <?= __('Net Pay:') ?> <strong><?= money($latestPayslip['net_pay']) ?></strong> · <?= __('Paid:') ?> <strong><?= money($latestPayslip['amount_paid']) ?></strong> · <?= __('Status:') ?> <?= e(strtoupper($latestPayslip['status'])) ?></p>
</div>
<?php endif; ?>
