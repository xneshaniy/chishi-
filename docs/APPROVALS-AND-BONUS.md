# Approximations & Implementation Notes — JUSTI NUMBER ONE ERP (CTC)

This document explains every v2 module in plain language: what it does, what the numbers mean, and the exact accounting entries involved.

---

## 1 · Separate Login Areas

Two independent doors into the same ERP:

| Area | Who can log in | What they see |
|---|---|---|
| `/admin` (Admin Console) | Owner and Admin only | Everything, plus the **Payment Approvals inbox** (`/admin/approvals`) and admin quick actions |
| `/management` (Management Console) | Manager, Accountant, Owner, Admin | Team performance, receivables, unpaid payroll, quick links into the permitted ERP modules — **no approvals power** |

The gate is enforced **server-side** in `index.php` via `Auth::requireArea()`, not by hiding menus. An accountant typing `/admin` directly is redirected to `/admin/login` with an error message. Only Owner/Admin reach the approvals inbox; managers and accountants can look but cannot approve or reject anything.

Each user saves their preferred language (EN / Roman Urdu / اردو) on their account; the topbar toggle switches it instantly and persists it.

---

## 2 · Financial Approval System

**Rule: nothing outgoing leaves the books without Owner/Admin approval.** The system never books a journal for outgoing money until an Owner or Admin explicitly approves the request.

### 2.1 Which transactions need approval

| Document | Requested by | Approved by | Ledger effect |
|---|---|---|---|
| Supplier payment | Anyone with purchase perms | Owner/Admin | After approval: Dr AP, Cr Cash/Bank |
| Expense | Anyone with expense perms | Owner/Admin | After approval: Dr category account, Cr Cash/Bank (or Cr AP if payable) |
| Transfer (cash ↔ bank, bank ↔ bank) | Anyone with transfer perms | Owner/Admin | After approval: Dr destination, Cr source |
| Salary / payroll payment | Payroll clerk | Owner/Admin | After approval: Dr Salaries & Wages, Cr Cash/Bank (per employee) |
| Salary advance | Employee request (via manager) | Owner/Admin | After approval: Dr Employee Advances (asset), Cr Cash/Bank |
| Employee loan | Employee request (via manager) | Owner/Admin | After approval: Dr Employee Loans (asset), Cr Cash/Bank; installment schedule auto-created |
| Salesman credit issue | Salesman via storekeeper | Owner/Admin | After approval: stock moves AND Dr Salesman Receivable, Cr Sales Revenue |
| Below-minimum sales invoice | Cashier | Owner/Admin | After approval: stock moves AND Dr AR/Cash, Cr Sales/Tax, Dr COGS, Cr Inventory |
| Restricted bonus scheme | Anyone with bonus perms | Owner/Admin | After approval: stock moves AND bonus cost posts Dr Promotion Expense, Cr Inventory |
| Refund | Owner/Admin directly | — | Post immediately (approvals bypass) |

### 2.2 How it works step by step

1. The requester picks the document (say a supplier payment) and submits it.
2. The document is written to its table with `status = 'pending'`. **No cash, bank, GL or stock movement happens yet.**
3. An `approval_requests` row is created with `status = 'pending'` — linked to the source document by `(reference_type, reference_id, request_type)` with a unique constraint so duplicate submissions collapse into one request.
4. The Admin Console shows the pending count on every page; opening **Approvals Inbox** lists everything pending with requester, amount, reason and timestamps.
5. Owner/Admin types a decision note and clicks **Approve** or **Reject**.
6. **Approve** → the system flips the document status and posts the journal (exactly once). **Reject** → nothing posts; balances are unchanged; the request and the rejection note stay in the audit trail forever.
7. **Requester ≠ approver** is enforced inside the transaction — nobody can approve their own request, even the Owner. A second Admin (or another Owner) must decide.

### 2.3 Why this can't post twice

Every posting handler has a guard (`status` flip to `posted`, plus `journal_entry_id` existence, plus journal-level `idem_key`). Calling it twice changes nothing. `Approvals::decide()` also refuses decisions on non-pending requests (`throw new RuntimeException`).

### 2.4 Admin Approval Center (expanded)

