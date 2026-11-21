# User Manual — JUSTI NUMBER ONE ERP (CTC)

Audience: owner, administrator, accountant, manager, storekeeper, salesman.
Languages: English (LTR) / اردو (RTL) / Roman Urdu (LTR) — toggle top-right; your choice is saved on your account and applies instantly on every screen after sign-in.

## Golden rules

1. **Nothing is deleted.** Mistakes are fixed by *reversal*: every reversal posts a mirror-image journal and (for stock documents) restores stock. The original stays visible marked REVERSED.
2. **Posted = locked.** Once posted, a voucher cannot be edited. Check before posting.
3. **One source of truth.** Dashboard and reports read posted transactions only. Draft numbers don't appear anywhere.
4. **Salesman van stock is not your shop's stock.** Issues to a salesman = transfer, never a sale.

## Signing in

- URL: `https://software.chishtitobaccocompany.com` (or the address your admin gave you).
- After 5 failed attempts the account locks for 15 minutes — contact the owner/admin to reset.
- Idle sessions expire after 120 minutes; you'll be asked to sign in again.
- If an account shows "you must change your password", see Users & Roles → Edit (owner can reset your password and force a change).

## Daily flow by role

### Storekeeper / Warehouse
1. Morning: check **Dashboard → Low Stock** and **Physical Stock Count** if doing a count day.
2. Goods arriving (finished factory inputs and raw materials): **Purchases → Raw Material Purchases** for raw/packaging, or **Purchase → Receive Goods (GRN)** for everything else. GRNs post stock and the journal automatically. Note: managers submit raw-material purchases as **requests** — an Owner/Admin must approve them before stock moves.
3. Stock moving between factory/warehouse/salesman: **Inventory → Stock Transfers** (or Salesmen → Issue Stock for vans).
4. Machine broke down? **Maintenance → New Maintenance Record** — enter the machine, what happened, parts used, labour and vendor; the record waits for Owner/Admin approval before any payment posts.
4. Never use adjustments to fix paperwork mistakes; adjustments are for real physical variances and need approval.

### Salesman
1. You only see **your** customers and **your** invoices/receipts. If a customer isn't in your list, ask the admin to assign it (Customers → edit → Assigned Salesman).
2. Create invoice: **Sales → New Invoice** — pick customer, items; "Bonus" checkbox for free goods (they leave your van, no money).
3. Collect cash/bank: **Cash & Bank → New Receipt** (or ask admin/accountant to record it).
4. Evening: **Salesmen → Settle** (or ask manager) — enter counted cash; the system shows expected vs counted and records any shortage/excess.
5. Van empty but more needed? Tell the warehouse — they will **Issue Stock** to you. Never book it as a purchase.

### Accountant / Cashier
1. Receiving money: **Cash & Bank → Receipts** — pick customer, account (cash/bank), amount; posting writes the journal instantly (Dr Cash/Bank, Cr Receivable).
2. Paying money: **Cash & Bank → Payments** for suppliers; **Expenses → New Expense** for factory/office/fuel/etc. (records as *pending*; an approver posts it).
3. Owner's personal shopping? **Payments → Owner (personal drawing)** — goes to Drawings (equity), never to expenses, so profit stays clean.
4. Moving cash to bank: **Transfers** — Dr bank, Cr cash; use Cheque Register for issued/received cheques and mark them cleared/bounced when the bank does.
5. Every day: **Accounting → Day Book** — skim vouchers; **Trial Balance** — must always agree.
6. Month-end: **Expenses** posted, **Payroll** paid, **Ledger** for large accounts tied out to statements, then **Financial Year Closing** only after all twelve periods are posted and the balance sheet agrees.
7. Factory/office running costs (Plastic Bags, Electricity, Water, Gas, Internet, Petrol/Diesel, Packaging, Office & Factory Supplies, Loading/Unloading, Transport, Repairs …): **Expense → Company Expenses → + New Company Expense** — date, category (or a custom one via Expense Categories), item, optional qty/unit/rate (amount auto-calculates), supplier, Cash or Bank payment (bank requires the account), receipt photo/PDF, notes. It stays *pending* until Owner/Admin approves in **Payment Approval**; the cash/bank GL moves exactly once on approval, and the record (plus its audit trail) is kept forever — cancel instead of delete if it was wrong.

