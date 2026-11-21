<?php $title = __('Warehouses'); ?>
<style>
.wh-actions {display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.wh-edit {grid-column:span 2;margin:0 0 6px}
.wh-edit summary {cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:6px;color:var(--green,#1f6a39);font-weight:700;font-size:13px;padding:4px 10px;border:1px solid var(--green,#1f6a39);border-radius:6px}
.wh-edit[open] summary {background:#eaf4ec}
.wh-edit[open] summary::after {content:"⌃"}
.wh-edit:not([open]) summary::after {content:"⌄"}
.wh-edit form .form-grid {margin-top:8px}
.link-chip {display:inline-block;background:#f3f1e9;border:1px solid #d8d1bf;border-radius:999px;padding:2px 8px;font-size:11px;color:#5a5330;margin-left:4px}
.link-chip.danger {background:#fbe3e1;border-color:#f2b8b5;color:#8f1d13}
.warehouses-archived {margin-top:18px}
.single-wh-banner {margin:0 0 14px;padding:12px 16px;border:1px solid #cfe3d2;background:#eef6ef;border-radius:10px;font-size:13px;color:#14452a}
.single-wh-banner strong {display:block;font-size:14px;margin-bottom:2px}
.wh-add-adv summary {cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:8px;color:#8a5a00;font-weight:700;border:1px solid #e6cf8a;border-radius:8px;padding:8px 14px;font-size:13px}
.wh-add-adv[open] summary {background:#fdf6e4}
@media (max-width:860px){ .wh-actions{justify-content:flex-start} }
</style>

<div class="single-wh-banner">
  <strong><?= __('Single Main Warehouse (Ahmedpur)') ?></strong>
  <?= __('This installation uses one Main Warehouse only. All stock (production, purchases, opening stock, counts and reports) and goods issues to salesmen flow through it. Salesman vans remain separate salesman stock locations. Additional warehouses are not part of the normal workflow — use Settings → Warehouses (Owner/Admin) only for exceptional system management.') ?>
</div>

<div class="two-col">
  <details class="wh-add-adv panel">
    <summary><?= __('Advanced — Add Warehouse (Owner/Admin only)') ?></summary>
    <p class="help" style="margin:10px 0 4px;color:#8a5a00;font-weight:700"><?= __('This company is configured with a single Main Warehouse. Add another warehouse only for exceptional management reasons — it is not needed for normal operations.') ?></p>
    <form method="post" data-confirm="<?= e(__('This company is configured with a single Main Warehouse. Add another warehouse only for exceptional management reasons. Continue?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Add anyway')) ?>" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div><label class="f"><?= __('Code *') ?></label><input class="f" name="code" required maxlength="20" placeholder="WH-MAIN"></div>
        <div><label class="f"><?= __('Name *') ?></label><input class="f" name="name" required></div>
        <div><label class="f"><?= __('Branch') ?></label><select class="f" name="branch_id"><option value=""><?= __('— none —') ?></option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="f"><?= __('Type') ?></label><select class="f" name="type"><option value="central"><?= __('Central / Main') ?></option><option value="factory"><?= __('Factory') ?></option><option value="warehouse"><?= __('Warehouse') ?></option><option value="salesman"><?= __('Salesman (van/stock)') ?></option><option value="vehicle"><?= __('Vehicle') ?></option></select></div>
      </div>
      <label class="mt" style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="is_default" value="1"><?= __('Default issue warehouse') ?></label>
      <button class="btn btn-primary mt" type="submit"><?= __('Add Warehouse') ?></button>
      <p class="help"><?= __('Salesmen automatically get a dedicated stock location when you create them in the Salesmen section — issues to a salesman are stock transfers, not sales.') ?></p>
    </form>
  </details>

  <div class="panel">
    <h2><?= __('Active Warehouses (') ?><?= count($rows) ?>)</h2>
    <p class="help" style="margin:-4px 0 8px"><?= __('Click Edit on a row to change name / branch / type. Delete asks for a confirmation and a reason — warehouses linked to stock, transactions or salesmen cannot be deleted; deactivate or archive them instead.') ?></p>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('Branch') ?></th><th><?= __('Type') ?></th><th><?= __('Linked data') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $w): $linked = $linkedById[(int)$w['id']] ?? []; $hasLinks = !empty($linked) ? 'danger' : ''; $isDefault = (int)$w['is_default'] === 1; ?>
        <tr>
          <td><strong><?= e($w['code']) ?></strong></td>
          <td>
            <details class="wh-edit"><summary><?= e($w['name']) ?><?= $isDefault ? ' <span class="badge green">DEFAULT</span>' : '' ?></summary>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                <div class="form-grid">
                  <div><label class="f"><?= __('Name *') ?></label><input class="f" name="name" required value="<?= e($w['name']) ?>"></div>
                  <div><label class="f"><?= __('Branch') ?></label><select class="f" name="branch_id"><option value=""><?= __('— none —') ?></option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$w['branch_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
                  <div><label class="f"><?= __('Type') ?></label><select class="f" name="type"><?php foreach (['central' => __('Central / Main'), 'factory' => __('Factory'), 'warehouse' => __('Warehouse'), 'salesman' => __('Salesman (van/stock)'), 'vehicle' => __('Vehicle')] as $k => $l): ?><option value="<?= $k ?>" <?= $w['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                  <?php if (is_owner_or_admin()): ?><div><label class="f"><?= __('Default issue warehouse') ?></label><label style="display:flex;gap:8px;align-items:center;font-size:13px;padding:8px 0"><input type="checkbox" name="is_default" value="1" <?= $isDefault ? 'checked' : '' ?>><?= __('Mark as default') ?></label></div><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-sm mt" type="submit"><?= __('Save changes') ?></button>
              </form>
            </details>
          </td>
          <td><?= e($w['branch'] ?? '—') ?></td><td><span class="badge gray"><?= e($w['type']) ?></span></td>
          <td><?php if ($linked): foreach ($linked as $l): ?><span class="link-chip danger" title="<?= e($l['label']) ?>: <?= (int)$l['count'] ?>"><?= e($l['label']) ?> · <?= (int)$l['count'] ?></span><?php endforeach; else: ?><span class="muted"><?= __('None') ?></span><?php endif; ?></td>
          <td><span class="badge <?= $w['is_active'] ? 'green' : 'gray' ?>"><?= $w['is_active'] ? __('Active') : __('Inactive') ?></span></td>
          <td class="wh-actions">
            <?php if (!$isDefault): ?><form method="post" style="display:contents" data-confirm="<?= $w['is_active'] ? e(__('Deactivate this warehouse? It stays in the system with full history.')) : e(__('Reactivate this warehouse?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Confirm')) ?>" data-confirm-variant="approve"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $w['is_active'] ? __('Deactivate') : __('Activate') ?></button></form><?php endif; ?>
            <?php if (!$isDefault): ?>
              <form method="post" style="display:contents" data-confirm="<?= e(__('Archive this warehouse? It will be hidden from daily lists; history is preserved and can be restored any time.')) ?>" data-confirm-title="<?= e(__('Archive Warehouse')) ?>" data-confirm-ok="<?= e(__('Archive')) ?>" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-outline btn-sm" type="submit" style="color:#8a5a00;border-color:#e6cf8a"><?= __('Archive') ?></button></form>
              <?php if (is_owner_or_admin() && !$hasLinks): ?>
                <form method="post" style="display:contents" data-confirm="<?= e(__('Permanently delete this warehouse? This cannot be undone.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger" data-confirm-require-note="#wh-reason-<?= (int)$w['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input id="wh-reason-<?= (int)$w['id'] ?>" name="reason" placeholder="<?= e(__('reason')) ?>" class="f" style="width:120px;padding:4px 6px;font-size:12px" required><button class="btn btn-danger btn-sm" type="submit"><?= __('Delete') ?></button></form>
              <?php else: ?>
                <button class="btn btn-danger btn-sm" type="button" disabled title="<?= e(__('Warehouse is linked — delete is blocked')) ?>"><?= __('Delete') ?></button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:24px"><?= __('No active warehouses yet.') ?></td></tr><?php endif; ?></tbody>
    </table></div>
  </div>
</div>

<?php if ($archived): ?>
<div class="panel warehouses-archived">
  <h2><?= __('Archived Warehouses (') ?><?= count($archived) ?>)</h2>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('Linked data') ?></th><th><?= __('Archived') ?></th><th></th></tr></thead>
    <tbody><?php foreach ($archived as $w): $linked = $linkedById[(int)$w['id']] ?? []; ?>
      <tr>
        <td><?= e($w['code']) ?></td><td><?= e($w['name']) ?></td>
        <td><?php if ($linked): foreach ($linked as $l): ?><span class="link-chip"><?= e($l['label']) ?> · <?= (int)$l['count'] ?></span><?php endforeach; else: ?><span class="muted"><?= __('None') ?></span><?php endif; ?></td>
        <td><?= !empty($w['created_at']) ? e(date_display($w['created_at'])) : '—' ?></td>
        <td class="wh-actions">
          <form method="post" style="display:contents" data-confirm="<?= e(__('Restore this warehouse from the archive?')) ?>" data-confirm-title="<?= e(__('Restore Warehouse')) ?>" data-confirm-ok="<?= e(__('Restore')) ?>" data-confirm-variant="approve"><?= csrf_field() ?><input type="hidden" name="action" value="unarchive"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-primary btn-sm" type="submit"><?= __('Restore') ?></button></form>
          <?php if (is_owner_or_admin() && !$linked): ?>
            <form method="post" style="display:contents" data-confirm="<?= e(__('Permanently delete this archived warehouse? This cannot be undone.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger" data-confirm-require-note="#wh-reason-<?= (int)$w['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input id="wh-reason-<?= (int)$w['id'] ?>" name="reason" placeholder="<?= e(__('reason')) ?>" class="f" style="width:120px;padding:4px 6px;font-size:12px" required><button class="btn btn-danger btn-sm" type="submit"><?= __('Delete') ?></button></form>
          <?php else: ?>
            <button class="btn btn-danger btn-sm" type="button" disabled title="<?= e(__('Warehouse is linked — delete is blocked')) ?>"><?= __('Delete') ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