Opening **Approvals Inbox** (`/admin/approvals`) now shows:
- **Requested By**, **Reason** and an inline **Details →** link to `/admin/approvals/{id}` — every request shows: type, requested amount, requester name, date/time, payment method, cash or bank account, related party, the full source-document snapshot, any **attachments** (Receipt / PDF) downloadable through the hardened `admin/attachment` endpoint (realpath-confined to `storage/`, owner/admin only), an **Audit Trail** of every action taken on the request, and the **Decision** form (Approve & Post / Reject-with-reason).
- **`/admin/notifications`** — every notification stored in the system (all users, unread/read filter, type filter, search) for Owner/Admin to see the complete picture. Front-end visibility for other users remains role-scoped.
- The **Admin Console dashboard** shows four cards: Pending Approvals, Total Pending Payment Amount (sum of every pending request), Approved Today, Rejected Today.

### 2.5 Payment Approval sidebar tab (Owner/Admin only)

The sidebar shows a dedicated **Payment Approval** tab (new `approval` icon, `Payment Approval` section) for the `/admin/approvals` route. It is registered in `app/views/layout.php` gated on the `approvals.decide` permission only, and the gate deliberately does **not** fall back to the normal `.view` group fallback that other entries use — Manager and Accountant hold `approvals.view` but must never see this tab, and they never pass the server-side `/admin/*` area gate (HTTP 302 to the admin login) either:

| Actor | Sees tab? | Can open `/admin/approvals`? |
|---|---|---|
| Owner | yes | 200 |
| Admin | yes | 200 |
| Manager | no | 302 |
| Accountant | no | 302 |
| Employees / Labour / Salesmen | no | 302 |

What the tab shows:
- A header with **Pending Approvals** count and **Pending Payment Amount** (auto-summed across every pending request ERP-wide), so the Owner/Admin sees the full queue at a glance without opening any filter.
- Filter pills: **Pending Approvals / Approved Payments / Rejected Payments / All Requests** (`?status=` server-side filters), plus Type and free-text search (requester username, full name, reason, reference type).
- A table with **Request #**, **Party**, **Type**, **Requested By**, **Amount**, **Payment Method / Account**, **Request Date** and **Status**.
- Party cell renders the actual saved **profile photo** (`photo_data_uri`, embedded data-URI, so images render in every proxy/browser context and survive refresh) for employees and salesmen, with the person's **full name and employee/salesman ID code** (e.g. `EMP-0001`, `SM-014`). Suppliers and customers show a default initial avatar (no document images are ever used as avatars). When no photo exists, an initials avatar renders instead. Photos come from the existing employee/salesman profile (`employees.photo_path` / `salesmen.photo_path`); CNIC/back/front document images are never used.
- Approve/Reject actions per request, unchanged semantics (exactly-once posting, no posting on reject, no self-approval, full audit trail).

`Approvals::source()` was extended to also return `party_id` / `party_code` / `party_photo` / `party_kind` for every reference type the ERP emits (incl. `claim_goods_payments`, which previously had no source description at all), and two latent fatals in the inbox were fixed (`maintenance_records.date` → `problem_date`, `purchase_orders.date` → `order_date`). The list page enriches each row with the source snapshot so photos and names render inline.

### 2.8 New employee / salesman registrations require Owner/Admin approval

**Manager may add new Employees and Salesmen but every such registration stays inactive until Owner/Admin accepts it.**
- Manager adds the record via the normal Employee/Salesman form. The save path branches: Owner/Admin → record becomes active immediately (unchanged); Manager → record is created with `is_active = 0` and an `approval_requests` row of type `registration` / reference `employees` or `salesmen` is opened. No journal entries, no stock moves — this is a workflow only.
- The request appears in the same Admin/Owner approval queue as payments. The detail page renders a **Registration Profile** panel with the photo, ID, name, contact, CNIC, address, job title/area, salary + **Salary Payment Frequency**, commission/vehicle/join date, requester name and request date.
- **Approve** flips the record active (`is_active = 1`) and, for salesmen, creates their dedicated sales-stock warehouse (previously created eagerly — now created at approval, keeping unapproved registrations out of every operational surface). The Manager is notified of the decision.
- **Reject** (reason required) leaves the record inactive forever — it appears nowhere in employee/salesman lists, attendance, salary computation, payroll generation or PeopleDirectory (all filter `is_active = 1`). The rejection note and full audit trail persist.
- Manager self-approval is impossible (`requested_by != deciderId` inside `Approvals::decide()`), Manager holds no `approvals.decide` permission, and the Manager-facing queue renders no Approve/Reject controls.
- Duplicate registrations collapse to a single pending request per record (`Approvals::create` no-ops on existing pending). CNIC-duplication is checked on submission.
- Owner/Admin direct-create stays immediate (no approval) — only Manager registrations go through approval.

