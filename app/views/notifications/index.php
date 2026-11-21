<?php $title = 'Notifications'; ?>
<div class="toolbar">
  <div class="pill-toggle" style="margin-right:auto">
    <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $k => $l): ?>
      <a href="<?= e(url('notifications', ['filter' => $k])) ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= e(__($l)) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?= e(url('notifications/mark')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="all-read"><button class="btn btn-outline btn-sm" type="submit"><?= e(__('Mark all read')) ?></button></form>
  <form method="post" action="<?= e(url('notifications/sound')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit" title="<?= e(__('Notification Sound')) ?>"><?= e(__('Notification Sound')) ?>: <strong><?= $prefs['sound'] ? e(__('On')) : e(__('Off')) ?></strong></button></form>
  <form method="post" action="<?= e(url('notifications/desktop')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit" title="<?= e(__('Desktop Notifications')) ?>"><?= e(__('Desktop Notifications')) ?>: <strong><?= $prefs['desktop'] ? e(__('On')) : e(__('Off')) ?></strong></button></form>
</div>

<div class="panel">
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= e(__('When')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Notification')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($p['rows'] as $n): ?>
    <tr style="<?= $n['is_read'] ? 'opacity:.62' : 'font-weight:600' ?>">
      <td style="white-space:nowrap"><?= e(dt_display($n['created_at'])) ?><?= $n['is_read'] ? '' : ' <span class="badge blue">NEW</span>' ?></td>
      <td><span class="badge gray"><?= e(strtoupper(str_replace('_', ' ', $n['type']))) ?></span></td>
      <td>
        <?php if (!empty($n['link'])): ?><a href="<?= e($n['link']) ?>"><?= e($n['title']) ?></a><?php else: ?><?= e($n['title']) ?><?php endif; ?>
        <?php if (!empty($n['body'])): ?><div class="muted" style="font-weight:400;font-size:12px"><?= e($n['body']) ?></div><?php endif; ?>
      </td>
      <td style="white-space:nowrap">
        <form method="post" action="<?= e(url('notifications/mark')) ?>" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <input type="hidden" name="action" value="<?= $n['is_read'] ? 'unread' : 'read' ?>">
          <button class="btn btn-outline btn-sm" type="submit"><?= $n['is_read'] ? e(__('Mark unread')) : e(__('Mark read')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$p['rows']): ?><tr><td colspan="4" class="muted"><?= e(__('No notifications yet.')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?= pager_html($p, 'notifications', ['filter' => $filter]) ?>
</div>