### Manager
1. Approve **Expenses** (post them), approve **Stock Adjustments**, approve **Claims**.
2. Watch **Dashboard**: receivables, overdue credit invoices, salesman performance, stock value.
3. Create/adjust **Credit Limits** on customers (Customers → edit). Over-limit credit sales are blocked in the invoice form.
4. Record bonuses: **Salesmen → Commission & Bonuses**; pay them via Expenses → Sales Commission.
5. Need raw materials? **Purchases → Purchase Orders → New PO** — it goes to the Owner/Admin for approval. You never add raw stock directly.
6. Report a machine fault: **Maintenance → New Maintenance Record** — Owner/Admin must approve before payment posts.

### Owner / Admin
1. **Users & Roles**: create accounts, assign roles, reset passwords, deactivate leavers (toggle). Owner role = everything; keep only one or two owner accounts.
2. **Settings → Company Profile**: logo, NTN/STRN, bank details, invoice terms — these print on every invoice.
3. **Settings → Fiscal Year**: check periods are open/closed correctly; closing a period blocks all posting into it (including back-dating).
4. **Settings → Opening Balances**: one-time after go-live; re-runnable (skips already-posted lines).
5. **Audit Log**: who did what, when, from which IP. Filter by user/entity/date/action.

## Common tasks (how-to)

**How do I sell with bonus cigarettes?**
Sales → New Invoice → two lines for the same product: one normal line (qty 10), one Bonus-checked line (qty 1, price 0). The bonus line moves stock, posts Dr Bonus/Promotion Expense at cost, books no revenue and reduces nothing else.

**Customer returned goods.**
Sales → Sales Returns → New → pick the original invoice → enter return qty → Post. Stock goes back, receivable drops, COGS/inventory reverse. The return gets its own voucher (SR-00001).

**I posted a wrong invoice.**
Open it → **Reverse Invoice** (danger button, top of page). Stock is restored, a mirror journal posts, customer balance drops. Create the corrected invoice. Both stay in the ledger forever — that's the audit trail.

**Supplier bill didn't arrive yet but goods are here.**
Purchase → GRN as usual (or log it against the PO by clicking "Receive Goods" on the PO). When the bill lands, note its number in "Their Invoice / Bill No" if you have it; you can add it later by reference in the notes.

**Salary day.**
Payroll → pick the month → **Generate Draft Payroll** (daily-wage staff need attendance marked for the month — partial months pay proportionally) → review the list → each row has the **amount field (defaults to the full remaining balance)** → enter a smaller amount for a partial payment → pick cash or bank → **Submit for Approval**. The Owner/Admin approves or rejects in the console; the payslip flips to **PARTIAL** until the full net is paid, then **PAID**. The employee's **Salary Ledger** page shows earned vs paid vs remaining payable plus complete payment history, and **Payslip** print shows the payment status block. Reversing a payslip restores outstanding advances automatically and reverses every partial payment journal.

**Where is my profit?**
Reports → Profit & Loss (any date range) and Dashboard → Month Snapshot. Profit = income − expenses (COGS included). Owner drawings are equity and never appear here.

**Printing an invoice / statement / payslip.**
Open it → **🖨 Print** (or Ctrl+P) → in the dialog choose **Save as PDF** to email it. All statements and invoices are print-optimised; the sidebar/topbar vanish on paper.

**Urdu view.**
Click **اردو** top-right. The layout flips to RTL and menus show Urdu labels. Data entry remains as-typed (name fields accept Urdu).

## Raw materials, production & finished goods (factory flow)

Two separate inventories, never mixed: **Raw materials** (kind *raw* and *packaging*: tobacco, wrappers, labels, paper) and **Finished products** (kind *finished*: cartons of JUSTI Number One ready to sell).

**The full chain in one day:**

0. **Manage salesmen from one account (new):** Salesmen → tap a name → **Account** — one page shows everything: goods issued (paid, bonus, payable), goods sold, stock with him, salary earned/paid/outstanding, all expenses by type (diesel, vehicle, food, hotel, repair, other), advances, and a final balance. Buttons: **Add Goods / Add Expense / Pay Salary / Advance Payment / Settlement**. Add the vehicle (type, reg no) and joining date on the Salesman edit page.

