<?php $title = __('Return Stock —') . e($sm['name']); $tab = 'return'; require __DIR__ . '/../partials/sm_tabs.php'; ?>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Returned') ?></div><div class="sc-val"><?= qty($retSummary['total_qty'] ?? 0) ?></div><div class="sc-sub"><?= __('Packets') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Good Returned') ?></div><div class="sc-val"><?= qty($retSummary['good_qty'] ?? 0) ?></div><div class="sc-sub"><?= __('restocked, credit issued') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Damaged') ?></div><div class="sc-val" style="color:#8f1d13"><?= qty($retSummary['damaged_qty'] ?? 0) ?></div><div class="sc-sub"><?= __('kept separate, no credit') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Return Credit') ?></div><div class="sc-val"><?= money($retSummary['good_credit'] ?? 0) ?></div><div class="sc-sub"><?= __('value of good returns') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Amount Paid') ?></div><div class="sc-val"><?= money($paidOnReturns) ?></div><div class="sc-sub"><?= __('all-time payments') ?></div></div>
  <div class="stat-chip" style="border-color:#bfe3cd"><div class="sc-lbl"><?= __('Remaining Payable') ?></div><div class="sc-val" style="color:#0a4d29"><?= money(max(0, $goodsPayableBalance)) ?></div><div class="sc-sub"><?= __('salesman still owes') ?></div></div>
</div>

<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('Receive Unsold Stock From') ?><?= e($sm['name']) ?></h2>
<div class="form-grid"><div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div></div>
<label class="f mt"><?= __('Notes (return reason / condition)') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. weekend route return · damaged found')) ?>">
<h3 style="font-size:13px;margin:14px 0 6px"><?= __('Quantities Returned (split paid / bonus — bonus never creates payable)') ?></h3>
<div class="table-wrap"><table class="tbl" id="itemsTable"><thead><tr><th><?= __('Product') ?></th><th><?= __('Condition') ?></th><th class="num"><?= __('Paid Return (Packets)') ?></th><th class="num"><?= __('Bonus Return (Packets)') ?></th><th class="num"><?= __('Issue Rate') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<p class="help"><strong><?= __('Good / Accepted') ?></strong>: <?= __('goods go back to saleable stock and reduce salesman payable.') ?><br><strong><?= __('Damaged') ?></strong>: <?= __('recorded separately, NOT returned to stock, NO payable reversal.') ?></p>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Product') ?></button>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Post Return') ?></button><a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/ledger')) ?>"><?= __('Cancel') ?></a></div>
</form>

<?php if (!empty($history)): ?>
<div class="panel" style="margin-top:14px">
  <h2><?= __('Return History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Return No') ?></th><th class="num"><?= __('Total') ?></th><th class="num"><?= __('Good') ?></th><th class="num"><?= __('Damaged') ?></th><th class="num"><?= __('Credit') ?></th></tr></thead>
    <tbody><?php foreach ($history as $h): ?><tr><td><?= date_display($h['date']) ?></td><td class="mono"><?= e($h['issue_no']) ?></td><td class="num"><?= qty($h['total_qty']) ?></td><td class="num"><?= qty($h['good_qty']) ?></td><td class="num"><?= qty($h['damaged_qty']) ?></td><td class="num"><?= money($h['credit_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<script>
const products = <?= json_encode($stock) ?>;
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[product_id][]" required><option value=""><?= __('— select —') ?></option>${products.map(p=>`<option value="${p.id}" data-rate="${p.issue_rate}">${p.sku} — ${p.name} (van: ${p.qty})</option>`).join('')}</select></td><td><select class="f" name="items[return_condition][]" style="width:110px"><option value="good"><?= __('Good') ?></option><option value="damaged"><?= __('Damaged') ?></option></select></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[paid_qty][]" value="0"></td><td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items[bonus_qty][]" value="0"></td><td><input class="f" style="width:90px" type="number" step="0.01" min="0" name="items[issue_rate][]" value="0.00"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button></td>`; tr.querySelector('select').addEventListener('change', function(){ const c = this.selectedOptions[0]?.dataset.rate; if (c) tr.querySelector('[name$="[issue_rate][]"]').value = c; }); document.querySelector('#itemsTable tbody').appendChild(tr); }
document.getElementById('addRow').addEventListener('click', addRow);
addRow();
</script>
