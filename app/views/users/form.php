<?php
$title = $user ? 'Edit User' : 'New User';
$sel = $userRoles ?? [1];
$userPerms = $userPerms ?? [];
$rolePermMap = $rolePermMap ?? [];

$moduleLabels = [
    'dashboard'   => __('Dashboard'),
    'purchase'    => __('Raw Materials & Purchases'),
    'inventory'   => __('Warehouse & Inventory'),
    'production'  => __('Production & Factory'),
    'sales'       => __('Sales & Invoicing'),
    'customers'   => __('Customers'),
    'suppliers'   => __('Suppliers'),
    'salesmen'    => __('Salesmen & Recovery'),
    'cashbank'    => __('Cash & Bank Accounts'),
    'loans'       => __('Loans / Dasti (Personal Loans)'),
    'expenses'    => __('Expenses (Daily & Company)'),
    'gifts'       => __('Gifts'),
    'capital'     => __('Capital'),
    'income'      => __('Other Income'),
    'accounting'  => __('Accounting & Ledger'),
    'reports'     => __('Reports & Analytics'),
    'payroll'     => __('Staff & Payroll'),
    'maintenance' => __('Maintenance & Assets'),
    'vehicles'    => __('Vehicles & Transport'),
    'claims'      => __('Claims'),
    'approvals'   => __('Approvals'),
    'audit'       => __('Audit Log'),
    'backup'      => __('Backup & Restore'),
    'settings'    => __('Settings'),
    'users'       => __('Users & Roles'),
];
?>
<form method="post" class="panel"><?= csrf_field() ?>
  <h2><?= $user ? __('Edit User') . ' — ' . e($user['username']) : __('Create User') ?></h2>
  <?php if ($user && $user['force_password_change']): ?><div class="alert alert-warn"><?= __('This user must change their password at next login.') ?></div><?php endif; ?>
  <div class="alert alert-info"><?= __('Login accounts are limited to Owner/Admin, Manager and Accountant. Admin can grant specific custom permissions below — users will ONLY see and access what they are permitted.') ?></div>
  
  <div class="form-grid">
    <div><label class="f"><?= __('Username *') ?></label><input class="f" name="username" <?= $user ? 'value="' . e($user['username']) . '" readonly' : 'required placeholder="e.g. accountant1"' ?>></div>
    <div><label class="f"><?= __('Full Name *') ?></label><input class="f" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required></div>
    <div><label class="f"><?= __('Email') ?></label><input class="f" name="email" type="email" value="<?= e($user['email'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
    <div><label class="f"><?= __('Branch') ?></label><select class="f" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= ((int)($user['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
  </div>

  <fieldset class="mt" style="border:1px solid var(--line);border-radius:10px;padding:12px;background:#fafafa">
    <legend class="help" style="font-weight:700"><?= __('Roles (authorized login roles only)') ?></legend>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
      <?php foreach ($roles as $r): ?>
      <label style="display:flex;gap:8px;align-items:center;font-size:13px;background:#fff;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer">
        <input type="checkbox" name="roles[]" class="role-checkbox" value="<?= (int)$r['id'] ?>" data-role-id="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $sel, true) ? 'checked' : '' ?> onchange="onRoleChange()">
        <span><strong><?= e($r['name']) ?></strong><br><span class="muted" style="font-size:11px"><?= e($r['code']) ?></span></span>
      </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div class="form-grid mt">
    <div>
      <label class="f"><?= $user ? __('New Password (leave blank to keep current)') : __('Password * (min 8 chars)') ?></label>
      <div style="position:relative">
        <input class="f" id="user-password-input" name="password" type="password" autocomplete="new-password" <?= $user ? '' : 'required' ?> placeholder="<?= $user ? ($user['temp_password'] ? __('Current: ') . e($user['temp_password']) : '••••••••') : 'e.g. Pass@1234' ?>" style="padding-right:40px">
        <button type="button" onclick="toggleLocalPwd()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#666" title="<?= e(__('Show / Hide Password')) ?>">👁️</button>
      </div>
      <?php if ($user && !empty($user['temp_password'])): ?>
        <small class="muted" style="display:block;margin-top:4px"><?= __('Stored Password: ') ?><code><?= e($user['temp_password']) ?></code></small>
      <?php endif; ?>
    </div>
  </div>

  <!-- Custom User Permissions Section -->
  <fieldset class="mt" style="border:1px solid var(--line);border-radius:10px;padding:16px;background:#fff">
    <legend style="font-weight:700;font-size:15px;color:#0e5a34">
      🛡️ <?= __('Custom Permissions') ?>
    </legend>
    <p class="muted" style="font-size:13px;margin:4px 0 12px">
      <?= __('Select exactly which modules and actions this user is permitted to access. Items not checked will be completely hidden from their navigation.') ?>
    </p>

    <div class="btn-group mb" style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-outline btn-sm" onclick="selectAllPerms(true)"><?= __('Select All') ?></button>
      <button type="button" class="btn btn-outline btn-sm" onclick="selectAllPerms(false)"><?= __('Clear All') ?></button>
      <button type="button" class="btn btn-outline btn-sm" onclick="loadRoleDefaults()"><?= __('Load Selected Role Defaults') ?></button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:12px">
      <?php foreach ($byModule as $module => $perms): ?>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid #e5e7eb;padding-bottom:4px">
            <strong style="font-size:12px;color:#1e293b;text-transform:uppercase">
              <?= e($moduleLabels[$module] ?? ucfirst($module)) ?>
            </strong>
            <button type="button" style="background:none;border:none;color:#0e5a34;font-size:11px;cursor:pointer;padding:0" onclick="toggleModulePerms('<?= e($module) ?>')"><?= __('Toggle') ?></button>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($perms as $p): ?>
              <label style="display:flex;align-items:flex-start;gap:6px;font-size:12px;cursor:pointer" title="<?= e($p['description'] ?: $p['code']) ?>">
                <input type="checkbox" name="perms[]" class="perm-cb perm-mod-<?= e($module) ?>" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $userPerms, true) ? 'checked' : '' ?> style="margin-top:2px">
                <span>
                  <code><?= e($p['code']) ?></code>
                  <?php if (!empty($p['description'])): ?>
                    <br><span class="muted" style="font-size:11px"><?= e($p['description']) ?></span>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div class="btn-group mt" style="display:flex;gap:10px;margin-top:20px">
    <button class="btn btn-primary" type="submit"><?= $user ? __('Save Changes') : __('Create User') ?></button>
    <a class="btn btn-outline" href="<?= e(url('users')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>

<script>
var rolePermMap = <?= json_encode($rolePermMap) ?>;

function toggleLocalPwd() {
  var inp = document.getElementById('user-password-input');
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function selectAllPerms(val) {
  document.querySelectorAll('.perm-cb').forEach(function(cb) {
    cb.checked = val;
  });
}

function toggleModulePerms(mod) {
  var cbs = document.querySelectorAll('.perm-mod-' + mod);
  var anyUnchecked = false;
  cbs.forEach(function(c) { if (!c.checked) anyUnchecked = true; });
  cbs.forEach(function(c) { c.checked = anyUnchecked; });
}

function loadRoleDefaults() {
  var checkedRoleIds = [];
  document.querySelectorAll('.role-checkbox:checked').forEach(function(rc) {
    checkedRoleIds.push(parseInt(rc.value, 10));
  });
  if (checkedRoleIds.length === 0) {
    alert(<?= json_encode(__('Please select at least one role above first.')) ?>);
    return;
  }
  var allowedPermIds = new Set();
  checkedRoleIds.forEach(function(rid) {
    if (rolePermMap[rid]) {
      rolePermMap[rid].forEach(function(pid) {
        allowedPermIds.add(pid);
      });
    }
  });
  document.querySelectorAll('.perm-cb').forEach(function(cb) {
    cb.checked = allowedPermIds.has(parseInt(cb.value, 10));
  });
}

function onRoleChange() {
  // If this is a new user form and no custom perms touched yet, automatically load role defaults
  <?php if (!$user): ?>
    loadRoleDefaults();
  <?php endif; ?>
}
</script>