1. **Buy raw material (Owner/Admin):** Purchases → Raw Material Purchases → + New Raw Material Purchase — supplier, qty, rate **per selected unit** (**TON / KG / MAN**), invoice #, payment status (cash = Paid immediately; credit = Unpaid until supplier payments, which flip it to Partial → Paid automatically, oldest invoice first). Every raw line carries its purchase unit (e.g. **2 TON**); the system converts to KG automatically (default 1 TON = 1,000 KG, 1 MAN = 40 KG, editable in Settings → Units & Packaging). Stock goes up in KG, journal posts, purchase is recorded in the audit log.
2. **Manager submits a purchase request:** Purchases → Purchase Orders → New PO — the order sits as **PENDING APPROVAL** until an Owner/Admin clicks **Approve** (on the orders list or Admin Console → Approvals). No stock moves, no payable posts before approval.
3. **Issue raw material to the factory (new):** Inventory → **Factory Consumption** → + Issue to Factory — pick date, **batch no** (e.g. BATCH-2026-001), warehouse, and lines (raw material + quantity used **in KG**). The page shows **opening stock and remaining** per row; over-issue is blocked. Posting deducts exactly the KG from raw stock at actual cost; a printable **Consumption Voucher** is produced (Issued By / Approved By). Multiple materials per batch are fine. If "Factory Consumption Approval" is enabled in Settings → Units & Packaging, requests wait in Admin Console (RM_CONSUMPTION) before stock moves.
4. **Produce:** Production → Production Orders → New (pick the finished product + qty, attach the BOM → materials used per batch) → Create. Then **Release** (factory starts), then **Complete** — enter units actually produced, waste, labour and overhead. Raw materials are deducted from Raw Stock automatically: **if the batch has consumption entries, those are used as the actual consumption (never double-deducted);** otherwise the planned BOM materials are consumed at actual cost. Finished units land in Finished Product Stock automatically (with cost); the batch record is kept forever.
5. **Sell/issue finished goods only:** Sales → New Invoice (finished products only — raw kinds are blocked server-side), or Salesmen → Issue Stock (finished products only) — FG stock goes down, the salesman's van stock goes up. FG stock shows in pieces and, when configured, Packs/Bundles/Packets equivalents (12 pcs per pack default; editable per product in Products → Edit → Packaging or in Settings → Units & Packaging).
6. **Watch the numbers:** Dashboard cards (Raw Material Stock / Used Today / Low, Finished Product Stock / Produced Today / Issued Today / Low) · Raw Material Stock page (KG with TON/MAN equivalents) · Finished Product Stock page (with packaging) · Reports → Raw Material Daily / Finished Product Daily / **Production Report** (batch-wise consumption KG, RM cost, labour, overhead, cost per unit; date filter, CSV, print).

**Low stock alerts:** set **Reorder** level on the product card; at/below it the owner/admin/manager get a notification ("LOW STOCK (Raw Material) — ...") with the current qty vs the level, so materials can be bought before the factory runs dry.

**Golden rule reminder:** raw materials are never sold or issued to salesmen — the system blocks it. Anything the factory consumes must go through **Factory Consumption** (Inventory → Factory Consumption) or a production order, so the cost lands in the finished carton's cost.

## Maintenance, repairs & printable vouchers

**Factory Maintenance & Repairs** (sidebar → Expenses & Payroll → **Factory Maintenance & Repairs**) records every machine/equipment/electrical/vehicle repair.

**Recording a repair:**

1. **Maintenance → New Maintenance Record** (or **Assets → + New Asset** to register the machine first).
2. Enter: machine/asset name (pick from the registry or type it), asset ID/code, department, location, problem description, date, work performed, technician/repair shop, spare parts used (qty × rate — rows added with + Add Part), labour charges, other expenses — the form computes the total automatically. Payment method: Cash, Bank, or Credit/Payable (not paid yet). Attach an invoice, receipt, photo or PDF.
3. **It never posts straight to the ledger.** On save it goes to Owner/Admin for approval (Admin Console → Approvals, or the Approve & Post button on the record/list). No journal is created and no cash/bank moves until an Owner/Admin approves. Exactly-once guarantee — approving twice is impossible.
4. On approval, an expense is created under category **Factory Maintenance & Repairs** and posted to the ledger exactly once. Cash/bank is updated (or Accounts Payable when paying on credit).
5. **Reports → Maintenance & Repairs**: totals grouped by day/month/year/machine/department/vendor, full per-machine history, how much each machine costs to repair, CSV export, printable.

