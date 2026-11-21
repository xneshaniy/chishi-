<?php $title = __('New Manual Journal'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('Manual Journal Entry') ?></h2>
<div class="form-grid"><div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div><div style="grid-column:span 2"><label class="f"><?= __('Memo / Narration') ?></label><input class="f" name="memo" placeholder="<?= e(__('e.g. Month-end accrual — March salaries')) ?>"></div></div>
<div class="table-wrap mt"><table class="tbl" id="linesTable"><thead><tr><th><?= __('Account') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th><th><?= __('Line Memo') ?></th><th></th></tr></thead><tbody></tbody></table></div>
<button class="btn btn-outline btn-sm mt" type="button" id="addRow"><?= __('+ Add Line') ?></button>
<div class="mono mt" style="text-align:right"><?= __('Total Debit:') ?><strong><?= __('Rs') ?><span id="tDebit">0.00</span></strong><?= __('· Total Credit:') ?><strong><?= __('Rs') ?><span id="tCredit">0.00</span></strong><?= __('· Difference:') ?><strong id="tDiff" style="color:var(--red)">0.00</strong></div>
<div class="btn-group mt">
  <button class="btn btn-dark" type="submit" name="action" value="draft"><?= __('Save as Draft') ?></button>
  <button class="btn btn-primary" type="submit" name="action" value="post"><?= __('Save & Post') ?></button>
  <a class="btn btn-outline" href="<?= e(url('accounting/journal')) ?>"><?= __('Cancel') ?></a>
</div>
<p class="help"><?= __('Entries must balance (Σ debit = Σ credit) — the server rejects unbalanced entries. Posted entries are immutable; correct them by reversal.') ?></p>
</form>
<script>
const accounts = <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'label' => $a['code'] . ' — ' . $a['name']], $accounts)) ?>;
function addRow(){ const tr = document.createElement('tr'); tr.innerHTML = `<td><select class="f" name="items[account_id][]" required><option value=""><?= __('— select —') ?></option>${accounts.map(a=>`<option value="${a.id}">${a.label}</option>`).join('')}</select></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="items[debit][]" value="0.00"></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="items[credit][]" value="0.00"></td><td><input class="f" name="items[memo][]" placeholder="<?= e(__('optional')) ?>"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()">✕</button></td>`; document.querySelector('#linesTable tbody').appendChild(tr); }
function recalc(){ let d=0,c=0; document.querySelectorAll('#linesTable tbody tr').forEach(tr => { d += parseFloat(tr.querySelector('[name="items[debit][]"]').value||0); c += parseFloat(tr.querySelector('[name="items[credit][]"]').value||0); }); document.getElementById('tDebit').textContent = d.toFixed(2); document.getElementById('tCredit').textContent = c.toFixed(2); const diff = Math.abs(d-c); const el = document.getElementById('tDiff'); el.textContent = diff.toFixed(2); el.style.color = diff < 0.005 ? 'var(--green)' : 'var(--red)'; }
document.getElementById('addRow').addEventListener('click', addRow);
document.querySelector('#linesTable').addEventListener('input', recalc);
addRow(); addRow();
</script>
