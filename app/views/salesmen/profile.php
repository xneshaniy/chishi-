<?php
$title = __('Salesman') . ' — ' . e($sm['name']);
$tab = 'biodata';
require __DIR__ . '/../partials/sm_tabs.php';
$issuedValue = (float)($goods['issued_value'] ?? 0);
$bonusValue = (float)($goods['issued_bonus'] ?? 0);
?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:10px">
  <div class="greet" style="display:flex;gap:16px;align-items:center">
<?php $imgSrc = !empty($sm['photo_path']) ? photo_data_uri($sm['photo_path']) : null; ?>
    <div style="text-align:center">
      <img id="photoPreview" class="profile-photo" style="width:96px;height:96px;border-radius:50%;object-fit:cover;<?= $imgSrc ? '' : 'display:none' ?>" alt="<?= e($sm['name']) ?>"<?= $imgSrc ? ' src="' . e($imgSrc) . '"' : '' ?>>
      <div class="profile-avatar" id="photoAvatar" style="width:96px;height:96px;border-radius:50%;font-size:36px;<?= $imgSrc ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($sm['name'], 0, 1))) ?></div>
      <?php if (Auth::can('salesmen.edit')): ?>
        <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/photo')) ?>" enctype="multipart/form-data" style="margin-top:8px">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$sm['id'] ?>">
          <label class="btn btn-outline btn-sm" for="photoInput" style="cursor:pointer">📷 <?= __('Change Photo') ?></label>
          <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" style="display:none">
          <button class="btn btn-primary btn-sm" type="submit"><?= __('Save Photo') ?></button>
        </form>
      <?php endif; ?>
    </div>
    <div style="flex:1">
      <span style="font-size:22px;font-weight:800"><?= e($sm['name']) ?></span><br><small><?= e($sm['code']) ?> · <?= e($sm['area'] ?: '—') ?><?= $sm['phone'] ? ' · <span style="margin-left:2px">' . __('Phone') . ':</span> ' . e($sm['phone']) : '' ?> · <?= __('Joined') ?> <?= e($sm['join_date'] ? date_display($sm['join_date']) : '—') ?></small><br><span class="badge <?= $sm['is_active'] ? 'green' : 'red' ?>" style="margin-top:6px"><?= $sm['is_active'] ? __('Active') : __('Inactive') ?></span></div>
    <div class="btn-group" style="flex-wrap:wrap;gap:6px;justify-content:flex-end">
      <?php if (Auth::can('salesmen.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/edit')) ?>"><?= __('Edit') ?></a><?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/statement')) ?>" target="_blank">🖨 <?= __('Statement') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/ledger')) ?>"><?= __('Ledger') ?></a>
      <?php if (is_owner_or_admin()): ?>
      <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/toggle')) ?>" style="display:contents" data-confirm="<?= $sm['is_active'] ? e(__('Deactivate this profile? It stays inactive until an Owner/Admin reactivates it.')) : e(__('Activate this profile?')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$sm['id'] ?>">
        <input type="hidden" name="back" value="salesmen/<?= (int)$sm['id'] ?>/profile">
        <button class="btn <?= $sm['is_active'] ? 'btn-outline' : 'btn-dark' ?> btn-sm" type="submit"><?= $sm['is_active'] ? __('Deactivate') : __('Activate') ?></button>
      </form>
      <button class="btn btn-danger btn-sm" type="button" onclick="openMarkLeftModal('salesmen', <?= (int)$sm['id'] ?>, '<?= e(addslashes($sm['name'])) ?>')">🚪 <?= __('Left / Mark Departed / Left') ?></button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Current Stock') ?></div><div class="sc-val"><?= qty($stockRow['qty']) ?></div><div class="sc-sub"><?= __('Packets') ?> · <a href="<?= e(url('reports/company-stock', ['date' => today(), 'salesman_id' => (int)$sm['id']])) ?>"><?= __('Daily van stock report →') ?></a></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Issued') ?></div><div class="sc-val"><?= qty($goods['issued_units']) ?></div><div class="sc-sub"><?= __('Packets') ?> · <?= money($issuedValue) ?> <?= __('issued value') ?></div></div>
  <div class="stat-chip" style="border-color:#bfe3cd"><div class="sc-lbl"><?= __('Goods Payable') ?></div><div class="sc-val" style="color:#0a4d29"><?= money($goodsPayable) ?></div><div class="sc-sub"><?= __('salesman owes') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bonus Issued') ?></div><div class="sc-val"><?= qty($bonusValue) ?></div><div class="sc-sub"><?= __('free Packets given') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Expenses') ?></div><div class="sc-val"><?= money($expenseTotal) ?></div><div class="sc-sub"><?= __('posted only') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __(($sm['salary_frequency'] ?? 'monthly') === 'weekly' ? 'Weekly Salary' : 'Monthly Salary') ?></div><div class="sc-val"><?= money($sm['salary']) ?></div><div class="sc-sub">/<?= e(__(($sm['salary_frequency'] ?? 'monthly') === 'weekly' ? 'week' : 'month')) ?> · <?= e(ucfirst($sm['salary_frequency'] ?? 'monthly')) ?></div></div>