**Printing (button 🖨 on every document):**

- Purchase invoices & GRNs (list + detail → Print / Save as PDF)
- Raw material purchases, production reports, stock ledger, sales invoices, returns, receipts, statements (customers & suppliers), payment vouchers, expense vouchers, maintenance records & reports, salary slips, daily/monthly/yearly reports — every report has a Print button.
- **Thermal 80 mm mode** on payment / receipt / expense vouchers (Thermal 80mm button) — for small printers.
- Documents print with company name, logo, NTN/STRN, document number, date and signature lines; menus and buttons are hidden; Urdu works RTL automatically when the language is set to اردو.
- Print uses the browser's print dialog — choose **Save as PDF** to download a PDF copy. Every print shows a preview first.

**Maintaining the asset registry:** **Maintenance → Maintenance Assets** — every machine with asset ID, department, location; shows total repair jobs and cumulative repair cost per machine; history of all repairs is one click away from each record.

## Notifications (real-time)

Look at the **bell icon** in the top bar — the red badge shows how many unread notifications you have (updates within 15 seconds, no refresh needed).

- **Click the bell** to see the latest items (unread first). **View all notifications** opens the full history.
- **Desktop popup**: allow the browser's notification permission — then you get a system popup for urgent events even when the tab is in the background.
- **Sound**: a short chime plays on new notifications; turn off/on from the Notifications page ("Notification Sound: On/Off").
- **Mark read/unread** per item with the button next to it, or **Mark all read**.
- **Notifications open the related record** — click the payment/sale etc.
- **Who gets what**: approvals go to Owner/Admin; sales and low stock go to Owner/Admin/Manager; customer payments and accounting events go to Owner/Admin/Accountant. Salesmen never see approval or payroll notifications.
- **Language**: the notification bell UI follows your language choice (EN / Roman Urdu / اردو).

### Claim Goods — supplier claim receipts

Goods received on a supplier claim: take the delivery, count every unit, split into **Good / Accepted**, **Damaged** and **Rejected** (any product).

1. **Claims → Claim Goods → + New Claim Receipt** — pick supplier, warehouse, date, optional related purchase invoice.
2. Enter **Good Qty**, **Damaged Qty**, **Rejected Qty** and the agreed **Claim Rate** (Rs per accepted unit) for each line. Only the accepted quantities will be posted to stock, valued at the claim rate.
3. Choose payment: **Cash (pay now)** — pick account, posts Dr Inventory / Cr Cash on approval; or **Outstanding — pay later** — posts Dr Inventory / Cr Accounts Payable; pay full or in parts later.
4. Submit → Owner/Admin approves in **Admin Console → Approvals** (stock + journal post exactly once). Partially paying later also needs approval.
5. Totals page shows accepted/damaged/rejected quantities, eligible claim amount, cash paid and outstanding; supplier-wise history below; printable receipt per claim.
6. Damaged/rejected quantities are never added to stock or stock valuation.

## Common v2 scenarios (Admin approval, bonus issue, loans, advances, second login areas)

### Admin Console & Management Console
- **Admin Console** (`/admin`): Owner/Admin sign in and see everything plus the **Payment Approvals inbox** and admin quick actions.
- **Management Console** (`/management`): Managers and Accountants see team performance, receivables, unpaid payroll, and quick links into the modules they are permitted to open.
- Neither area is reachable without the right role — the gate is enforced on the server, not just hidden in the UI.

### Approving or rejecting a payment
1. The requester fills the form (Supplier Payment / Expense / Transfer / Salary / Loan / Advance / Discount / Bonus) and submits.
2. The document is stored as `pending` — nothing posts yet.
3. In the Admin Console open **Approvals Inbox**.
4. Type a note (required to reject) and click **Approve** or **Reject**.
5. **Approve** posts the journal exactly once; **Reject** never touches balances. Both decisions are logged forever in Audit.

