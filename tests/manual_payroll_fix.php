<?php
$f = 'docs/USER-MANUAL.md';
$lines = file($f);
// find the exact section to replace: from '### Setting salary type' to the line before '### Employee loan'
$start = null; $end = null;
foreach ($lines as $i => $line) {
    if (str_contains($line, 'Setting salary type and running a payroll period')) $start = $i;
    if ($start !== null && str_contains($line, '### Employee loan')) { $end = $i; break; }
}
echo "replacing lines ", $start + 1, " to ", $end, "\n";
$new = <<<'MD'
### Setting salary type and running a payroll period
1. **Employees → New/Edit** — pick salary type (Monthly / Weekly / Daily / Hourly / Fixed Contract), salary rate, **Salary Payment Method (Cash/Bank)**, and Active/Inactive. Only Owner/Admin can set or change the salary rate.
2. Mark attendance for the period (status + hours).
3. **Payroll** → pick the period → **Generate Draft Payroll**.
4. Review basic, overtime, deductions, net pay; each payslip row shows **Paid** and **Remaining**; Print payslips if needed.
5. **Submit for Approval** per payslip — the amount field defaults to the full remaining balance; enter a smaller amount for a **partial payment** (repeat until zero) → Owner/Admin approves → journal posts; payslip flips to **PARTIAL** then **PAID**.
6. **Employees → Ledger** shows each employee's earned/paid/remaining-payable totals and full payment history (printable).

### Salary advance
1. **Employees → (name) → Request Salary Advance** — amount, date, reason, payout account, optional monthly deduction cap. Anyone with the advances.request permission can request (managers don't need edit rights).
2. Only **Owner/Admin** sets the salary rate; advance disbursement is Owner/Admin-approved in the console → the advance is disbursed (Dr Employee Advances, Cr Cash/Bank).
3. At the next payroll run, the advance deducts automatically (full outstanding or monthly cap).
4. Reversing a payslip returns the advance to outstanding.
5. The requester can **cancel their own pending request** from the same page — nothing is paid and the approval is rejected with "Cancelled by requester".
6. Company-wide **Maximum Salary Advance (Rs)** (Settings → Company Profile → Payroll Policy) rejects larger requests from non-Owner/Admin users before approval. 0 = no limit.
7. **Print Advance Voucher** prints amount in words and the approval details.

MD;
array_splice($lines, $start, $end - $start, [$new]);
file_put_contents($f, implode('', $lines));
echo "done\n";
