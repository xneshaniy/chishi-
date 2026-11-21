<?php $title = __('Financial Year & Periods'); ?>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Fiscal Years') ?></h2>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= __('Year') ?></th><th><?= __('From') ?></th><th><?= __('To') ?></th><th><?= __('Active') ?></th><th><?= __('Closed') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($years as $y): ?>
        <tr>
          <td><strong><?= e($y['name']) ?></strong></td><td><?= date_display($y['start_date']) ?></td><td><?= date_display($y['end_date']) ?></td>
          <td><?= $y['is_active'] ? '<span class="badge green">ACTIVE</span>' : '' ?></td>
          <td><?= $y['is_closed'] ? '<span class="badge red">CLOSED</span>' : '<span class="badge blue">OPEN</span>' ?></td>
          <td><?php if (!$y['is_active'] && !$y['is_closed']): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="activate_year"><input type="hidden" name="id" value="<?= (int)$y['id'] ?>"><button class="btn btn-outline btn-sm"><?= __('Activate') ?></button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <p class="help">New fiscal years (July–June) are created by the installer for the current year. To add additional years, contact your administrator — the closing process requires the fiscal year closing procedure in Accounting → Fiscal Year Closing.</p>
  </div>
  <div class="panel">
    <h2><?= __('Periods —') ?><?= e($fy['name'] ?? '') ?></h2>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= __('Month') ?></th><th><?= __('From') ?></th><th><?= __('To') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($periods as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td><td><?= date_display($p['start_date']) ?></td><td><?= date_display($p['end_date']) ?></td>
          <td><span class="badge <?= $p['status'] === 'open' ? 'green' : 'red' ?>"><?= strtoupper($p['status']) ?></span></td>
          <td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_period"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-outline btn-sm"><?= $p['status'] === 'open' ? 'Close' : 'Re-open' ?></button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <p class="help"><?= __('Posting is blocked in closed periods. Close a period only after all its vouchers are entered and reconciled. Re-opening a closed period is possible here, but use it with care and audit trail.') ?></p>
  </div>
</div>