### Issuing salesman stock with bonus (Give Bonus? Yes/No)
1. **Salesmen → Issue Stock** — pick salesman, warehouse, date, issue type. **Issue Rate (Rs)** defaults from the product but can be changed per line; it is saved with the issue and never changes retroactively when prices change later. Different lines and different salesmen can carry different rates for the same product.
2. **Give Bonus?** — default **No**. The bonus qty column is hidden and no bonus is given.
3. Choose **Yes** — a per-line **Bonus Qty** column appears plus an optional **Bonus Reason** field (e.g. "festive offer").
4. Add lines: select product, enter **Paid Qty**, optional **Bonus Qty** and issue rate. The footer shows **Total Issued / Paid / Free Bonus / Salesman Payable** live.
5. Bonus qty is deducted from company stock alongside paid qty but posts **zero charge** to the salesman; the bonus cost posts to the **PROMO** expense account — never to his ledger.
6. If the bonus exceeds the **Bonus Approval Threshold** (Settings → Bonus Schemes & Rules) or the scheme is restricted, the whole issue waits for Owner/Admin approval — no stock moves and no journal posts until approved.
7. Review the printable **Stock Issue Receipt** (paid/bonus breakdown, amount in words, bonus reason) and print A4 or thermal.
8. **Reports → Salesman Stock & Settlement** shows issued paid/bonus/cost per salesman per period; **Reports → Bonus & Free Stock** and **Reports → FG Daily** split issue quantities into paid vs bonus.

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

### Recording a salesman's payment (goods collection)
1. **Salesmen -> tap a name -> Payments / Collection** tab.
2. The panel shows: **Goods Issued (Payable)** (paid goods at issue-rate), **Cash Received**, **Bank Received**, **Total Received**, **Return Credit** (good-condition returns) and **Remaining Goods Payable** - all calculated automatically; bonus/free goods never reduce it.
3. **Receive Payment**: date, total, cash amount, bank amount (they must add up to the total - live check), cash account, bank account (required when bank > 0), bank reference/transaction no. (required when bank > 0), notes. Saving posts immediately (money in): Dr Cash / Dr Bank / Cr Salesman Receivable, and credits the salesman's ledger. A unique receipt number (e.g. `SP-00042`) is generated and printable from the history table.
4. Split payments (e.g. Rs 15,000 = Rs 10,000 cash + Rs 5,000 bank) are one payment, one receipt.
5. Only **Owner / Admin / Manager** (`salesmen.settle`) can record; the Accountant (`salesmen.view`) sees the tab but cannot record. Salary, expenses and adjustments are never netted against goods payable.
### Employee loan
1. **Loans → New Loan**: employee, amount, reason, installment type (weekly/monthly), installment amount, payout account.
2. Owner/Admin approves → disbursement posts (Dr Employee Loans, Cr Cash/Bank).
3. Installment schedule auto-creates (e.g. 12 × 2,000).
4. Due installments auto-deduct from payroll; manual repayments recorded via **Payback** on the loan page.
5. Per-employee outstanding/recovered shows on the loan detail; full report via **Reports → Employee Loans**.

## Month-end checklist

1. All sales/purchase/GRN entered; all receipts and payments recorded.
2. Expenses all **posted** (none pending).
3. Payroll paid; advances correctly deducted.
4. Stock Count sheet filed for the month (Inventory → Physical Counts) and resulting adjustment approved.
5. Bank accounts reconciled (Cash & Bank → Reconciliation).
6. Trial Balance agrees; P&L and Balance Sheet print cleanly.
7. Backup generated (Settings → Backup) and a copy downloaded off the server.
8. Fiscal periods: leave open until year-end (Settings → Financial Year → Close Period only for audited months).

## Year-end closing

1. Finish all transactions for the last day of June (FY = July–June).
2. Verify Trial Balance for the year; verify Balance Sheet agrees.
3. **Accounting → Fiscal Year Closing** → type `CLOSE` → Close.
   - System posts two entries: income accounts → Retained Earnings; expenses → Retained Earnings.
   - All 12 periods close; next FY starts at zero income/expense with opening balances intact.
4. Start the new year: the installer already created next FY's periods? No — new FY must be created (Settings → Financial Year → activate; the current template creates the current FY only — see DEPLOYMENT §10 if you need a second FY before July).

## Getting help / reporting an error

Note: the screen/menu, the voucher number (SI-00042, PI-00007…), what you clicked, and the exact error text. The Audit Log entry number helps too (Settings → Audit Log). Send all four to your software administrator — never share passwords.
