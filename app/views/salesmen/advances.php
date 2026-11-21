<?php
$title = __('Advances') . ' — ' . e($sm['name']);
$tab = 'advances';
require __DIR__ . '/../partials/sm_tabs.php';
$balanceAfter = max(0, (float)($stats['outstanding'] ?? 0));
?>

<div class="simple-hero no-print" style="padding:14px 18px;margin-bottom:12px">
  <div class="greet" style="display:flex;gap:14px;align-items:center">
    <?php if (!empty($sm['photo_path'])): ?>
      <img class="emp-photo" src="<?= e(photo_data_uri($sm['photo_path'])) ?>" alt="<?= e($sm['name']) ?>" style="width:64px;height:64px;margin:0">
    <?php else: ?>
      <div class="emp-avatar" style="width:64px;height:64px;font-size:24px;margin:0"><?= e(mb_strtoupper(mb_substr($sm['name'], 0, 1))) ?></div>
    <?php endif; ?>
    <div>
      <span style="font-size:22px;font-weight:800"><?= e($sm['name']) ?></span><br>
      <small><?= e($sm['code'] ?? '') ?> · <?= __('Salary Advance Management') ?><?= !empty($sm['salary']) ? ' · ' . __('Monthly Salary:') . ' ' . money($sm['salary']) : '' ?></small>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Requested') ?></div><div class="sc-val"><?= money($stats['total_requested'] ?? 0) ?></div><div class="sc-sub"><?= __('all requests') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Paid') ?></div><div class="sc-val"><?= money($stats['total_given'] ?? 0) ?></div><div class="sc-sub"><?= __('disbursed') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Recovered') ?></div><div class="sc-val"><?= money($stats['total_recovered'] ?? 0) ?></div><div class="sc-sub"><?= __('adjusted from salary') ?></div></div>
  <div class="stat-chip" style="border-color:<?= $balanceAfter > 0 ? '#f2b8b5' : '#cfe8d5' ?>">
    <div class="sc-lbl"><?= __('Outstanding Advance') ?></div>
    <div class="sc-val" style="color:<?= $balanceAfter > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money($balanceAfter) ?></div>
    <div class="sc-sub"><?= $balanceAfter > 0 ? __('still to recover') : __('cleared') ?></div>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <h2><?= __('Give / Request Advance') ?></h2>
    <form method="post" action="<?= e(url('salesmen/' . (int)$id . '/advances')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div>
          <label class="f"><?= __('Advance Date') ?></label>
          <input class="f" type="date" name="date" value="<?= e(today()) ?>" required>
        </div>
        <div>
          <label class="f"><?= __('Amount (Rs) *') ?></label>
          <input class="f" type="number" step="0.01" min="0.01" name="amount" required placeholder="5000">
        </div>
        <div>
          <label class="f"><?= __('Payment Method') ?></label>
          <select class="f" name="method">
            <option value="cash"><?= __('Cash') ?></option>
            <option value="bank"><?= __('Bank') ?></option>
          </select>
        </div>
        <div>
          <label class="f"><?= __('Pay Out From *') ?></label>
          <select class="f" name="account_id" required>
            <option value=""><?= __('— select account —') ?></option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="span-2">
          <label class="f"><?= __('Reason / Notes *') ?></label>
          <input class="f" name="notes" required placeholder="<?= e(__('e.g. medical emergency / travel advance')) ?>">
        </div>
      </div>
      <p class="help" style="margin-top:10px">
        <?= __('Salary advances are adjusted automatically against monthly salary payouts and are never deducted from goods payable.') ?>
      </p>
      <button class="btn btn-primary btn-xl mt" type="submit">
        <?= is_owner_or_admin() ? __('Give Advance (Disburse & Post)') : __('Request Advance (Submit for Approval)') ?>
      </button>
    </form>
  </div>

  <div class="panel">
    <h2><?= __('Advance Policy & Vouchers') ?></h2>
    <div class="form-grid">
      <div><div class="f-lbl"><?= __('Salesman Code') ?></div><div class="mono"><?= e($sm['code']) ?></div></div>
      <div><div class="f-lbl"><?= __('Contact Number') ?></div><div><?= e($sm['phone'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Monthly Salary') ?></div><div><?= money($monthly) ?></div></div>
      <div><div class="f-lbl"><?= __('Total Advances Taken') ?></div><div><?= money($stats['total_given'] ?? 0) ?></div></div>
      <div class="span-2"><div class="f-lbl"><?= __('Current Outstanding Balance') ?></div><div style="font-size:18px;font-weight:800;color:<?= $balanceAfter > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money($balanceAfter) ?></div></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
      <a class="btn btn-dark" href="<?= e(url('advances/salesman/' . (int)$id . '/voucher')) ?>" target="_blank">🖨 <?= __('Print Advance Voucher') ?></a>
      <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$id . '/salary')) ?>"><?= __('Salary Payouts →') ?></a>
      <a class="btn btn-outline" href="<?= e(url('advances')) ?>"><?= __('All Staff Advances →') ?></a>
    </div>
  </div>
</div>

<div class="panel" style="margin-top:14px">
  <h2><?= __('Advance History') ?></h2>
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th><?= __('Date') ?></th>
          <th class="num"><?= __('Amount') ?></th>
          <th class="num"><?= __('Deducted') ?></th>
          <th class="num"><?= __('Outstanding') ?></th>
          <th><?= __('Paid From Account') ?></th>
          <th><?= __('Reason / Notes') ?></th>
          <th><?= __('Status') ?></th>
          <th><?= __('Print') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($advances as $a): 
          $rem = max(0, (float)$a['amount'] - (float)$a['deducted']);
          $st = strtolower($a['status'] ?? 'pending');
        ?>
        <tr>
          <td><?= date_display($a['date']) ?></td>
          <td class="num" style="font-weight:700"><?= money($a['amount']) ?></td>
          <td class="num"><?= money($a['deducted']) ?></td>
          <td class="num" style="color:<?= $rem > 0 ? '#8f1d13' : '#1d6530' ?>;font-weight:700"><?= money($rem) ?></td>
          <td><?= e($a['account_name'] ?? '—') ?> <span class="muted"><?= !empty($a['method']) ? '(' . strtoupper(e($a['method'])) . ')' : '' ?></span></td>
          <td><?= e($a['reason'] ?: '—') ?></td>
          <td>
            <span class="badge <?= $st === 'paid' || $st === 'approved' ? 'green' : ($st === 'pending' ? 'amber' : 'red') ?>">
              <?= e(strtoupper($st === 'paid' ? 'DISBURSED' : $st)) ?>
            </span>
          </td>
          <td>
            <a class="btn btn-outline btn-sm" href="<?= e(url('advances/salesman/' . (int)$id . '/voucher?aid=' . (int)$a['id'])) ?>" target="_blank"><?= __('Voucher') ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$advances): ?>
          <tr><td colspan="8" class="muted"><?= __('No advance records found for this salesman.') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
