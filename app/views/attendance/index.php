<?php $title = __('Attendance'); ?>
<div class="simple-hero no-print" style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <div class="greet"><div><span style="font-size:22px;font-weight:800"><?= __('Attendance') ?></span><br><small><?= date_display($date) ?> · <?= __('everyone registered (salesmen, labour, employees)') ?></small></div></div>
  <div class="btn-group">
    <a class="btn btn-outline btn-sm" href="<?= e(url('attendance/report')) ?>"><?= __('Monthly Report') ?></a>
    <a class="btn btn-outline btn-sm" href="<?= e(url('attendance/history')) ?>"><?= __('History') ?></a>
    <a class="btn btn-outline btn-sm" href="<?= e(url('attendance/hours')) ?>"><?= __('Payroll Hours Grid') ?></a>
  </div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="attendance">
  <input type="date" class="f" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
  <input name="q" placeholder="<?= e(__('Search by name / ID / phone…')) ?>" value="<?= e($q) ?>" style="min-width:220px">
  <select class="f" name="kind" style="min-width:150px">
    <option value="all" <?= $kind === 'all' ? 'selected' : '' ?>><?= __('All types') ?></option>
    <option value="salesman" <?= $kind === 'salesman' ? 'selected' : '' ?>><?= __('Salesman') ?></option>
    <option value="labour" <?= $kind === 'labour' ? 'selected' : '' ?>><?= __('Labour') ?></option>
    <option value="employee" <?= $kind === 'employee' ? 'selected' : '' ?>><?= __('Employee') ?></option>
  </select>
  <select class="f" name="designation" style="min-width:160px">
    <option value=""><?= __('All designations') ?></option>
    <?php foreach ($designations as $d): ?><option value="<?= e($d) ?>" <?= $designation === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('attendance')) ?>"><?= __('Today') ?></a>
</form>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Shown') ?></div><div class="sc-val"><?= count($rows) ?></div><div class="sc-sub"><?= __('people') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Marked') ?></div><div class="sc-val"><?= (int)$marked ?></div><div class="sc-sub"><?= __('recorded') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Present') ?></div><div class="sc-val"><?= (int)$totalP ?></div><div class="sc-sub"><?= __('all types') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Half Day') ?></div><div class="sc-val"><?= (int)$totalH ?></div><div class="sc-sub"><?= __('counts as 0.5') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Absent') ?></div><div class="sc-val" style="color:#8f1d13"><?= (int)$totalA ?></div><div class="sc-sub"><?= __('all types') ?></div></div>
</div>

<form method="post" action="<?= e(url('attendance')) ?>" class="panel"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="designation" value="<?= e($designation) ?>">
  <?php if (Auth::can('attendance.mark')): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <p class="help" style="margin:0"><?= __('Tick one option per person, then save once.') ?></p>
    <button class="btn btn-primary btn-xl" type="submit"><?= sprintf(__('Save Attendance — %s'), date_display($date)) ?></button>
  </div>
  <?php else: ?>
    <p class="help" style="margin:0;color:#8f1d13"><?= __('You can view attendance but cannot mark it (attendance.mark permission required).') ?></p>
  <?php endif; ?>
  <div class="table-wrap" style="margin-top:10px"><table class="tbl attendance-table" id="attTable"><thead><tr><th style="width:64px"><?= __('Picture') ?></th><th><?= __('Name') ?></th><th><?= __('ID') ?></th><th><?= __('Type') ?></th><th><?= __('Designation') ?></th><th><?= __('Status') ?></th><th><?= __('Totals (P / H / A)') ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><?php if (!empty($r['photo_path'])): ?><img src="<?= e(photo_data_uri($r['photo_path'])) ?>" alt="<?= e($r['name']) ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:46px;height:46px;border-radius:50%;background:var(--green-soft,#e8f5ec);display:flex;align-items:center;justify-content:center;font-weight:800"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></div><?php endif; ?></td>
      <td><strong><?= e($r['name']) ?></strong></td>
      <td class="mono"><?= e($r['code']) ?></td>
      <td><span class="badge <?= $r['kind'] === 'salesman' ? 'blue' : ($r['kind'] === 'labour' ? 'amber' : 'green') ?>"><?= e(strtoupper(PeopleDirectory::kindLabel($r['kind']))) ?></span></td>
      <td><?= e($r['designation']) ?></td>
      <td>
        <div class="att-btns small att-radio-group" style="gap:6px">
          <?php foreach (['present' => ['✓ Present', 'present'], 'absent' => ['✗ Absent', 'absent'], 'half_day' => ['½ Half Day', 'leave']] as $status => [$lbl, $cls]): ?>
            <label class="att-btn <?= e($cls) ?><?= $r['status'] === $status ? ' on' : '' ?>" style="cursor:pointer;padding:6px 12px;font-size:12px;border-radius:8px;white-space:nowrap">
              <input type="radio" name="status[<?= e($r['person_type'] . ':' . $r['id']) ?>]" value="<?= e($status) ?>" <?= $r['status'] === $status ? 'checked' : '' ?> style="display:none"><?= e($lbl) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($r['actor'])): ?><div class="muted" style="font-size:10px;margin-top:4px" title="<?= e(__('Recorded / last updated by')) ?>"><?= __('Recorded by') ?>: <strong><?= e($r['actor']) ?></strong></div><?php endif; ?>
      </td>
      <td class="mono"><?= (int)$r['att_totals']['present'] ?> / <?= (int)$r['att_totals']['half_day'] ?> / <?= (int)$r['att_totals']['absent'] ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= __('Nobody matches the filters.') ?></td></tr><?php endif; ?></tbody>
  </table></div>
</form>
<script>
document.querySelectorAll('.att-radio-group').forEach(function (group) {
  group.querySelectorAll('input[type=radio]').forEach(function (r) {
    r.addEventListener('change', function () {
      group.querySelectorAll('.att-btn').forEach(function (b) { b.classList.remove('on'); });
      if (this.checked) this.closest('.att-btn').classList.add('on');
    });
  });
});
</script>
