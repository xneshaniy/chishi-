<?php $title = __('Attendance History'); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="attendance/history">
  <input type="date" class="f" name="from" value="<?= e($from) ?>">
  <input type="date" class="f" name="to" value="<?= e($to) ?>">
  <select class="f" name="type" style="min-width:140px">
    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>><?= __('All types') ?></option>
    <option value="employee" <?= $type === 'employee' ? 'selected' : '' ?>><?= __('Employee') ?></option>
    <option value="salesman" <?= $type === 'salesman' ? 'selected' : '' ?>><?= __('Salesman') ?></option>
  </select>
  <input name="q" placeholder="<?= e(__('Search by name / ID…')) ?>" value="<?= e($q) ?>" style="min-width:200px">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('attendance')) ?>">&larr; <?= __('Attendance Board') ?></a>
</form>
<div class="alert alert-info"><?= __('Every attendance entry recorded or updated is listed here — who did it, what changed, and when.') ?></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('When') ?></th><th><?= __('Person') ?></th><th><?= __('ID') ?></th><th><?= __('Type') ?></th><th><?= __('Date') ?></th><th><?= __('Change') ?></th><th><?= __('Changed By') ?></th><th><?= __('Note') ?></th></tr></thead>
<tbody><?php foreach ($p['rows'] as $r): ?><tr>
  <td style="white-space:nowrap"><?= dt_display($r['changed_at']) ?></td>
  <td><strong><?= e($r['person_name']) ?></strong></td>
  <td class="mono"><?= e($r['person_code']) ?></td>
  <td><span class="badge <?= $r['person_type'] === 'salesman' ? 'blue' : 'green' ?>"><?= e(strtoupper($r['person_type'])) ?></span></td>
  <td style="white-space:nowrap"><?= e(date_display($r['date'])) ?></td>
  <td style="white-space:nowrap"><?php if ($r['old_status']): ?><span class="muted"><?= e(strtoupper($r['old_status'])) ?></span> &rarr; <?php endif; ?><span class="badge <?= $r['new_status'] === 'present' ? 'green' : ($r['new_status'] === 'absent' ? 'red' : 'amber') ?>"><?= e(strtoupper((string)$r['new_status'])) ?></span></td>
  <td><?= e($r['actor_name'] ?: $r['actor_username'] ?: '—') ?><?php if ($r['actor_username']): ?><br><span class="muted mono" style="font-size:11px">@<?= e($r['actor_username']) ?></span><?php endif; ?></td>
  <td class="muted" style="max-width:240px"><?= e($r['note'] ?? '') ?></td>
</tr><?php endforeach; ?>
<?php if (!$p['rows']): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:30px"><?= __('No attendance changes in this range.') ?></td></tr><?php endif; ?></tbody></table></div>
<?= pager_html($p, 'attendance/history', ['from' => $from, 'to' => $to, 'q' => $q, 'type' => $type]) ?></div>