</div>

<div class="two-col" style="margin-top:12px">
  <div class="panel">
    <h2><?= __('Biodata') ?></h2>
    <div class="form-grid">
      <div><div class="f-lbl"><?= __('Contact Number') ?></div><div><?= e($sm['phone'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('CNIC Number') ?></div><div><?= e($sm['cnic'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Province') ?></div><div><?= e($province ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('City') ?></div><div><?= e($city ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Area / Locality') ?></div><div><?= e($area ?: ($sm['area'] ?: '—')) ?></div></div>
      <div><div class="f-lbl"><?= __('Joining Date') ?></div><div><?= e($sm['join_date'] ? date_display($sm['join_date']) : '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Assigned Customers') ?></div><div><?= (int)$assignedCustomers ?></div></div>
      <div><div class="f-lbl"><?= __('Commission') ?></div><div><?= qty($sm['commission_percent']) ?>%</div></div>       <div><div class="f-lbl"><?= __('Salary Payment Frequency') ?></div><div><?= e(ucfirst($sm['salary_frequency'] ?? 'monthly')) ?> · <?= money($sm['salary']) ?></div></div>
      <?php if (!empty($sm['vehicle_type'])): ?><div><div class="f-lbl"><?= __('Vehicle') ?></div><div><?= e($sm['vehicle_type']) ?><?= !empty($sm['vehicle_reg']) ? ' · ' . e($sm['vehicle_reg']) : '' ?></div></div><?php endif; ?>
      <div class="span-2"><div class="f-lbl"><?= __('Full Address') ?></div><div><?= e($sm['address'] ?: '—') ?></div></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/statement')) ?>" target="_blank">🖨 <?= __('Printable Statement') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/account')) ?>"><?= __('Full Account') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/settlement')) ?>"><?= __('Settlement') ?></a>
    </div>
  </div>

  <div class="panel">
    <h2><?= __('Documents & Photos') ?></h2>
    <?php if (Auth::can('salesmen.edit')): ?>
    <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/documents')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$sm['id'] ?>">
      <input class="f" type="file" name="documents[]" multiple accept=".pdf,image/jpeg,image/png,image/webp,image/gif" style="flex:1;min-width:200px">
      <button class="btn btn-outline" type="submit"><?= __('Upload document') ?></button>
    </form>
    <?php endif; ?>
    <?php if ($documents): ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($documents as $d): ?>
          <div class="doc-row">
            <div class="doc-ic"><?= e(strtoupper(pathinfo($d['original_name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;word-break:break-all"><?= e($d['original_name']) ?></div>
              <div class="muted" style="font-size:11px"><?= number_format($d['size_bytes'] / 1024, 0) ?> KB · <?= e(dt_display($d['created_at'])) ?></div>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/documents/' . (int)$d['id'] . '/file')) ?>" target="_blank"><?= __('View') ?></a>
            <?php if (Auth::can('salesmen.edit')): ?>
              <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/documents/' . (int)$d['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this document?')) ?>" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$sm['id'] ?>"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>">
                <button class="btn btn-delete-outline btn-sm" type="submit"><?= __('Delete') ?></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="panel" style="margin:0;padding:10px"><p class="muted" style="margin:0"><?= __('No documents uploaded yet.') ?></p></div>
    <?php endif; ?>
    <h3 style="font-size:13px;margin:16px 0 8px"><?= __('CNIC Pictures') ?></h3>
    <div style="display:flex;gap:14px;flex-wrap:wrap">
      <?php foreach (['cnic_front_path' => 'CNIC Front', 'cnic_back_path' => 'CNIC Back'] as $col => $lbl): ?>
        <div class="doc-row" style="flex-direction:column;align-items:flex-start;gap:6px">
          <div class="f-lbl"><?= e(__($lbl)) ?></div>
          <?php if (!empty($sm[$col])): ?>
            <?php if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $sm[$col])): ?>
              <a href="<?= e(url('salesmen/' . (int)$sm['id'] . '/file/' . $col)) ?>" target="_blank" style="opacity:.9"><img src="<?= e(url('salesmen/' . (int)$sm['id'] . '/file/' . $col)) ?>" alt="CNIC" style="width:160px;border-radius:8px;border:1px solid var(--line)"></a>
            <?php else: ?><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/file/' . $col)) ?>" target="_blank"><?= __('View document') ?></a><?php endif; ?>
          <?php else: ?><span class="muted"><?= __('Not uploaded') ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');</script>

<!-- Mark as Left / Archive Modal -->
<div id="markLeftModal" class="erp-modal-overlay no-print" hidden style="display:none;position:fixed;inset:0;background:rgba(10,20,13,.55);z-index:999;align-items:center;justify-content:center">
  <div class="panel" style="width:100%;max-width:440px;margin:auto;box-shadow:0 10px 25px rgba(0,0,0,.25);background:#fff;border-radius:12px;padding:22px">
    <h3 style="margin-top:0;color:#991b1b">🚪 <?= __('Mark as Left / Mark Departed / Left') ?></h3>
    <p class="muted" style="font-size:13px;margin-bottom:14px"><?= __('Record departure of ') ?><strong id="leftTargetName"></strong><?= __('. This will safely remove them from the active list and preserve their full historical record in the Records archive.') ?></p>
    <form method="post" action="<?= e(url('records/mark-left')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="type" id="leftTargetType" value="salesmen">
      <input type="hidden" name="id" id="leftTargetId" value="">
      <div style="margin-bottom:12px">
        <label class="f" style="font-weight:600;margin-bottom:4px"><?= __('Leaving Date *') ?></label>
        <input class="f" type="date" name="left_date" value="<?= today() ?>" required>
      </div>
      <div style="margin-bottom:16px">
        <label class="f" style="font-weight:600;margin-bottom:4px"><?= __('Leaving Reason *') ?></label>
        <input class="f" type="text" name="reason" required placeholder="<?= e(__('e.g. Resigned / Left job / Replaced')) ?>">
      </div>
      <div class="btn-group" style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="closeMarkLeftModal()"><?= __('Cancel') ?></button>
        <button type="submit" class="btn btn-danger"><?= __('Confirm & Move to Records') ?></button>
      </div>
    </form>
  </div>
</div>
<script>
function openMarkLeftModal(type, id, name) {
  var modal = document.getElementById('markLeftModal');
  document.getElementById('leftTargetType').value = type;
  document.getElementById('leftTargetId').value = id;
  document.getElementById('leftTargetName').textContent = name;
  modal.style.display = 'flex';
  modal.hidden = false;
}
function closeMarkLeftModal() {
  var modal = document.getElementById('markLeftModal');
  modal.style.display = 'none';
  modal.hidden = true;
}
</script>
