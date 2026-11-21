<?php $title = __('Settlement — ') . e($sm['name']); ?>
<div class="simple-hero" style="padding:16px 20px;margin-bottom:14px;display:flex;gap:16px;justify-content:space-between;align-items:center;flex-wrap:wrap">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Settlement / Hisab — ') ?><?= e($sm['name']) ?></span><br><small><?= __('All figures pull from live transactions. No invented numbers.') ?></small></div>
  <div class="btn-group no-print"><a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/collections')) ?>"><?= __('Record Collection') ?></a><button class="btn btn-dark" type="button" onclick="window.print()">🖨</button></div>
</div>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Salary (kept separate)') ?></h2>
    <div class="stat-strip" style="grid-template-columns:1fr 1fr;margin:0">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Salary earned (to date)') ?></div><div class="sc-val"><?= money($salaryStats['earned']) ?></div><div class="sc-sub"><?= __('months since joining × monthly rate') ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Salary paid (all-time)') ?></div><div class="sc-val"><?= money($salaryStats['paid']) ?></div><div class="sc-sub"><?= __('approved salary payments only') ?></div></div>
    </div>
    <p class="help"><?= __('Salary is never mixed with goods payable or collections. Outstanding salary is handled through Payroll — not through this settlement.') ?></p>
    <a class="btn btn-primary" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/salary')) ?>"><?= __('Open Salary Tab →') ?></a>
    <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/salary')) ?>" style="margin-left:8px"><?= __('Pay Salary / Advance →') ?></a>
  </div>

  <div class="panel">
    <h2><?= __('Goods Payable (kept separate)') ?></h2>
    <div class="stat-strip" style="grid-template-columns:1fr 1fr;margin:0">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Outstanding payable') ?></div><div class="sc-val"><?= money($goodsPayable) ?></div><div class="sc-sub"><?= __('salesman owes for goods taken') ?></div></div>
      <div class="stat-chip"><div class="sc-lbl"><?= __('Paid so far') ?></div><div class="sc-val"><?= money($collections) ?></div><div class="sc-sub"><?= __('paid / remitted against goods') ?></div></div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/issue')) ?>"><?= __('View Goods Issue →') ?></a>
    <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/recovery')) ?>" style="margin-left:8px"><?= __('Pay / Handover →') ?></a>
  </div>
</div>

<div class="panel print-area" style="margin-bottom:14px">
  <h2><?= __('Collections & Handovers (Hisab)') ?></h2>
  <div class="table-wrap"><table class="tbl" style="font-size:15px">
    <thead><tr><th><?= __('Description') ?></th><th class="num"><?= __('Amount (Rs)') ?></th></tr></thead>
    <tbody>
      <tr><td><?= __('Total customer collections') ?><div class="help" style="margin-top:2px"><?= __('From the Collections tab — cash + bank') ?></div></td><td class="num"><?= money($hisab['collected'], false) ?></td></tr>
      <tr><td>Of which cash</td><td class="num"><?= money($hisab['cash_collected'], false) ?></td></tr>
      <tr><td>Of which bank</td><td class="num"><?= money($hisab['bank_collected'], false) ?></td></tr>
      <tr><td><?= __('Cash handed over to company') ?></td><td class="num">-<?= money($hisab['handed_cash'], false) ?></td></tr>
      <tr><td><?= __('Bank deposits / transfers handed over') ?></td><td class="num">-<?= money($hisab['handed_bank'], false) ?></td></tr>
      <tr><td><?= __('Total handed over') ?></td><td class="num"><strong>-<?= money($hisab['handed_total'], false) ?></strong></td></tr>
      <tr><td><?= __('Approved expenses paid from collection') ?><div class="help" style="margin-top:2px"><?= __('Salesman paid a company expense out of customer collections — deducted here and credited in his receivable ledger') ?></div></td><td class="num">-<?= money($hisab['approved_expenses'], false) ?></td></tr>
      <tr><td><?= __('Expenses paid directly by company') ?><div class="help" style="margin-top:2px"><?= __('P&L expense paid by the company — informational, NOT deducted from his collections') ?></div></td><td class="num"><?= money($hisab['company_expenses'], false) ?></td></tr>
    </tbody>
    <tfoot><tr>
      <th><?= __('Outstanding – Salesman Must Pay Company') ?></th>
      <th class="num" style="font-size:20px;color:<?= $hisab['outstanding'] >= 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money(abs($hisab['outstanding']), false) ?></th>
    </tr><tr><td colspan="2" style="font-size:11px;color:var(--gray)"><?= $hisab['outstanding'] >= 0 ? __('Salesman is holding unsettled money; he owes the company this amount.') : __('Salesman over-paid — company owes him.') ?></td></tr></tfoot>
  </table></div>
  <div class="btn-group mt no-print">
    <a class="btn btn-primary" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/collections')) ?>"><?= __('Record Collection') ?></a>
    <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/recovery')) ?>"><?= __('Handover to Company') ?></a>
    <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/expense')) ?>"><?= __('Record Expense') ?></a>
  </div>
</div>

<div class="panel print-area" style="max-width:860px;margin:0 auto">
  <h2><?= __('Combined Summary (informational only)') ?></h2>
  <div class="table-wrap"><table class="tbl" style="font-size:15px">
    <thead><tr><th><?= __('Description') ?></th><th class="num"><?= __('Amount (Rs)') ?></th></tr></thead>
    <tbody>
      <tr><td><strong><?= __('Goods Payable (what he owes you)') ?></strong></td><td class="num"><strong><?= money($goodsPayable, false) ?></strong></td></tr>
      <tr><td><?= __('Money handed over to company (all-time)') ?></td><td class="num"><?= money($hisab['handed_total'], false) ?></td></tr>
      <tr><td><?= __('Salary remaining (earned not yet paid)') ?></td><td class="num"><?= money($salaryRemaining, false) ?></td></tr>
      <tr><td><?= __('Expenses paid by the company') ?><div class="help" style="margin-top:2px"><?= __('P&L expense; not salesman debt and never settled against collections') ?></div></td><td class="num"><?= money($expensesPosted, false) ?></td></tr>
      <tr><td><?= __('Advances outstanding') ?></td><td class="num"><?= money($advancesOut, false) ?></td></tr>
    </tbody>
    <tfoot><tr><th><?= __('Balance — Payable (+) / Refundable') ?></th><th class="num" style="font-size:20px"><?= money(abs($net), false) ?></th></tr>
      <tr><td colspan="2" style="font-size:11px;color:var(--gray)"><?= $net >= 0 ? __('Salesman owes the company.') : __('Company owes the salesman.') ?> <?= __('Goods payable and advances, minus salary remaining. Salary is never settled against goods.') ?></td></tr></tfoot>
  </table></div>
  <div class="btn-group mt no-print"><a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/account')) ?>"><?= __('Back to Account') ?></a></div>
</div>
