<?php $title = __('Users & Roles'); $canManage = Auth::can('users.edit') && is_owner_or_admin(); ?>
<style>
.user-actions {display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.link-chip {display:inline-block;background:#f3f1e9;border:1px solid #d8d1bf;border-radius:999px;padding:2px 8px;font-size:11px;color:#5a5330;margin:2px 4px 2px 0}
.link-chip.danger {background:#fbe3e1;border-color:#f2b8b5;color:#8f1d13}
.pwd-badge {display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;font-family:monospace;font-size:12px}
.pwd-toggle-btn {background:none;border:none;cursor:pointer;padding:0 2px;font-size:14px;line-height:1;color:#4b5563}
.pwd-toggle-btn:hover {color:#111827}
@media (max-width:860px){ .user-actions{justify-content:flex-start} }
</style>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px"><input type="hidden" name="r" value="users"><input name="q" placeholder="<?= e(__('Search users…')) ?>" value="<?= e($q) ?>"><button class="btn btn-outline" type="submit"><?= __('Search') ?></button></form>
  <span class="spacer" style="flex:1"></span>
  <?php if (Auth::can('users.create')): ?><a class="btn btn-primary" href="<?= e(url('users/new')) ?>"><?= __('+ New User') ?></a><?php endif; ?>
  <a class="btn btn-outline" href="<?= e(url('users/roles')) ?>"><?= __('Roles & Permissions') ?></a>
</div>
<div class="panel"><div class="table-wrap"><table class="tbl">
  <thead><tr><th><?= __('Username') ?></th><th><?= __('Full Name') ?></th><th><?= __('Email') ?></th><th><?= __('Roles') ?></th><th><?= __('Password') ?></th><th><?= __('Status') ?></th><th><?= __('Last Login') ?></th><th><?= __('Linked records') ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $u): $uid = (int)$u['id']; $linked = $linkedById[$uid] ?? []; $roleCodes = $roleCodesById[$uid] ?? []; $isSelf = $uid === $currentId; $isOwnerAcct = in_array('owner', $roleCodes, true); $isActiveOwnerAdmin = (bool)$u['is_active'] && (bool)array_intersect(['owner','admin'], $roleCodes); $isLastOwnerAdmin = $isActiveOwnerAdmin && $activeOwnerAdmins <= 1; $deleteBlocked = $isSelf || $isOwnerAcct || $isLastOwnerAdmin || $linked; if ($isSelf) $deleteBlockedTitle = __('You cannot delete your own account.'); elseif ($isOwnerAcct) $deleteBlockedTitle = __('Owner accounts cannot be permanently deleted. Deactivate instead.'); elseif ($isLastOwnerAdmin) $deleteBlockedTitle = __('Cannot delete the last active Owner/Admin account.'); elseif ($linked) $deleteBlockedTitle = __('User is linked — delete is blocked. Deactivate instead.'); else $deleteBlockedTitle = ''; ?>
    <tr>
      <td><strong><?= e($u['username']) ?></strong></td>
      <td><?= e($u['full_name']) ?><?php if ($isSelf): ?> <span class="badge gray"><?= __('You') ?></span><?php endif; ?></td>
      <td><?= e($u['email'] ?: '—') ?></td>
      <td><?= e($u['roles'] ?: '—') ?></td>
      <td>
        <span class="pwd-badge">
          <span id="pwd-val-<?= $uid ?>" data-pwd="<?= e($u['temp_password'] ?: '••••••••') ?>" data-shown="0">••••••••</span>
          <button type="button" class="pwd-toggle-btn" onclick="toggleUserPwd(<?= $uid ?>)" title="<?= e(__('Show / Hide Password')) ?>" aria-label="<?= e(__('Show / Hide Password')) ?>">👁️</button>
        </span>
      </td>
      <td><span class="badge <?= $u['is_active'] ? 'green' : 'red' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
      <td><?= dt_display($u['last_login']) ?></td>
      <td><?php if ($linked): foreach ($linked as $l): ?><span class="link-chip danger" title="<?= e($l['label']) ?>: <?= (int)$l['count'] ?>"><?= e($l['label']) ?> · <?= (int)$l['count'] ?></span><?php endforeach; else: ?><span class="muted"><?= __('None') ?></span><?php endif; ?></td>
      <td class="user-actions">
        <?php if ($canManage): ?>
          <a class="btn btn-outline btn-sm" href="<?= e(url('users/' . $uid . '/edit')) ?>"><?= __('Edit') ?></a>
          <button type="button" class="btn btn-outline btn-sm" onclick="openResetModal(<?= $uid ?>, '<?= e(addslashes($u['username'])) ?>')"><?= __('Reset Pass') ?></button>
          <?php if ($isSelf): ?>
            <button class="btn btn-outline btn-sm" type="button" disabled title="<?= e(__('You cannot deactivate yourself.')) ?>">Toggle</button>
          <?php else: ?>
            <form method="post" style="display:contents" data-confirm="<?= e($u['is_active'] ? __('Deactivate this user? They will not be able to log in until reactivated.') : __('Reactivate this user?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Confirm')) ?>" data-confirm-variant="approve"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $uid ?>"><button class="btn btn-outline btn-sm" formaction="<?= e(url('users/' . $uid . '/toggle')) ?>" type="submit">Toggle</button></form>
          <?php endif; ?>
          <?php if ($deleteBlocked): ?>
            <button class="btn btn-danger btn-sm" type="button" disabled title="<?= e($deleteBlockedTitle) ?>"><?= __('Delete') ?></button>
          <?php else: ?>
            <form method="post" style="display:contents" data-confirm="<?= e(__('Permanently delete this user') . ' \"' . $u['username'] . '\"? ' . __('This cannot be undone.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger" data-confirm-require-note="#user-reason-<?= $uid ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $uid ?>"><input id="user-reason-<?= $uid ?>" name="reason" placeholder="<?= e(__('reason')) ?>" class="f" style="width:120px;padding:4px 6px;font-size:12px" required><button class="btn btn-danger btn-sm" type="submit" formaction="<?= e(url('users/' . $uid . '/delete')) ?>"><?= __('Delete') ?></button></form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:24px"><?= __('No users found.') ?></td></tr><?php endif; ?></tbody>
</table></div></div>

<!-- Quick Reset Password Modal -->
<div id="resetModal" class="erp-modal-overlay no-print" hidden style="display:none;position:fixed;inset:0;background:rgba(10,20,13,.55);z-index:999;align-items:center;justify-content:center">
  <div class="panel" style="width:100%;max-width:400px;margin:auto;box-shadow:0 10px 25px rgba(0,0,0,.2);background:#fff;border-radius:12px;padding:20px">
    <h3 style="margin-top:0"><?= __('Reset Password') ?></h3>
    <p class="muted" style="font-size:13px"><?= __('Reset password for user: ') ?><strong id="resetTargetUsername"></strong></p>
    <form id="resetForm" method="post" action="">
      <?= csrf_field() ?>
      <div style="margin:16px 0">
        <label class="f" style="font-weight:600;margin-bottom:6px"><?= __('New Password (min 8 chars) *') ?></label>
        <input class="f" type="text" name="password" id="resetPasswordInput" required minlength="8" placeholder="<?= e(__('e.g. Secret@123')) ?>">
      </div>
      <div class="btn-group" style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="closeResetModal()"><?= __('Cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('Set Password') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleUserPwd(id) {
  var el = document.getElementById('pwd-val-' + id);
  if (!el) return;
  if (el.dataset.shown === '1') {
    el.textContent = '••••••••';
    el.dataset.shown = '0';
  } else {
    el.textContent = el.dataset.pwd;
    el.dataset.shown = '1';
  }
}

function openResetModal(id, username) {
  var modal = document.getElementById('resetModal');
  var form = document.getElementById('resetForm');
  var target = document.getElementById('resetTargetUsername');
  var input = document.getElementById('resetPasswordInput');
  target.textContent = username;
  form.action = '<?= e(url('users')) ?>/' + id + '/reset-password';
  input.value = '';
  modal.style.display = 'flex';
  modal.hidden = false;
  setTimeout(function() { input.focus(); }, 100);
}

function closeResetModal() {
  var modal = document.getElementById('resetModal');
  modal.style.display = 'none';
  modal.hidden = true;
}
</script>
