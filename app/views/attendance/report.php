<?php $title = 'Attendance Report — ' . e($from) . ' → ' . e($to); ?>
<form method="get" class="toolbar no-print">
  <input type="hidden" name="r" value="attendance/report">
  <label class="muted"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>">
  <label class="muted"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>">
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
  <button class="btn btn-primary" type="submit"><?= __('Apply') ?></button>
  <button class="btn btn-dark no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
  <a class="btn btn-outline" href="<?= e(url('attendance')) ?>">← <?= __('Attendance Board') ?></a>
</form>

<div class="panel print-area">
  <div class="print-only"><strong><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= __('Attendance Report') ?> — <?= e($from) ?> → <?= e($to) ?></span><hr></div>
  <h2><?= __('Attendance Report') ?> <span class="muted" style="font-size:11px;font-weight:400"><?= e($from) ?> → <?= e($to) ?></span></h2>

  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(120px,1fr));margin-bottom:12px">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Present') ?></div><div class="sc-val"><?= (int)$totPres ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Half Day') ?></div><div class="sc-val"><?= (int)$totHalf ?></div></div>
    <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Absent') ?></div><div class="sc-val" style="color:#8f1d13"><?= (int)$totAbs ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Leave') ?></div><div class="sc-val"><?= (int)$totLeave ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Net Payable Days') ?></div><div class="sc-val"><?= number_format($totNet, 1) ?></div></div>
  </div>

  <?php if (!$rows): ?><p class="muted"><?= __('No people match the filters.') ?></p><?php else: ?>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Picture') ?></th><th><?= __('Name') ?></th><th><?= __('ID') ?></th><th><?= __('Type') ?></th><th><?= __('Designation') ?></th><th class="num"><?= __('Present') ?></th><th class="num"><?= __('Half Day') ?></th><th class="num"><?= __('Absent') ?></th><th class="num"><?= __('Leave') ?></th><th class="num"><?= __('Net Days') ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><?php if (!empty($r['photo_path'])): ?><img src="<?= e(photo_data_uri($r['photo_path'])) ?>" alt="<?= e($r['name']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:38px;height:38px;border-radius:50%;background:var(--green-soft,#e8f5ec);display:flex;align-items:center;justify-content:center;font-weight:800"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></div><?php endif; ?></td>
      <td><?= e($r['name']) ?></td><td class="mono"><?= e($r['code']) ?></td><td><?= e(strtoupper(PeopleDirectory::kindLabel($r['kind']))) ?></td><td><?= e($r['designation']) ?></td>
      <td class="num"><?= (int)$r['present'] ?></td><td class="num"><?= (int)$r['half_day'] ?></td><td class="num"><?= (int)$r['absent'] ?></td><td class="num"><?= (int)$r['leave'] ?></td><td class="num"><strong><?= number_format((float)$r['net_days'], 1) ?></strong></td>
    </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
