<?php $title = __('Attendance'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="attendance/hours"><label class="muted"><?= __('Date:') ?></label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"><span class="muted"><?= __('Hours default to 8.00 per present day — used for hourly-pay staff and overtime.') ?></span></form>
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>">
<h2><?= __('Attendance —') ?><?= e(date_display($date)) ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Employee') ?></th><th><?= __('Type') ?></th><th><?= __('Designation') ?></th><th><?= __('Status') ?></th><th class="num"><?= __('Hours') ?></th></tr></thead>
<tbody><?php foreach ($employees as $e): $cur = $existing[(int)$e['id']]['status'] ?? 'present'; $hrs = $existing[(int)$e['id']]['hours'] ?? '8.00'; ?>
<tr><td class="mono"><?= e($e['code']) ?></td><td><?= e($e['name']) ?></td><td><?= e($e['salary_type']) ?></td><td><?= e($e['designation'] ?? '—') ?></td>
<td><select class="f" name="status[<?= (int)$e['id'] ?>]" style="width:auto"><?php foreach (['present','absent','leave','half_day','off'] as $s): ?><option value="<?= $s ?>" <?= $cur === $s ? 'selected' : '' ?>><?= strtoupper(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></td>
<td><input class="f" type="number" step="0.25" min="0" max="24" name="hours[<?= (int)$e['id'] ?>]" value="<?= e(number_format((float)$hrs, 2, '.', '')) ?>" style="width:80px"></td></tr>
<?php endforeach; ?></tbody></table></div>
<button class="btn btn-primary mt" type="submit"><?= __('Save Attendance') ?></button>
</form>
