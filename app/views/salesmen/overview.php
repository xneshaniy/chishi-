<?php $title = __('Complete Overview — ') . e($sm['name']); $tab = 'overview'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<?php $printq = 'from=' . $from . '&to=' . $to; ?>
<div class="simple-hero" style="padding:14px 20px;margin-bottom:12px;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Complete Overview — ') ?><?= e($sm['name']) ?></span><br><small><?= __('Complete live financial and stock position — no separate reports needed. Everything derives from the same transactions.') ?></small></div>
  <a class="btn btn-dark" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/overview/print', ['from' => $from, 'to' => $to])) ?>" target="_blank">🖨 <?= __('Print / Save as PDF') ?></a>
</div>

<!-- STATUS BANNER -->
<div style="background:<?= $stats['outstanding'] > 0 ? '#fff5f5' : '#f2fbf5' ?>;border:1px solid <?= $stats['outstanding'] > 0 ? '#f0c6c6' : '#bfe3cd' ?>;border-radius:16px;padding:18px 22px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap">
  <div>
    <div class="help" style="font-weight:800;letter-spacing:.08em;color:var(--charcoal)"><?= __('COMPLETE POSITION') ?></div>
    <div style="font-size:34px;font-weight:800;color:<?= $stats['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' ?>;line-height:1.15">
      <?= $stats['outstanding'] > 0 ? money($stats['outstanding'], false) : __('Fully Settled') ?>
    </div>
    <div class="muted" style="font-size:13px"><?= $stats['outstanding'] > 0 ? __('Salesman owes the company this amount (goods payable).') : __('Goods and cash accounts reconcile. Nothing is owed.') ?></div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?php if ($stats['pending'] > 0): ?><span class="badge amber" style="font-size:15px;padding:8px 16px"><?= __('Pending Approval:') ?><?= (int)$stats['pending'] ?></span><?php endif; ?>
    <span class="badge <?= $stats['outstanding'] > 0 ? 'red' : 'green' ?>" style="font-size:15px;padding:8px 16px"><?= $stats['outstanding'] > 0 ? __('Amount Outstanding') : __('Fully Settled') ?></span>
  </div>
</div>

<!-- GOODS & STOCK -->
<h2 style="margin:4px 0 8px"><?= __('Goods & Stock') ?></h2>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Goods Issued') ?></div><div class="sc-val"><?= money($stats['goodsIssued']) ?></div><div class="sc-sub"><?= qty($stats['unitsIssued']) ?> <?= __('Packets · posted issues only') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Paid Goods Value') ?></div><div class="sc-val"><?= money($stats['goodsIssued']) ?></div><div class="sc-sub"><?= __('line-payable on paid quantity') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bonus / Free Goods') ?></div><div class="sc-val"><?= qty($stats['bonusQty']) ?></div><div class="sc-sub"><?= __('free value') ?> <?= money($stats['bonusValue']) ?> — <?= __('never charged') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Returned') ?></div><div class="sc-val"><?= qty($stats['retGoodUnits']) ?></div><div class="sc-sub"><?= __('accepted') ?> <?= money($stats['retValue']) ?> · <?= qty($stats['retDamagedUnits']) ?> <?= __('damaged') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Stock With Salesman') ?></div><div class="sc-val"><?= qty($stats['stockUnits']) ?></div><div class="sc-sub"><?= __('Packets · value') ?> <?= money($stats['stockValue']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Goods Payable') ?></div><div class="sc-val" style="color:<?= $stats['goodsPayable'] > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money($stats['goodsPayable']) ?></div><div class="sc-sub"><?= __('salesman ledger debit − credit') ?></div></div>
</div>

