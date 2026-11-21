<?php $title = 'New Maintenance Record'; ?>
<form method="post" class="panel" enctype="multipart/form-data"><?= csrf_field() ?><?= idempotency_input() ?>
<h2><?= __('Factory Maintenance & Repair Record') ?><span class="muted" style="font-size:12px"><?= __('goes to Owner/Admin for approval before any payment posts') ?></span></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Asset / Machine *') ?></label>
    <input class="f" name="machine_name" id="machineNamePick" list="assetList" placeholder="<?= __('e.g. Cartoning Machine #2') ?>" autocomplete="off" required>
    <datalist id="assetList"><?php foreach ($assets as $a): ?><option value="<?= e($a['name']) ?>" data-id="<?= (int)$a['id'] ?>"><?= e($a['asset_no'] ? $a['name'] . ' — ' . $a['asset_no'] : $a['name']) ?></option><?php endforeach; ?></datalist>
    <input type="hidden" name="asset_id" id="assetIdHidden" value="">
  </div>
  <div><label class="f"><?= __('Asset ID / Code (optional)') ?></label><input class="f" name="asset_no" id="assetNoHidden" placeholder="<?= e(__('e.g. MAC-014')) ?>" value=""></div>
  <div><label class="f"><?= __('Department') ?></label><input class="f" name="department" id="departmentHidden" placeholder="<?= e(__('e.g. Packing')) ?>"></div>
  <div><label class="f"><?= __('Location') ?></label><input class="f" name="location" id="locationHidden" placeholder="<?= e(__('e.g. Main factory, Line 2')) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Problem / Damage Description *') ?></label><textarea class="f" name="problem" rows="2" required placeholder="<?= e(__('What happened? e.g. Main bearing worn out, belt broken…')) ?>"></textarea></div>
  <div><label class="f"><?= __('Date Problem Occurred *') ?></label><input class="f" type="date" name="problem_date" value="<?= e(today()) ?>" required></div>
  <div><label class="f"><?= __('Work Date') ?></label><input class="f" type="date" name="work_date" value="<?= e(today()) ?>"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Repair / Maintenance Work Performed') ?></label><textarea class="f" name="work_performed" rows="2" placeholder="<?= e(__('Describe the repair work done…')) ?>"></textarea></div>
  <div><label class="f"><?= __('Technician / Repair Shop') ?></label><input class="f" name="vendor" placeholder="<?= e(__('e.g. Khan Engineering Works')) ?>"></div>
  <div><label class="f"><?= __('Payment Method') ?></label><select class="f" name="payment_type" id="payment_type">
    <option value="cash" selected><?= __('Cash') ?></option>
    <option value="bank"><?= __('Bank') ?></option>
    <option value="credit"><?= __('Credit / Payable (not paid yet)') ?></option>
  </select></div>
  <div id="accountWrap"><label class="f"><?= __('Cash / Bank Account *') ?></label><select class="f" name="account_id"><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e(strtoupper($a['type'])) ?>)</option><?php endforeach; ?></select></div>
</div>

<h3 style="margin-top:14px"><?= __('Spare Parts Used') ?></h3>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Part') ?></th><th class="num"><?= __('Qty') ?></th><th class="num"><?= __('Unit Cost') ?></th><th class="num"><?= __('Amount') ?></th><th></th></tr></thead>
<tbody id="partsBody"><tr><td><input class="f" name="parts[name][]" placeholder="<?= e(__('e.g. Main bearing')) ?>"></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0" name="parts[qty][]" value="1"></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="parts[unit_cost][]" value="0.00"></td><td class="line-total num">0.00</td><td></td></tr></tbody>
</table></div>
<button class="btn btn-outline btn-sm" type="button" id="addPart"><?= __('+ Add Part') ?></button>

