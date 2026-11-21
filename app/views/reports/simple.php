<?php $title = __('Simple Reports'); ?>
<div class="simple-hero" style="background:linear-gradient(135deg,#143d25,#1f6a39 60%,#2f7a4c 100%)">
  <div class="greet"><?= __('Today in plain words') ?><br><small><?= __('Everything you need for today — numbers big enough to see from across the room.') ?></small></div>
  <div class="today-clock"><div class="t-d"><?= date_display($today) ?></div><div class="t-y"><a href="<?= e(url('reports')) ?>"><?= __('All reports →') ?></a></div></div>
</div>

<h3 class="section-head" style="margin-top:4px"><span class="tick"></span><?= __('Today') ?></h3>
<div class="simple-reports">
  <a class="report-tile" href="<?= e(url('reports/daily-sales')) ?>"><span class="rt-lbl"><?= __('Sales') ?></span><span class="rt-val"><?= money($todaySales) ?></span><span class="rt-sub"><?= (int)$todaySalesCount ?> <?= __('invoices') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports/production')) ?>"><span class="rt-lbl"><?= __('Production') ?></span><span class="rt-val"><?= qty($todayProduction) ?><span style="font-size:13px"> <?= __('Packets') ?></span></span><span class="rt-sub"><?= __('finished today') ?></span></a>
  <a class="report-tile" href="<?= e(url('purchase/raw')) ?>"><span class="rt-lbl"><?= __('Purchases') ?></span><span class="rt-val"><?= money($todayPurchases) ?></span><span class="rt-sub"><?= (int)$todayPurchasesCount ?> <?= __('GRN') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports/expense-summary')) ?>"><span class="rt-lbl"><?= __('Expenses') ?></span><span class="rt-val"><?= money($todayExpenses) ?></span><span class="rt-sub"><?= __('paid today') ?></span></a>
</div>

<h3 class="section-head" style="margin-top:18px"><span class="tick"></span><?= __('Stock right now') ?></h3>
<div class="simple-reports">
  <a class="report-tile" href="<?= e(url('inventory/finished')) ?>"><span class="rt-lbl"><?= __('Finished Goods') ?></span><span class="rt-val"><?= qty($fgStock) ?><span style="font-size:13px"> <?= __('Packets') ?></span></span><span class="rt-sub"><?= __('ready to sell') ?></span></a>
  <a class="report-tile" href="<?= e(url('inventory/raw')) ?>"><span class="rt-lbl"><?= __('Raw Materials') ?></span><span class="rt-val"><?= number_format($rawStock, 0) ?><span style="font-size:13px"> <?= __('KG') ?></span></span><span class="rt-sub"><?= __('in factory') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports/stock-value')) ?>"><span class="rt-lbl"><?= __('Stock Value') ?></span><span class="rt-val"><?= money($stockValue) ?></span><span class="rt-sub"><?= __('raw + finished, company-wide') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports/salesman-stock')) ?>"><span class="rt-lbl"><?= __('Salesman Balances') ?></span><span class="rt-val"><?= money(abs($salesmanPayable), false) ?></span><span class="rt-sub"><?= $salesmanPayable >= 0 ? __('they owe you') : __('you owe them') ?></span></a>
</div>

<h3 class="section-head" style="margin-top:18px"><span class="tick"></span><?= __('Money still owed') ?></h3>
<div class="simple-reports">
  <a class="report-tile" href="<?= e(url('reports/customer-aging')) ?>"><span class="rt-lbl"><?= __('Customers Owe You') ?></span><span class="rt-val"><?= money($receivable) ?></span><span class="rt-sub"><?= __('open credit invoices') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports/salary-expense')) ?>"><span class="rt-lbl"><?= __('Salary to Pay') ?></span><span class="rt-val"><?= money($salaryBalance) ?></span><span class="rt-sub"><?= __('payslips pending or partial') ?></span></a>
  <a class="report-tile" href="<?= e(url('expenses')) ?>"><span class="rt-lbl"><?= __('Today Expenses') ?></span><span class="rt-val"><?= money($todayExpenses) ?></span><span class="rt-sub"><?= __('today') ?></span></a>
  <a class="report-tile" href="<?= e(url('reports')) ?>"><span class="rt-lbl"><?= __('All Reports') ?></span><span class="rt-val">→</span><span class="rt-sub"><?= __('every report in the system') ?></span></a>
</div>
