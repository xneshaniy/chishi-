<?php $title = __('Backup & Restore'); ?>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Generate Database Backup') ?></h2>
    <p><?= __('Creates a timestamped SQL dump of all tables (data only — the empty schema lives in') ?><code><?= __('database/schema.mysql.sql') ?></code><?= __('in the source) in') ?><code><?= __('storage/backups/') ?></code><?= __('. Backups are') ?><strong><?= __('not') ?></strong><?= __('web-downloadable; copy them via Hostinger File Manager or FTP/SFTP.') ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="generate"><button class="btn btn-primary"><?= __('Generate Backup Now') ?></button></form>
    <h3 style="font-size:13px;margin:16px 0 6px"><?= __('Restore procedure') ?></h3>
    <ol class="help" style="padding-left:18px">
      <li><?= __('Create a fresh empty database in Hostinger → Databases, assign the user.') ?></li>
      <li><?= __('Import') ?><code><?= __('database/schema.mysql.sql') ?></code><?= __('via phpMyAdmin (Import).') ?></li>
      <li><?= __('Import') ?><code><?= __('ctc_erp_YYYYMMDD_HHMMSS.sql') ?></code><?= __('the same way.') ?></li>
      <li><?= __('Point') ?><code><?= __('config/config.php') ?></code><?= __('to the new database or re-run the installer into it.') ?></li>
    </ol>
    <h3 style="font-size:13px;margin:16px 0 6px"><?= __('Automated backups (recommended)') ?></h3>
    <p class="help"><?= __('Hostinger Premium supports Cron Jobs (hPanel → Advanced → Cron Jobs). Add a daily job running:') ?><br><code><?= __('php /home/USER/public_html/bin/backup.php') ?></code><br><?= __('Then enable Hostinger weekly backups too. Keep at least one copy off-site (Google Drive / PC).') ?></p>
  </div>
  <div class="panel">
    <h2><?= __('Existing Backups (') ?><?= count($backups) ?>)</h2>
    <?php if (!$backups): ?><p class="muted"><?= __('No backups yet. Generate one before making changes.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('File') ?></th><th><?= __('Size') ?></th><th><?= __('Created') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($backups as $b): ?><tr>
        <td class="mono"><?= e($b['name']) ?></td><td><?= number_format($b['size']/1024, 1) ?><?= __('KB') ?></td><td><?= dt_display(date('Y-m-d H:i:s', $b['time'])) ?></td>
        <td><form method="post" style="display:inline" data-confirm="<?= e(__('Delete this backup file?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="<?= e($b['name']) ?>"><button class="btn btn-danger btn-sm" type="submit"><?= __('Delete') ?></button></form></td>
      </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>
