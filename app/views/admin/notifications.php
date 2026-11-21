<?php $title = 'All Notifications (Admin)'; ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="admin/notifications">
  <select name="filter" onchange="this.form.submit()">
    <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $k => $l): ?><option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $l ?> (<?= $total ?>)</option><?php endforeach; ?>
  </select>
  <select name="type" onchange="this.form.submit()"><option value=""><?= __('All types') ?></option><?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(approval_label($t)) ?></option><?php endforeach; ?></select>
  <input name="q" placeholder="<?= e(__('Search title / body / user…')) ?>" value="<?= e($q) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <?php if ($q !== '' || $type !== '' || $filter !== 'all'): ?><a class="btn btn-outline" href="<?= e(url('admin/notifications')) ?>"><?= __('Reset') ?></a><?php endif; ?>
</form>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('When') ?></th><th><?= __('To') ?></th><th><?= __('Type') ?></th><th><?= __('Notification') ?></th><th><?= __('Read') ?></th></tr></thead>
<tbody><?php foreach ($rows as $n): ?><tr style="<?= $n['is_read'] ? 'opacity:.62' : '' ?>">
  <td style="white-space:nowrap"><?= e(dt_display($n['created_at'])) ?></td>
  <td><div><strong><?= e($n['full_name'] ?: $n['username']) ?></strong><br><span class="muted">@<?= e($n['username']) ?></span></div></td>
  <td><span class="badge gray"><?= e(approval_label($n['type'])) ?></span></td>
  <td><?php if (!empty($n['link'])): ?><a href="<?= e($n['link']) ?>"><?= e($n['title']) ?></a><?php else: ?><?= e($n['title']) ?><?php endif; ?><?php if (!empty($n['body'])): ?><div class="muted" style="font-size:12px"><?= e($n['body']) ?></div><?php endif; ?></td>
  <td><?= $n['is_read'] ? '<span class="badge green">READ</span>' : '<span class="badge blue">UNREAD</span>' ?></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= __('No notifications.') ?></td></tr><?php endif; ?></tbody></table></div>
<?php if ($pages > 1): ?><div class="btn-group mt">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="btn btn-outline btn-sm <?= $i === $page ? 'is-active' : '' ?>" href="<?= e(url('admin/notifications', ['filter' => $filter, 'type' => $type, 'q' => $q, 'page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div><?php endif; ?>
<p class="help mt"><?= __('This view is Owner/Admin only. It shows every notification stored in the system; visibility for each user is otherwise role-scoped and never crosses users.') ?></p>
