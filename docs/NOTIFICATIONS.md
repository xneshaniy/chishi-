# Notifications — how they work

A real-time notification system for JUSTI NUMBER ONE ERP. Everything about it is permission-aware, deduplicated and stored in the database.

## What notifies whom

| Event | Who gets notified | Type |
|---|---|---|
| Manager/Accountant submits an outgoing payment, transfer, expense, salary, advance, loan, below-minimum discount, or restricted-bonus request | Owner + Admin (+ Manager for purchase/transfer/expense/consumption) | `payment_request` |
| Admin approves/rejects the request | The person who requested it | `approval` |
| Sales invoice posted | Owner + Admin + Manager | `sale` |
| Purchase / GRN posted (stock in) | Owner + Admin + Manager | `purchase` |
| Customer payment received | Owner + Admin + Accountant | `receipt` |
| Salesman deposits money | Owner + Admin + Accountant | `receipt` |
| Goods issued to a salesman | Owner + Admin + Manager | `salesman_issue` |
| Salesman returns goods | Owner + Admin + Manager | `salesman_issue` |
| Salesman signs off a daily settlement | Owner + Admin + Manager | `salesman_settlement` |
| Salesman submits an expense | Owner + Admin | `expense` |
| Factory consumption posted | Owner + Admin + Manager | `factory_issue` |
| Production order created | Owner + Admin + Manager | `production` |
| Production order completed | Owner + Admin + Manager | `production` |
| New employee added | Owner + Admin + Manager | `employee` |
| Attendance marked / updated | Owner + Admin + Manager | `attendance` |
| Low stock (≤ reorder level), checked on dashboard load & after sales/purchases | Owner + Admin + Manager (once per product per day) | `low_stock` |
| Out of stock (zero on hand) | Owner + Admin + Manager (once per product per day) | `out_of_stock` |
| Salesman carries an outstanding goods/collection balance | Owner + Admin + Accountant (once per salesman per day) | `outstanding` |
| Credit invoice past due date (checked on dashboard load) | Owner + Admin + Accountant (once per invoice per day) | `overdue` |
| Supplier/owner payment posted (direct path) | Owner + Admin + Accountant | `supplier_payment` |
| Manual journal posted / reversed / fiscal year closed | Owner + Admin + Accountant | `journal` / `accounting` |

## Admin Notifications Feed

The Admin Console (`/admin/notifications`) shows **every notification stored in the system** — all users' notifications with type filter and search. This is owner/admin-only (server-side gated). It does not show notifications in the normal user interface: per-user visibility on the front-end is role-scoped as listed above.

## Approval Attachments

The Approval Center (`/admin/approvals/{id}`) shows every request in detail — type/amount/requester/date/method/cash-bank account/related party/description/attachment — and exposes the attachment through a hardened `admin/attachment` endpoint (realpath-confined to `storage/`, admin-only).

## How it works technically

- **Storage**: `notifications` table (MySQL/SQLite) — `user_id`, `type`, `title`, `body`, `link`, `entity_type`, `entity_id`, `is_read`, `dedupe_key`, `created_at`. `dedupe_key` is unique **per user** (composite unique index `user_id + dedupe_key`), so duplicate notifications are impossible even under retries.
- **Delivery**: written to the database inside the business-flow's success path (wrapped in try/catch — a notification failure never rolls back a sale, payment or journal).
- **Role resolution**: `Notifications::usersWithRoles()` selects active users holding the required role codes (`owner`, `admin`, `manager`, `accountant`) — salesmen never receive approval/payroll/accounting notifications.
- **Bell**: topbar button with unread badge, server-rendered count. A hidden dropdown shows the latest items (unread first). "Mark all read" marks everything.
- **Real-time**: the browser polls `index.php?r=notifications/unread` every 15 s; no page refresh needed. New unread items appearing **after the page loaded** trigger: badge update, optional chime (Web Audio, user-toggleable), optional desktop popup (Web Notification API, user-toggleable, browser permission required).
- **Silence on navigation**: the server injects `ERP_NOTIF.initialUnreadIds` (the IDs already unread when the page rendered) and the bell script stores known-IDs in `sessionStorage`. Reloading a page or navigating between ERP sections never re-fires the chime / desktop popup for previously-seen notifications — only genuinely new ones, and only when the user has sound enabled.
- **Sound is OFF by default** (`users.notify_sound` defaults to 0). Users opt in from the Notifications page. Visual badge / dropdown / history work regardless.
- **Sound/popup preferences**: stored on the user account (`users.notify_sound`, `users.notify_desktop`) — toggles live on the Notifications page.
- **History**: `Notifications` page shows all notifications with All / Unread / Read filters, mark read/unread per item or in bulk, click-through to the related record (link validated server-side to internal routes only).
- **Exactly-once notifications**: approval notifications fire once per decision because `Approvals::decide()` only runs once per request; sale/purchase/receipt notifications dedupe via `sale:posted:{id}`-style keys, immune to re-posts.

## Notes

- The unread badge works without JavaScript (server-rendered count) but real-time refresh + desktop popup require JavaScript and (for popups) the browser's notification permission. Toggle between EN / Roman Urdu / اردو to see the Notifications UI in your language.
