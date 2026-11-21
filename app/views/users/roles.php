<?php $title = __('Roles & Permissions'); ?>
<div class="panel"><h2><?= __('Roles') ?></h2>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Role') ?></th><th><?= __('Code') ?></th><th><?= __('Description') ?></th><th class="num"><?= __('Permissions') ?></th><th></th></tr></thead>
<tbody><?php foreach ($roles as $r): ?><tr>
  <td><strong><?= e($r['name']) ?></strong></td><td><code><?= e($r['code']) ?></code></td><td><?= e($r['description']) ?></td>
  <td class="num"><?= $r['code'] === 'owner' ? 'ALL' : (int)$r['perm_count'] ?></td>
  <td><?php if ($r['code'] !== 'owner'): ?><a class="btn btn-outline btn-sm" href="<?= e(url('users/roles/' . (int)$r['id'])) ?>"><?= __('Edit Permissions') ?></a><?php else: ?><span class="muted"><?= __('Full access (locked)') ?></span><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<p class="help">Permission changes take effect at next request — no re-login needed. Salesmen see only their own customers and documents on every list screen in the system.</p>
</div>
