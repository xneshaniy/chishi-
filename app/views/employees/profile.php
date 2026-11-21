<?php
$title = $employee['name'];
$tab = $tab ?? 'profile';
require __DIR__ . '/../partials/emp_tabs.php';
$imgSrc = !empty($employee['photo_path']) ? photo_data_uri($employee['photo_path']) : null;
$monthly = (float)$employee['salary_rate'];
$remaining = max(0, $salaryRemaining);
$attendanceBase = max(0, (int)($att['present'] ?? 0) + (int)($att['half_day'] ?? 0) * 0.5);
?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:10px">
  <div class="greet" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <?php $imgSrc = $imgSrc; ?>
    <div style="text-align:center;min-width:110px">
      <img id="photoPreview" class="profile-photo" style="width:110px;height:110px;border-radius:50%;object-fit:cover;<?= $imgSrc ? '' : 'display:none' ?>" alt="<?= e($employee['name']) ?>"<?= $imgSrc ? ' src="' . e($imgSrc) . '"' : '' ?>>
      <div class="profile-avatar" id="photoAvatar" style="width:110px;height:110px;border-radius:50%;font-size:40px;<?= $imgSrc ? 'display:none' : '' ?>"><?= e(mb_strtoupper(mb_substr($employee['name'], 0, 1))) ?></div>
      <?php if (Auth::can('employees.edit')): ?>
        <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/photo')) ?>" enctype="multipart/form-data" style="margin-top:6px">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">
          <label class="btn btn-outline btn-sm" for="photoInput" style="cursor:pointer">📷 <?= __('Change Photo') ?></label>
          <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" style="display:none">
          <button class="btn btn-primary btn-sm" type="submit"><?= __('Save Photo') ?></button>
        </form>
      <?php endif; ?>
    </div>
    <div style="flex:1">
      <span style="font-size:22px;font-weight:800"><?= e($employee['name']) ?></span><br><small><?= e($employee['code']) ?> · <?= e($employee['designation'] ?: __('Employee')) ?><?= $employee['department'] ? ' · ' . e($employee['department']) : '' ?> · <?= __('Joined') ?> <?= e($employee['join_date'] ? date_display($employee['join_date']) : '—') ?> · <?= __('Phone') ?> <?= e($employee['phone'] ?: '—') ?></small><br><span class="badge <?= $employee['is_active'] ? 'green' : 'red' ?>" style="margin-top:6px"><?= $employee['is_active'] ? __('Active') : __('Inactive') ?></span></div>
    <div class="btn-group" style="flex-wrap:wrap;gap:6px;justify-content:flex-end">
      <?php if (Auth::can('employees.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('employees/' . (int)$employee['id'] . '/edit')) ?>"><?= __('Edit') ?></a><?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$employee['id'] . '/ledger')) ?>"><?= __('Complete Ledger') ?></a>
      <?php if (is_owner_or_admin()): ?>
      <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/toggle')) ?>" style="display:contents" data-confirm="<?= $employee['is_active'] ? e(__('Deactivate this profile? It stays inactive until an Owner/Admin reactivates it.')) : e(__('Activate this profile?')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">
        <button class="btn <?= $employee['is_active'] ? 'btn-outline' : 'btn-dark' ?> btn-sm" type="submit"><?= $employee['is_active'] ? __('Deactivate') : __('Activate') ?></button>
      </form>
      <button class="btn btn-danger btn-sm" type="button" onclick="openMarkLeftModal('employees', <?= (int)$employee['id'] ?>, '<?= e(addslashes($employee['name'])) ?>')">🚪 <?= __('Left / Mark Departed / Left') ?></button>
      <?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('employees')) ?>"><?= __('All Employees') ?></a>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <div class="stat-chip"><div class="sc-lbl"><?= __(($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly') === 'weekly' ? 'Weekly Salary' : 'Monthly Salary') ?></div><div class="sc-val"><?= money($monthly) ?></div><div class="sc-sub"><?= ucfirst($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly') ?> <?= __('frequency') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Salary Paid') ?></div><div class="sc-val"><?= money($salaryPaid) ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Salary Remaining') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($remaining) ?></div><div class="sc-sub"><?= __('accrued − paid') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($advancesOut) ?></div><div class="sc-sub"><?= __('of') ?> <?= money($advancesTotal) ?> <?= __('given') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Present') ?></div><div class="sc-val"><?= (int)$att['present'] ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Half Days') ?></div><div class="sc-val"><?= (int)$att['half_day'] ?></div><div class="sc-sub"><?= __('half day units') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Absent') ?></div><div class="sc-val" style="color:#8f1d13"><?= (int)$att['absent'] ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Deductions') ?></div><div class="sc-val"><?= money($deductionsTotal) ?></div><div class="sc-sub"><?= __('total deductions') ?></div></div>
</div>

<?php if ($tab === 'profile'): ?>
<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <h2><?= __('Biodata') ?></h2>
    <div class="form-grid">
      <div><div class="f-lbl"><?= __('Full Name') ?></div><div><?= e($employee['name']) ?></div></div>
      <div><div class="f-lbl"><?= __('Employee ID') ?></div><div><?= e($employee['code']) ?></div></div>
      <div><div class="f-lbl"><?= __('Job Title') ?></div><div><?= e($employee['designation'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Department') ?></div><div><?= e($employee['department'] ?: '—') ?></div></div>       <div><div class="f-lbl"><?= __('Salary Payment Frequency') ?></div><div><?= e(ucfirst($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly')) ?></div></div>
      <div><div class="f-lbl"><?= __('Contact Number') ?></div><div><?= e($employee['phone'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('CNIC') ?></div><div><?= e($employee['cnic'] ?: '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Joining Date') ?></div><div><?= e($employee['join_date'] ? date_display($employee['join_date']) : '—') ?></div></div>
      <div><div class="f-lbl"><?= __('Employment Status') ?></div><div><?= $employee['is_active'] ? __('Active') : __('Inactive') ?></div></div>
      <div class="span-2"><div class="f-lbl"><?= __('Address') ?></div><div><?= e($employee['address'] ?: '—') ?></div></div>
    </div>
  </div>
  <div class="panel">
    <h2><?= __('Documents') ?></h2>
    <?php if (Auth::can('employees.edit')): ?>
    <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/documents')) ?>" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">
      <input class="f" type="file" name="documents[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" style="flex:1;min-width:200px">
      <button class="btn btn-outline" type="submit"><?= __('Upload') ?></button>
    </form>
    <?php endif; ?>
    <?php if ($documents): ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($documents as $d): ?>
          <div class="doc-row">
            <div class="doc-ic"><?= e(strtoupper(pathinfo($d['original_name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></div>
            <div style="flex:1;min-width:0"><div class="doc-name" style="word-break:break-all"><?= e($d['original_name']) ?></div><div class="muted" style="font-size:11px"><?= number_format($d['size_bytes'] / 1024, 0) ?> KB · <?= e(dt_display($d['created_at'])) ?></div></div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$employee['id'] . '/documents/' . (int)$d['id'] . '/file')) ?>" target="_blank"><?= __('View') ?></a>
            <?php if (Auth::can('employees.edit')): ?><form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/documents/' . (int)$d['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this document?')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><button class="btn btn-delete-outline btn-sm" type="submit"><?= __('Delete') ?></button></form><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?><p class="muted"><?= __('No documents uploaded.') ?></p><?php endif; ?>
    <h3 style="font-size:13px;margin:14px 0 8px"><?= __('CNIC Pictures') ?></h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php foreach (['cnic_front_path' => 'CNIC Front', 'cnic_back_path' => 'CNIC Back'] as $col => $lbl): ?>
        <div class="doc-row" style="flex-direction:column;align-items:flex-start;gap:6px"><div class="f-lbl"><?= e(__($lbl)) ?></div><?php if (!empty($employee[$col])): ?><?php if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $employee[$col])): ?><a href="<?= e(url('employees/' . (int)$employee['id'] . '/file/' . $col)) ?>" target="_blank"><img src="<?= e(photo_data_uri($employee[$col])) ?>" alt="CNIC" style="width:150px;border-radius:8px;border:1px solid var(--line)"></a><?php else: ?><a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$employee['id'] . '/file/' . $col)) ?>" target="_blank"><?= __('View document') ?></a><?php endif; ?><?php else: ?><span class="muted"><?= __('Not uploaded') ?></span><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'salary'): ?>
<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <h2><?= __('Set Salary') ?></h2>
    <p class="help"><?= __('Current salary: ') ?><strong><?= e(ucfirst($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly')) ?> <?= money($employee['salary_rate']) ?></strong></p>
    <?php if (is_owner_or_admin()): ?>
    <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/salary/set')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">
      <div><label class="f"><?= __('Salary Payment Frequency') ?></label><select class="f" name="salary_type"><option value="monthly" <?= ($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>><?= __('Monthly') ?></option><option value="weekly" <?= ($employee['salary_frequency'] ?? $employee['salary_type'] ?? '') === 'weekly' ? 'selected' : '' ?>><?= __('Weekly') ?></option></select></div>
      <div><label class="f"><?= __('Rate (Rs)') ?> *</label><input class="f" type="number" step="0.01" min="0.01" name="salary_rate" required value="<?= e($employee['salary_rate']) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Effective From') ?></label><input class="f" type="date" name="effective_date" value="<?= e(today()) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Note') ?></label><input class="f" name="note" placeholder="<?= e(__('e.g. annual increment')) ?>"></div>
      <div class="span-2"><button class="btn btn-primary" type="submit"><?= __('Save Salary') ?></button></div>
    </form>
    <?php endif; ?>
    <?php if ($salaryChanges): ?><h3 style="font-size:13px;margin:14px 0 6px"><?= __('Salary History') ?></h3><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Effective') ?></th><th class="num"><?= __('From') ?></th><th class="num"><?= __('To') ?></th><th><?= __('Type') ?></th></tr></thead>
      <tbody><?php foreach ($salaryChanges as $sc): ?><tr><td><?= date_display($sc['effective_date']) ?></td><td class="num"><?= money($sc['old_rate'] ?? 0) ?></td><td class="num"><?= money($sc['new_rate']) ?></td><td><?= e($sc['salary_type']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Recent Payslips') ?></h2>
    <?php if (!$recentPayslips): ?><p class="muted"><?= __('No payslips yet. Generate from Payroll Run.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Period') ?></th><th class="num"><?= __('Net Pay') ?></th><th class="num"><?= __('Paid') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($recentPayslips as $p): ?><tr><td><?= date_display($p['period_start']) ?> → <?= date_display($p['period_end']) ?></td><td class="num"><?= money($p['net_pay']) ?></td><td class="num"><?= money($p['amount_paid']) ?></td><td class="num"><?= money(max(0, $p['net_pay'] - $p['amount_paid'])) ?></td><td><span class="badge <?= $p['status'] === 'paid' ? 'green' : ($p['status'] === 'partial' ? 'amber' : 'gray') ?>"><?= e(strtoupper($p['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <a class="btn btn-primary mt" href="<?= e(url('payroll')) ?>"><?= __('Open Payroll →') ?></a>
  </div>
</div>

<?php elseif ($tab === 'attendance'): ?>
<div class="panel" style="margin-top:14px">
  <h2><?= __('Mark Attendance') ?></h2>
  <?php $todayMarked = null; foreach ($recentAttendance as $r) { if ($r['date'] === today()) { $todayMarked = $r['status']; break; } } ?>
  <div class="att-btns" style="max-width:420px">
    <?php foreach (['present' => ['Present','present'], 'half_day' => ['Half Day','leave'], 'absent' => ['Absent','absent']] as $status => [$lbl,$cls]): ?>
      <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/attendance/mark')) ?>" style="display:contents">
        <?= csrf_field() ?><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="date" value="<?= e(today()) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
        <button class="att-btn <?= e($cls) ?><?= $todayMarked === $status ? ' on' : '' ?>" type="submit"><?= e(__($lbl)) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Recent Attendance') ?></h3>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Status') ?></th></tr></thead>
    <tbody><?php foreach ($recentAttendance as $r): ?><tr><td><?= date_display($r['date']) ?></td><td><span class="badge <?= $r['status'] === 'present' ? 'green' : ($r['status'] === 'absent' ? 'red' : 'amber') ?>"><?= e(strtoupper($r['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$recentAttendance): ?><tr><td colspan="2" class="muted"><?= __('No attendance recorded.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>

<?php elseif ($tab === 'advances'): ?>
<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <h2><?= __('Give Advance') ?></h2>
    <p class="help"><?= __('Advances reduce salary. All advance payments require Owner/Admin approval before posting.') ?></p>
    <a class="btn btn-primary btn-xl" style="text-align:center" href="<?= e(url('employees/' . (int)$employee['id'] . '/advance')) ?>">+ <?= __('Request Advance') ?></a>
    <div class="stat-strip" style="margin-top:12px;grid-template-columns:1fr;max-width:320px">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Advance Outstanding') ?></div><div class="sc-val"><?= money($advancesOut) ?></div><div class="sc-sub"><?= __('of') ?> <?= money($advancesTotal) ?> <?= __('given') ?></div></div>
    </div>
  </div>
  <div class="panel">
    <h2><?= __('All Advances') ?></h2>
    <?php $allAdv = db_all('SELECT * FROM employee_advances WHERE employee_id = ? ORDER BY date DESC, id DESC LIMIT 40', [(int)$employee['id']]); ?>
    <?php if (!$allAdv): ?><p class="muted"><?= __('No advances yet.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Amount') ?></th><th class="num"><?= __('Deducted') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($allAdv as $a): ?><tr><td><?= date_display($a['date']) ?></td><td class="num"><?= money($a['amount']) ?></td><td class="num"><?= money($a['deducted']) ?></td><td class="num"><?= money(max(0, $a['amount'] - $a['deducted'])) ?></td><td><span class="badge <?= $a['status'] === 'disbursed' ? 'blue' : ($a['status'] === 'pending' ? 'amber' : 'gray') ?>"><?= e(strtoupper($a['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'bonus'): ?>
<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <h2><?= __('Give Bonus') ?></h2>
    <p class="help"><?= __('Bonus goes through the existing Owner/Admin approval workflow before any cash is paid.') ?></p>
    <?php if (Auth::can('employees.edit')): ?>
    <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/bonus/add')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">
      <div><label class="f"><?= __('Bonus Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
      <div class="span-2"><label class="f"><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. Eid bonus / annual increment / performance')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Payment Method') ?></label><select class="f" name="payment_method"><option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option></select></div>
      <div class="span-2"><label class="f"><?= __('Pay Out From *') ?></label><select class="f" name="account_id" required><option value=""><?= __('— select —') ?></option><?php foreach (db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name") as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
      <div class="span-2"><button class="btn btn-primary" type="submit">+ <?= __('Request Bonus') ?></button></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Bonus History') ?> <span class="muted" style="font-size:11px;font-weight:400"><?= money($bonusTotal) ?> <?= __('given') ?></span></h2>
    <?php if (!$bonusRows): ?><p class="muted"><?= __('No bonuses recorded yet.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Reason') ?></th><th><?= __('Status') ?></th></tr></thead>
        <tbody><?php foreach ($bonusRows as $b): ?><tr><td><?= date_display($b['date']) ?></td><td class="num"><?= money($b['amount']) ?></td><td><?= e($b['reason']) ?></td><td><span class="badge <?= $b['status'] === 'posted' ? 'green' : ($b['status'] === 'pending' ? 'amber' : ($b['status'] === 'cancelled' ? 'gray' : 'red')) ?>"><?= e(strtoupper($b['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'deductions'): ?>
<div class="two-col" style="margin-top:14px">
  <div class="panel">
    <h2><?= __('Record Deduction') ?></h2>
    <?php if (Auth::can('employees.edit')): ?>
    <form method="post" action="<?= e(url('employees/' . (int)$employee['id'] . '/deduction/add')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">
      <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required></div>
      <div class="span-2"><label class="f"><?= __('Reason *') ?></label><input class="f" name="reason" required placeholder="<?= e(__('e.g. loss / late deduction / advance recovery')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
      <div class="span-2"><button class="btn btn-primary" type="submit">+ <?= __('Record Deduction') ?></button></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Deduction History') ?> <span class="muted" style="font-size:11px;font-weight:400"><?= money($deductionsTotal) ?> <?= __('total') ?></span></h2>
    <?php if (!$deductions): ?><p class="muted"><?= __('No deductions recorded yet.') ?></p><?php else: ?>
      <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Reason') ?></th><th class="num"><?= __('Amount') ?></th></tr></thead>
        <tbody><?php foreach ($deductions as $d): ?><tr><td><?= date_display($d['date']) ?></td><td><?= e($d['reason']) ?></td><td class="num"><?= money($d['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'history'): ?>
<div class="panel" style="margin-top:14px">
  <h2><?= __('Work History') ?></h2>
  <div class="form-grid">
    <div><div class="f-lbl"><?= __('Joined Company') ?></div><div><?= e($employee['join_date'] ? date_display($employee['join_date']) : '—') ?></div></div>
    <div><div class="f-lbl"><?= __('Job Title') ?></div><div><?= e($employee['designation'] ?: '—') ?></div></div>
    <div><div class="f-lbl"><?= __('Department') ?></div><div><?= e($employee['department'] ?: '—') ?></div></div>       <div><div class="f-lbl"><?= __('Salary Payment Frequency') ?></div><div><?= e(ucfirst($employee['salary_frequency'] ?? $employee['salary_type'] ?? 'monthly')) ?></div></div>
    <div><div class="f-lbl"><?= __('Employee ID') ?></div><div><?= e($employee['code']) ?></div></div>
  </div>
  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Kinds of Work') ?></h3>
  <p class="help"><?= __('This system keeps a running record of role changes in Salary History (Set Salary with effective date).') ?></p>
</div>

<?php elseif ($tab === 'ledger'): ?>
<div class="panel" style="margin-top:14px">
  <h2><?= __('Complete Financial Ledger') ?></h2>
  <p class="help"><?= __('Salary payable and advance receivable are tracked separately and never mixed. Payments and accruals are ordered with a running balance.') ?></p>

  <h3 style="font-size:14px;margin:12px 0 6px"><?= __('Salary Payable Ledger') ?></h3>
  <?php if (!$salaryLedger): ?><p class="muted"><?= __('No salary movements yet.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Description') ?></th><th class="num"><?= __('Credit (Rs)') ?></th><th class="num"><?= __('Debit (Rs)') ?></th><th class="num"><?= __('Balance (Rs)') ?></th></tr></thead>
      <tbody><?php foreach ($salaryLedger as $r): ?><tr><td><?= $r['date'] === 'legacy' ? __('legacy') : date_display($r['date']) ?></td><td><?= e($r['desc_text']) ?></td><td class="num"><?= $r['credit'] > 0 ? money($r['credit']) : '—' ?></td><td class="num"><?= $r['debit'] > 0 ? money($r['debit']) : '—' ?></td><td class="num"><?= money($r['balance']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>

  <h3 style="font-size:14px;margin:16px 0 6px"><?= __('Advance Receivable Ledger') ?></h3>
  <?php if (!$advanceLedger): ?><p class="muted"><?= __('No advances yet.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Description') ?></th><th class="num"><?= __('Debit (Rs)') ?></th><th class="num"><?= __('Credit (Rs)') ?></th><th class="num"><?= __('Balance (Rs)') ?></th></tr></thead>
      <tbody><?php foreach ($advanceLedger as $r): ?><tr><td><?= date_display($r['date']) ?></td><td><?= e($r['desc_text']) ?></td><td class="num"><?= $r['debit'] > 0 ? money($r['debit']) : '—' ?></td><td class="num"><?= $r['credit'] > 0 ? money($r['credit']) : '—' ?></td><td class="num"><?= money($r['balance']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>

  <h3 style="font-size:14px;margin:16px 0 6px"><?= __('Bonuses & Deductions') ?></h3>
  <?php if (!$awardsLedger): ?><p class="muted"><?= __('No bonuses or deductions yet.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Description') ?></th><th class="num"><?= __('Amount (Rs)') ?></th></tr></thead>
      <tbody><?php foreach ($awardsLedger as $r): ?><tr><td><?= date_display($r['date']) ?></td><td><span class="badge <?= $r['kind'] === 'Deduction' ? 'red' : 'green' ?>"><?= e($r['kind']) ?></span></td><td><?= e($r['desc_text']) ?></td><td class="num"><?= money($r['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>if (window.initPhotoPreview) initPhotoPreview('photoInput', 'photoPreview', 'photoAvatar');</script>

<!-- Mark as Left / Archive Modal -->
<div id="markLeftModal" class="erp-modal-overlay no-print" hidden style="display:none;position:fixed;inset:0;background:rgba(10,20,13,.55);z-index:999;align-items:center;justify-content:center">
  <div class="panel" style="width:100%;max-width:440px;margin:auto;box-shadow:0 10px 25px rgba(0,0,0,.25);background:#fff;border-radius:12px;padding:22px">
    <h3 style="margin-top:0;color:#991b1b">🚪 <?= __('Mark as Left / Mark Departed / Left') ?></h3>
    <p class="muted" style="font-size:13px;margin-bottom:14px"><?= __('Record departure of ') ?><strong id="leftTargetName"></strong><?= __('. This will safely remove them from the active list and preserve their full historical record in the Records archive.') ?></p>
    <form method="post" action="<?= e(url('records/mark-left')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="type" id="leftTargetType" value="employees">
      <input type="hidden" name="id" id="leftTargetId" value="">
      <div style="margin-bottom:12px">
        <label class="f" style="font-weight:600;margin-bottom:4px"><?= __('Leaving Date *') ?></label>
        <input class="f" type="date" name="left_date" value="<?= today() ?>" required>
      </div>
      <div style="margin-bottom:16px">
        <label class="f" style="font-weight:600;margin-bottom:4px"><?= __('Leaving Reason *') ?></label>
        <input class="f" type="text" name="reason" required placeholder="<?= e(__('e.g. Resigned / Left job / Contract Ended')) ?>">
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
