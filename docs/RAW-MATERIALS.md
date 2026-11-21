# Raw Material Purchase & Daily Usage — Design Notes

Owner/Admin owns the raw-material financial ledger; Managers see stock and may only log daily KG consumption. Production history is never touched twice.

## 1. Units & conversions
- Purchase units: **TON (1000 KG)**, **MAN (40 KG)**, **KG** — configurable in Settings → Units (`rm_unit_ton`, `rm_unit_man`).
- KG is the internal stock unit. Quantities are stored **per line** on `purchase_items` as (`unit_short`, `qty`, `qty_kg`) with `unit_cost` = price per **original purchase unit** — converting settings later never rewrites history.
- `UnitConverter::toKg()` converts for stock; `fromKg()` displays TON/MAN equivalents.

## 2. Owner/Admin-only raw-material purchases
- Raw/packaging purchases flow via the normal GRN screen, but the ledger page (`/purchase/raw`) and all financial columns are restricted to Owner/Admin.
- Manager-created GRNs and POs containing raw/packaging products are refused outright (server-side) — not even as approval requests.
- Invoice views for raw invoices are blocked for non-Owner/Admin.
- All supplier-payment approvals follow the existing Payment Approval flow unchanged.

## 3. Daily Raw Material Usage (/inventory/raw-usage)
- One row per (`product_id`, `usage_date`, `warehouse_id`) — `rm_daily_usage`. The UI enforces it; the DB unique index enforces it; re-saving the same day adjusts the existing row by the delta (with an audit entry), never duplicates it.
- Manager enters KG only. Saving calls `Inventory::issue()` instantly, so raw stock drops exactly once. Cancelling (Owner/Admin only) returns the stock exactly once.
- Recorder is stored on each row (`recorded_by`), visible to both roles; edits always overwrite with the acting user.
- Available stock is shown live in the form (per selected warehouse); saving beyond available stock errors out with the exact KG available.
- Full audit history: row creation, corrections (delta +/-), cancels, note changes.

## 4. Reports (/inventory/raw-usage/report)
- Date range + material filter. Material-wise usage, monthly totals, daily entries — print button included.

## 5. Guards & exactly-once
- `Inventory::issue()` is the single deduction point; the unique index on `rm_daily_usage` prevents duplicate rows for the same day/warehouse/material; cancel is an explicit, audited one-shot return.
- Production consumption remains exclusively via `factory_issues` (+ `production_inputs` where no factory issue exists) — nothing in the new flow deducts that stock. Both aggregate into the Raw Material Stock view's *Used* and *Used Today* columns.
