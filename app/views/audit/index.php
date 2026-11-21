<?php $title = __('Audit Log'); extract($filters); ?>
<form method="get" class="toolbar"><input type="hidden" name="r" value="audit">
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <input name="entity" placeholder="<?= e(__('Entity e.g. sales_invoices')) ?>" value="<?= e($entity) ?>">
  <input name="action" placeholder="<?= e(__('Action e.g. post, reverse')) ?>" value="<?= e($action) ?>">
  <select name="user_id"><option value=""><?= __('All users') ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (string)$user === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <span class="muted"><?= (int)$p['total'] ?><?= __('entries') ?></span>
</form>
<div class="panel"><div class="table-wrap"><table class="tbl">
  <thead><tr><th><?= __('When') ?></th><th><?= __('User') ?></th><th><?= __('Action') ?></th><th><?= __('Entity') ?></th><th><?= __('Ref') ?></th><th><?= __('Details') ?></th><th><?= __('IP') ?></th></tr></thead>
  <tbody><?php foreach ($p['rows'] as $a): ?>
    <tr><td style="white-space:nowrap"><?= dt_display($a['created_at']) ?></td>
        <td><?= e($a['username'] ?? 'system') ?></td>
        <td><span class="badge <?= in_array($a['action'], ['reverse','delete']) ? 'red' : (in_array($a['action'], ['post','login']) ? 'green' : 'gray') ?>"><?= e($a['action']) ?></span></td>
        <td><?= e($a['entity']) ?></td><td><?= e($a['entity_id']) ?></td>
        <td style="max-width:340px"><?= e(friendly_audit_details((string)$a['details'])) ?></td><td class="mono"><?= e($a['ip_address']) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div><?= pager_html($p, 'audit', ['entity' => $entity, 'action' => $action, 'user_id' => $user, 'from' => $from, 'to' => $to]) ?></div>