<!-- COLLECTIONS & PAYMENTS -->
<h2 style="margin:4px 0 8px"><?= __('Collections & Payments') ?></h2>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Customer Collections') ?></div><div class="sc-val"><?= money((float)($stats['hisab']['collected'] ?? 0)) ?></div><div class="sc-sub"><?= __('cash') ?> <?= money((float)($stats['hisab']['cash_collected'] ?? 0)) ?> · <?= __('bank') ?> <?= money((float)($stats['hisab']['bank_collected'] ?? 0)) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash Received By Company') ?></div><div class="sc-val"><?= money($stats['handCash']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank Received By Company') ?></div><div class="sc-val"><?= money($stats['handBank']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Money Handed Over') ?></div><div class="sc-val"><?= money($stats['handTotal']) ?></div><div class="sc-sub"><?= __('all-time, cash + bank') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Remaining Payable To Company') ?></div><div class="sc-val" style="color:<?= $stats['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money($stats['outstanding']) ?></div><div class="sc-sub"><?= __('live — from actual posted transactions') ?></div></div>
</div>

<!-- EXPENSES -->
<h2 style="margin:4px 0 8px"><?= __('Expenses (approved)') ?></h2>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Vehicle / Petrol·Diesel') ?></div><div class="sc-val"><?= money($stats['expVehicle']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Food / Meals') ?></div><div class="sc-val"><?= money($stats['expFood']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Other Expenses') ?></div><div class="sc-val"><?= money($stats['expOther']) ?></div></div>
  <div class="stat-chip" style="border-color:#bfe3cd"><div class="sc-lbl"><?= __('Total Approved Expenses') ?></div><div class="sc-val" style="color:#0a4d29"><?= money($stats['expTotal']) ?></div><div class="sc-sub"><?= __('includes paid from collection + company-paid') ?></div></div>
</div>

<!-- SALARY & ADVANCES -->
<h2 style="margin:4px 0 8px"><?= __('Salary & Advances (separate from goods)') ?></h2>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Earned (to date)') ?></div><div class="sc-val"><?= money($stats['salaryEarned']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Paid') ?></div><div class="sc-val"><?= money($stats['salaryPaid']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Remaining') ?></div><div class="sc-val"><?= money($stats['salaryRemaining']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advances Received') ?></div><div class="sc-val"><?= money($stats['advGiven']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Recovered') ?></div><div class="sc-val"><?= money($stats['advRecovered']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($stats['advOutstanding']) ?></div></div>
</div>

<!-- CLAIMS -->
<h2 style="margin:4px 0 8px"><?= __('Claims') ?></h2>
<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Customer Claims (his customers)') ?></h2>
    <div class="stat-strip" style="grid-template-columns:1fr 1fr;margin:0">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Total Claims') ?></div><div class="sc-val"><?= (int)$stats['custClaims']['total'] ?></div><div class="sc-sub"><?= money($stats['custClaims']['amount']) ?> <?= __('claimed') ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Approved / Pending / Rejected') ?></div><div class="sc-val" style="font-size:17px"><?= (int)$stats['custClaims']['approved'] ?> / <?= (int)$stats['custClaims']['pending'] ?> / <?= (int)$stats['custClaims']['rejected'] ?></div></div>
    </div>
  </div>
  <div class="panel">
    <h2><?= __('Supplier Claim Goods (company ledger)') ?></h2>
    <div class="stat-strip" style="grid-template-columns:1fr 1fr;margin:0">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Accepted / Damaged / Rejected') ?></div><div class="sc-val" style="font-size:17px"><?= qty($stats['supplierCG']['good_qty']) ?> / <?= qty($stats['supplierCG']['damaged_qty']) ?> / <?= qty($stats['supplierCG']['rejected_qty']) ?></div><div class="sc-sub"><?= (int)$stats['supplierCG']['total'] ?> <?= __('claim records') ?> (<?= (int)$stats['supplierCG']['approved'] ?> <?= __('approved') ?>)</div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Approved Claim Amount') ?></div><div class="sc-val"><?= money($stats['supplierCG']['claim_amount']) ?></div><div class="sc-sub"><?= __('paid') ?> <?= money($stats['supplierCG']['cash_paid']) ?> · <?= __('outstanding') ?> <?= money(max(0, $stats['supplierCG']['claim_amount'] - $stats['supplierCG']['cash_paid'])) ?></div></div>
    </div>
  </div>