### 2.9 Salary Payment Frequency (per person, Monthly | Weekly)

Every employee and salesman now stores a separate **Salary Payment Frequency** (`salary_frequency` column; default `monthly`). The two are independent per person: the field is required on both profile forms, displayed on the profile page (Biodata panel / stat chip), the salary section card, the employee salary-set and salary detail pages.

- **Employees**: `salary_frequency` mirrors the payroll engine's `salary_type` (`monthly` | `weekly`), so the weekly engine path (`rate × present days / 6 working days`, half day = 0.5) is used automatically. Payroll generation takes a frequency filter (`all` / `monthly` / `weekly`), so monthly and weekly staff are never generated in the same run; the existing period-unique guard prevents double-generation.
- **Salesmen**: `salary_frequency` on the salesman record drives display; their salary payments (type `salesman_salary_payments`) already support partial payments and stay unchanged.
- **Salary edits are Owner/Admin-only**. Managers fill the salary fields on a new registration (required) but cannot change salary figures for existing profiles — every salary-changing endpoint (`setSalary`, profile salary fields) is Owner/Admin-gated server-side.
- Existing records: the upgrade sets `salary_frequency = 'monthly'` (and resyncs `employees.salary_type`), preserving all current amounts and cadence.
- Attendance remains Present/Absent/Half Day only; pending/rejected registrations never appear on the board because PeopleDirectory filters `is_active = 1`.

---

## 3 · Bonus & Free Stock — how the numbers work

### 3.1 Salesman bonus issue (your 200-paid + 50-free example)

Scenario: Company issues 200 paid units at issue rate Rs 10 and 50 free units to a salesman.

Step-by-step internally:

1. User opens **Salesmen → Issue Stock**, picks salesman, warehouse, date, enters `PAID 200`, `BONUS 50`, issue rate auto-filled from the product's `issue_rate` column.
2. The system calculates: total units = 250, payable = 200 × 10 = **Rs 2,000**, bonus cost = 50 × 2.00 (average cost) = **Rs 100**.
3. If the **bonus approval threshold** (set in **Settings → Bonus Schemes & Rules**, default 10,000) is exceeded OR the selected scheme requires approval, the issue is saved as **pending approval** — nothing moves yet.
4. Admin approves in the console (or auto-posts if under the threshold) → `postSalesmanIssue()` runs inside one transaction:
   - Stock moves out of the source warehouse (paid 200, bonus 50) into the salesman van warehouse — `stock_movements` records both movements.
   - `salesman_issues` gets `total_payable = 2,000`, `total_bonus_cost = 100`.
   - Journal posts: **Dr Salesman Receivable 2,000**, **Cr Sales Revenue 2,000** (payable — never on bonus); **Dr COGS 400** (200 × 2.00), **Cr Inventory 400**; **Dr Promotion Expense 100**, **Cr Inventory 100**.
   - `salesman_ledger` records the salesman's running balance +2,000.
5. The salesman can now sell at whatever price; each sale deducts from van stock. The salesman's outstanding balance is shown on his ledger page (`Salesmen → Ledger`), where he or an admin can record his payment to the company (Dr Cash/Bank, Cr Salesman Receivable — no approval needed, money in).

**What you verified in the automated tests (63 assertions, in-memory SQLite):**
- Van received exactly 250 units; main warehouse drained by 250.
- Salesman owes exactly Rs 2,000 (the paid portion); bonus 50 units are free.
- Salesman receivable = 2,000 in the ledger.
- Promotion expense = 100, COGS = 400.
- A bonus month re-post does nothing (exactly-once).

### 3.2 Customer free goods (sales invoice bonus lines)

When creating an invoice, each line can be marked **Bonus**. Bonus lines:
- Move stock from the warehouse (it leaves your stock).
- Carry zero revenue (no Dr Receivable for them).
- Post Dr Promotion Expense / Cr Inventory at the line's cost.
- Reduce the invoice total by zero (they are free).
- The selected **bonus scheme** is recorded on the invoice and on the scheme's report row, including which admin approved it (if restricted).

