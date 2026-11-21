<?php
$title = __('Change Password');
?>
<div class="panel" style="max-width:560px;margin:20px auto">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
    <div style="width:40px;height:40px;border-radius:50%;background:#e8f4ec;color:#0e5a34;display:flex;align-items:center;justify-content:center;font-size:20px">
      🔒
    </div>
    <div>
      <h2 style="margin:0;font-size:18px"><?= __('Change Password') ?></h2>
      <div class="muted" style="font-size:13px"><?= e($user['full_name']) ?> (<code><?= e($user['username']) ?></code>)</div>
    </div>
  </div>

  <form method="post" action="<?= e(url('settings/password')) ?>">
    <?= csrf_field() ?>

    <div style="margin-bottom:16px">
      <label class="f" for="current_password" style="font-weight:600;margin-bottom:6px"><?= __('Current Password') ?> *</label>
      <div style="position:relative">
        <input class="f" id="current_password" name="current_password" type="password" required autocomplete="current-password" placeholder="<?= e(__('Enter current password')) ?>" style="padding-right:40px">
        <button type="button" onclick="togglePwdVisibility('current_password')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#666" title="<?= e(__('Show / Hide Password')) ?>">👁️</button>
      </div>
    </div>

    <div style="margin-bottom:16px">
      <label class="f" for="new_password" style="font-weight:600;margin-bottom:6px"><?= __('New Password') ?> * (<?= __('min 8 chars') ?>)</label>
      <div style="position:relative">
        <input class="f" id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password" placeholder="<?= e(__('Enter new password')) ?>" style="padding-right:40px">
        <button type="button" onclick="togglePwdVisibility('new_password')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#666" title="<?= e(__('Show / Hide Password')) ?>">👁️</button>
      </div>
    </div>

    <div style="margin-bottom:20px">
      <label class="f" for="confirm_password" style="font-weight:600;margin-bottom:6px"><?= __('Confirm New Password') ?> *</label>
      <div style="position:relative">
        <input class="f" id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password" placeholder="<?= e(__('Re-enter new password')) ?>" style="padding-right:40px">
        <button type="button" onclick="togglePwdVisibility('confirm_password')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#666" title="<?= e(__('Show / Hide Password')) ?>">👁️</button>
      </div>
    </div>

    <div class="btn-group" style="display:flex;gap:10px;justify-content:flex-end">
      <a class="btn btn-outline" href="<?= e(url('dashboard')) ?>"><?= __('Cancel') ?></a>
      <button class="btn btn-primary" type="submit"><?= __('Update Password') ?></button>
    </div>
  </form>
</div>

<script>
function togglePwdVisibility(inputId) {
  var input = document.getElementById(inputId);
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