</div>

<!-- TRANSACTION HISTORY -->
<form method="get" class="toolbar" style="margin-bottom:8px">
  <input type="hidden" name="r" value="salesmen/<?= (int)$sm['id'] ?>/overview">
  <label><?= __('From') ?> <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label><?= __('To') ?> <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label><?= __('Type') ?>
    <select name="type">
      <option value="all" <?= ($type ?? 'all') === 'all' ? 'selected' : '' ?>><?= __('All activity') ?></option>
      <option value="issue" <?= ($type ?? '') === 'issue' ? 'selected' : '' ?>><?= __('Goods Issue') ?></option>
      <option value="return" <?= ($type ?? '') === 'return' ? 'selected' : '' ?>><?= __('Goods Returns') ?></option>
      <option value="collection" <?= ($type ?? '') === 'collection' ? 'selected' : '' ?>><?= __('Collections') ?></option>
      <option value="handover" <?= ($type ?? '') === 'handover' ? 'selected' : '' ?>><?= __('Handovers') ?></option>
      <option value="expense" <?= ($type ?? '') === 'expense' ? 'selected' : '' ?>><?= __('Expenses') ?></option>
      <option value="salary" <?= ($type ?? '') === 'salary' ? 'selected' : '' ?>><?= __('Salary') ?></option>
      <option value="advance" <?= ($type ?? '') === 'advance' ? 'selected' : '' ?>><?= __('Advances') ?></option>
      <option value="settlement" <?= ($type ?? '') === 'settlement' ? 'selected' : '' ?>><?= __('Settlements') ?></option>
    </select>
  </label>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/overview')) ?>"><?= __('Reset') ?></a>
</form>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));margin-bottom:8px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Issued (range)') ?></div><div class="sc-val"><?= money($summary['issue']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Returned (range)') ?></div><div class="sc-val"><?= money($summary['returns']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash In (range)') ?></div><div class="sc-val"><?= money($summary['cash']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank In (range)') ?></div><div class="sc-val"><?= money($summary['bank']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Expenses (range)') ?></div><div class="sc-val"><?= money($summary['expense']) ?></div></div>
</div>

<div class="panel">
  <h2><?= __('Transaction History') ?> <span class="muted" style="font-size:12px"><?= e($from) ?> → <?= e($to) ?></span></h2>
  <?php if (!$events): ?><p class="muted"><?= __('No transactions in this range.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl" style="font-size:13px"><thead><tr><th><?= __('Date / Time') ?></th><th><?= __('Type') ?></th><th><?= __('Description / Reference') ?></th><th class="num"><?= __('Issue') ?></th><th class="num"><?= __('Returns') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th class="num"><?= __('Expense') ?></th><th class="num"><?= __('Balance') ?></th></tr></thead>
    <tbody><?php foreach ($events as $e): ?><tr>
      <td><div><?= date_display($e['date']) ?></div><span class="muted" style="font-size:11px"><?= e(substr((string)$e['ts'], 11, 5)) ?></span></td>
      <td><span class="badge gray"><?= e(strtoupper($e['type'])) ?></span></td>
      <td><div><?= e($e['descr']) ?><?= $e['ref'] ? '<br><span class="mono muted">' . e($e['ref']) . '</span>' : '' ?></div></td>
      <td class="num"><?= $e['issue'] ? money($e['issue']) : '—' ?></td>
      <td class="num"><?= $e['returns'] ? money($e['returns']) : '—' ?></td>
      <td class="num"><?= $e['cash'] ? money($e['cash']) : '—' ?></td>
      <td class="num"><?= $e['bank'] ? money($e['bank']) : '—' ?></td>
      <td class="num"><?= $e['expense'] ? money($e['expense']) : '—' ?></td>
      <td class="num"><?= $e['balance'] !== null ? money($e['balance']) : '—' ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
