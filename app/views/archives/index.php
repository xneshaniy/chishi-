<?php $title = 'Archives — Admin'; ?>
<div class="simple-hero" style="padding:16px 20px;margin-bottom:12px">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Archives') ?></span><br><small><?= __('Soft-delete records. Deleted archives still appear everywhere previously; restore anytime.') ?></small></div>
</div>
<div class="panel" style="margin-bottom:12px">
  <p class="help"><?= __('Archived records are hidden from normal lists. They keep all their relationships, history and reporting data. Use Restore to bring them back, or Delete only when every incoming reference count is zero.') ?></p>
</div>
<div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Module') ?></th><th class="num"><?= __('Active') ?></th><th class="num"><?= __('Archived') ?></th><th></th></tr></thead>
<tbody><?php foreach ($featured as $row): ?><tr>
  <td><strong><?= e($row['label']) ?></strong><br><span class="muted mono"><?= e($row['key']) ?></span></td>
  <td class="num"><?= (int)$row['active'] ?></td>
  <td class="num" style="color:<?= $row['archived'] > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= (int)$row['archived'] ?></td>
  <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/archives/' . $row['key'])) ?>"><?= __('Manage →') ?></a></td>
</tr><?php endforeach; ?></tbody></table></div>
<p class="help mt"><?= __('Owner/Admin only. Every archive, restore and delete is recorded in the audit log with who, when and the reason.') ?></p>