Below-minimum-price invoices (product `min_price` exceeded by the entered selling price) are created in **pending approval** state — Owner/Admin must approve in the console before stock moves and the journal posts. The approval proves who authorised the discount.

### 3.3 Purchase bonus (supplier free goods)

On the GRN/Purchase Invoice form, each line can be marked **Bonus**. Bonus lines:
- Add stock at **cost 0** (average cost of the product goes down — correct weighted-average behavior).
- Never increase the payable (tax and cost are forced to 0 for bonus lines).
- Appear in the bonus report under "Purchase Bonus".

### 3.4 Bonus reports

**Reports → Bonus & Free Stock** shows:
- Salesman bonus issued (units, cost, by salesman),
- Customer free goods (units, cost, by scheme),
- Purchase bonus (units),

for any date range, with CSV export. Bonus stock also appears in **Stock Valuation**, **Stock Ledger** and each salesman's van balance — it's real inventory until it leaves.

---

## 4 · Salesman Selling Price vs Issue Rate

Per-product pricing model (set on the Products form):

| Field | Meaning |
|---|---|
| **Purchase Price** | What the company pays the supplier per unit |
| **Sale Price** | Company's shop/counter selling price |
| **Issue Rate** | What the salesman pays the company per unit for van stock |
| **Min Price** | Floor price — below this, invoice requires Owner/Admin approval |

When a salesman sells at Rs 9 with an issue rate of Rs 10:

- The invoice records the actual selling price (Rs 9).
- The difference (Rs 1 / unit) is computed on the **Price Difference** report (`Reports → Salesman Price Difference`).
- The report lists every posted invoice line with issue rate, selling price, per-unit difference and total impact — red for below-issue-rate rows (money the company loses on paper).

**Below minimum price** ⇒ the invoice is created as `pending`. Stock does not move; no journal posts. Owner/Admin approves in the console (authoriser recorded on the invoice). Below the minimum is blocked entirely if the admin does not approve — the requester can only wait, not push it through.

---

## 5 · Employee Salary Management

Five salary types, all on the Employees form:

| Type | Formula | Example |
|---|---|---|
| **Monthly** | Full rate per month | Manager 30,000 |
| **Weekly** | Rate × (days present ÷ 6) | Weekly labour 7,000/week |
| **Daily** | Rate × days present | Daily wage 5,000/day |
| **Hourly** | Rate × hours worked | Hourly worker 500/hour |
| **Contract** | Fixed rate for the period | Fixed contract 30,000 |

