<?php $title = __('Reports Hub');
$reports = [
    ['reports/daily-sales', 'Daily Sales Report', 'Invoices, totals, collections and gross margin for any date.', '📅'],
    ['reports/sales-summary', 'Sales Summary', 'By product, salesman, area or customer — with CSV export.', '📊'],
    ['reports/collections', 'Collection / Recovery', 'All receipts in a period with mode-wise totals.', '💰'],
    ['reports/customer-aging', 'Customer Aging', 'Who owes you money, and how overdue (FIFO allocation).', '🧾'],
    ['reports/supplier-aging', 'Supplier Aging', 'What you owe suppliers and how overdue.', '🏭'],
    ['reports/stock-value', 'Stock Valuation', 'Value of closing stock by warehouse and by kind.', '📦'],
    ['reports/company-stock', 'Company Stock Daily', 'Factory + salesman van stock by day — opening, produced, issued, sold, returns, closing; combined without double counting.', '📊'],
    ['reports/expense-summary', 'Expense Summary', 'Expenses by category with voucher counts.', '🧾'],
    ['reports/company-expenses', 'Company Expenses', 'Company/factory operating expenses — category-wise totals, cash vs bank, pending approvals.', '🏭'],
    ['reports/production', 'Production Report', 'Completed orders with materials, labor, overhead and cost.', '🏭'],
    ['reports/salesman-stock', 'Salesman Stock & Settlement', 'Goods with salesmen, sales and collections this period.', '🚚'],
    ['reports/bonus-report', 'Bonus & Free Stock', 'Bonus quantities and values — salesman, customer schemes and purchase freebies.', '🎁'],
    ['reports/price-difference', 'Salesman Price Difference', 'Issue rate vs customer selling price — discount/loss per line.', '⚖️'],
    ['reports/salary-expense', 'Salary Expense', 'Paid salary by employee for any period.', '💼'],
    ['reports/pending-salary', 'Pending Salary', 'Employees with no payslip for the period.', '⏳'],
    ['reports/maintenance', 'Maintenance & Repairs', 'Maintenance cost by day, month, year, machine, department or vendor; full history per machine.', '🛠'],
    ['reports/fg-daily', 'Finished Product Daily', 'Opening, produced, issued, sold, returns, closing for each finished product.', '📦'],
    ['reports/raw-daily', 'Raw Material Daily', 'Opening, purchases, consumption in production, adjustments, closing.', '🧱'],
    ['reports/day-book', 'Day Book', 'All posted journals in a date range.', '📖'],
    ['accounting/trial-balance', 'Trial Balance', 'All ledger accounts, debits = credits.', '⚖️'],
    ['accounting/pnl', 'Profit & Loss', 'Income − expenses for any period.', '📈'],
    ['accounting/balance-sheet', 'Balance Sheet', 'Assets = liabilities + equity, as of any date.', '🏦'],
    ['accounting/cash-flow', 'Cash Flow', 'Opening → closing cash with net movement.', '🌊'],
    ['accounting/ledger', 'General Ledger', 'Pick any account and see every posted line.', '📚'],
]; ?>
<div class="cards" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
<?php foreach ($reports as [$route, $name, $desc, $icon]): ?>
<a class="card accent" href="<?= e(url($route)) ?>" style="text-decoration:none"><div style="font-size:26px"><?= $icon ?></div><div class="lbl" style="margin-top:6px"><?= e($name) ?></div><div class="sub"><?= e($desc) ?></div></a>
<?php endforeach; ?>
</div>
<div class="panel"><p class="help">All reports accept date-range filters. Tabular reports export to UTF-8 CSV (opens in Excel). For PDF, open the report and use Print → “Save as PDF” — layouts are print-optimised. Figures derive exclusively from posted transactions — nothing is hardcoded.</p></div>