<div class="form-grid" style="margin-top:14px">
  <div><label class="f"><?= __('Parts Total') ?></label><input class="f" id="partsTotal" value="0.00" readonly></div>
  <div><label class="f"><?= __('Labour Charges') ?></label><input class="f" type="number" step="0.01" min="0" name="labour_cost" id="labourCost" value="0.00"></div>
  <div><label class="f"><?= __('Other Expenses') ?></label><input class="f" type="number" step="0.01" min="0" name="other_cost" id="otherCost" value="0.00"></div>
  <div><label class="f"><?= __('TOTAL COST') ?></label><input class="f" id="grandTotal" style="font-weight:800;font-size:16px;color:var(--charcoal)" value="0.00" readonly></div>
</div>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Attachment — invoice / receipt / repair photo / PDF (optional)') ?></label><input class="f" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"></div>
</div>
<p class="help"><?= __('After saving, the record goes to Owner/Admin for approval (Admin Console → Approvals). No expense journal posts, and no cash/bank moves, until it is approved. The approved expense uses category "Factory Maintenance & Repairs" and posts exactly once.') ?></p>
<div class="btn-group"><button class="btn btn-primary" type="submit"><?= __('Save Maintenance Record') ?></button><a class="btn btn-outline" href="<?= e(url('maintenance')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const pick = document.getElementById('machineNamePick');
const hiddenId = document.getElementById('assetIdHidden');
const hiddenNo = document.getElementById('assetNoHidden');
const hiddenDept = document.getElementById('departmentHidden');
const hiddenLoc = document.getElementById('locationHidden');
const assets = [<?php foreach ($assets as $a): ?>{id: <?= (int)$a['id'] ?>, name: <?= json_encode($a['name']) ?>, asset_no: <?= json_encode($a['asset_no']) ?>, department: <?= json_encode($a['department']) ?>, location: <?= json_encode($a['location']) ?>},<?php endforeach; ?>];
pick.addEventListener('input', () => {
  const a = assets.find(x => x.name.toLowerCase() === pick.value.toLowerCase());
  hiddenId.value = a ? a.id : '';
  hiddenNo.value = a ? (a.asset_no || '') : (a ? '' : hiddenNo.value);
  hiddenDept.value = a ? (a.department || '') : hiddenDept.value;
  hiddenLoc.value = a ? (a.location || '') : hiddenLoc.value;
});
function fmt(n){ return (isFinite(n)?n:0).toFixed(2); }
function recalc(){
  let parts = 0;
  document.querySelectorAll('#partsBody tr').forEach(tr => {
    const q = parseFloat(tr.querySelector('[name*="[qty]"]').value||0), c = parseFloat(tr.querySelector('[name*="[unit_cost]"]').value||0);
    const lt = q*c; tr.querySelector('.line-total').textContent = lt.toFixed(2); parts += lt;
  });
  document.getElementById('partsTotal').value = fmt(parts);
  const lab = parseFloat(document.getElementById('labourCost').value||0), oth = parseFloat(document.getElementById('otherCost').value||0);
  document.getElementById('grandTotal').value = fmt(parts + lab + oth);
}
function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = '<td><input class="f" name="parts[name][]" placeholder="<?= e(__('e.g. Main bearing')) ?>"></td><td><input class="f" style="width:90px" type="number" step="0.001" min="0" name="parts[qty][]" value="1"></td><td><input class="f" style="width:110px" type="number" step="0.01" min="0" name="parts[unit_cost][]" value="0.00"></td><td class="line-total num">0.00</td><td><button class="btn btn-danger btn-sm" type="button" onclick="this.closest(\'tr\').remove();recalc()">✕</button></td>';
  document.getElementById('partsBody').appendChild(tr);
  tr.querySelectorAll('input').forEach(i => i.addEventListener('input', recalc));
}
document.getElementById('partsBody').addEventListener('input', recalc);
document.getElementById('labourCost').addEventListener('input', recalc);
document.getElementById('otherCost').addEventListener('input', recalc);
document.getElementById('addPart').addEventListener('click', addRow);
document.getElementById('payment_type').addEventListener('change', function(){
  document.getElementById('accountWrap').style.display = this.value === 'credit' ? 'none' : '';
});
</script>