Attendance uses a grid with `status` (present/absent/leave/half-day/off) and an `hours` column (default 8.00). Attendance hours drive hourly pay and overtime. Overtime = hours beyond 8 per day; rate = 1.5× derived equivalent (or the employee's custom `overtime_rate`).

Generate a payslip period (e.g. July 2026) and the system computes every active employee automatically:
- Basic pay by type,
- Overtime,
- Minus outstanding advances (full, or capped by the employee's monthly deduction),
- Minus due loan installments falling due within the period,
- Net payable.

Printable payslips (`Print` link on each row) show earnings and deductions with signature lines. Reports:
- **Salary Expense** (`reports/salary-expense`) — paid salary by employee for any range,
- **Pending Salary** (`reports/pending-salary`) — employees with no payslip for the period,
- **Payroll History** — implicit in the payroll list.

---

## 6 · Salary Advance

1. Employee asks the manager for an advance (any reason — "medical", "house deposit").
2. Manager or admin opens **Employees → Edit → Request Salary Advance**, enters amount, date, reason, and optionally a **monthly deduction cap** (e.g. Rs 1,000/month instead of full 5,000 in one month).
3. The system creates a **pending** advance and an approval request. Stock/cash does not move.
4. Owner/Admin approves in the console → the advance is **disbursed**: Dr Employee Advances (asset), Cr Cash/Bank. The employee's outstanding advance balance rises.
5. At payroll generation, outstanding advances deduct automatically — full outstanding by default, or the employee's monthly cap if set. Deductions are capped so salary cannot go negative.
6. After the deduction, the advance row shows `deducted` amount and remaining `0`.
7. Reversing a payslip (Manager action on the payroll list) returns deducted amounts to outstanding — advances and loans are restored linked to that payslip via `payroll_deductions`.

**Spec example verified in tests:** 30,000 salary, 5,000 advance → next month net = **25,000**.

---

## 7 · Employee Loans

Loans are a separate ledger from advances — different GL account (`EMP_LOAN` vs `EMP_ADV`), different schedule.

1. Request a loan from **Loans → New Loan** (or via the admin's console): amount, reason, installment type (weekly/monthly), installment amount. The system computes the installment count (12 for 24,000 @ 2,000/month).
2. Owner/Admin approves → disbursement: Dr Employee Loans, Cr Cash/Bank. The loan becomes `active`.
3. An installment schedule table is auto-created (`loan_installments` — one row per installment with due date + amount).
4. At payroll generation, due installments (`due_date ≤ period_end`) deduct automatically and mark themselves `deducted`. The outstanding drops by each deducted amount.
5. Manual repayments (`Payback` button on the loan view) record Dr Cash/Bank, Cr Employee Loans and mark installments paid oldest-first; the loan's `recovered` and `outstanding` update.
6. When recovered == amount, the loan flips to `completed` automatically.

**Reports:** per-loan detail page (schedule + manual repayment + outstanding), **Employee Loan Report** (`reports/employee-loans`) shows every employee's loans, total amount, recovered, outstanding.

**Spec example verified in tests:** 24,000 loan, Rs 2,000/month → next month net = **28,000**; recovered = 2,000 after one month; outstanding = 22,000.


### 2.6 Payment Approval for Managers (read-only)

`management/approvals` opens the same Payment Approval cockpit for Manager users (Owner/Admin use the unchanged `admin/approvals`):
- **Manager sees** the pending queue with every request ERP-wide, request type, requester, party (photo/ID/name), amount, method, cash/bank account, date, status; the **Pending Approvals** count and **Pending Payment Amount** chips; the Pending/Approved/Rejected/All filter pills; and the request detail page with full **approval history / audit trail and rejection reasons**.
- **Manager cannot approve or reject anything.** The Approve/Reject buttons and the rejection-note field are never rendered for Manager; the only decide endpoint (`/admin/approvals/decide`) sits behind the server-side `/admin/*` area gate and `approvals.decide` — a Manager calling it directly gets a 302 before the controller runs, and `approvalsDecide()` itself hard-denies any non-Owner/Admin caller.
- Accountant holds no view route — `management/approvals` returns 403 for them; the menu shows neither.
- Sidebar: exactly one **Payment Approval** entry per role — Owner/Admin see one pointing at `admin/approvals`, Manager sees one at `management/approvals` (strict role whitelist in `layout.php`; the `.view` fallback is bypassed for these routes).
- Manager keeps full rights to **create/edit/update payment requests** through the existing permitted routes (`cashbank/payments/new`, `expenses/new`, `purchase/orders/new`, `loans/new`…). Submitted docs appear in the approval queue as usual; only Owner/Admin decide.

### 2.7 Attendance for Managers

`attendance` (board), `attendance/report` and the new **`attendance/history`** are reachable by Owner/Admin and Manager (perm `attendance.mark`, which they already hold). Everything else about attendance is unchanged:
- Manager records Present / Absent / Half Day per registered person (employees, labour workers, other staff; salesmen too — same board as before).
- The board shows **who recorded/last updated** each mark (via `staff_attendance.marked_by`, joined to `users`), and `attendance/history` lists every change (who, what old→new, when, optional audit note) paginated, from `staff_attendance_audit`.
- Salesman and Accountant roles cannot open the board (302 / 403); only `attendance.mark` holders can.
- Salary and payroll math is untouched.

---

## 8 · Suggested first-week rollout

1. Set your real **Bonus Approval Threshold** in Settings → Bonus Schemes & Rules (start with 5,000).
2. Create bonus schemes you use (10+1, festive offer, etc.) — mark all "Special" and "Festive" as restricted.
3. Go through Products and set each product's **Issue Rate** and **Minimum Price**.
4. Record all employees with the correct salary type; mark attendance for the current month.
5. Generate payroll once as a dry run and review it with the owner.
6. Do a real month-end close and print Trial Balance + P&L for sign-off.
