<?php
$title = __('Company Records');
?>
<style>
.rec-tabs { display:flex; gap:8px; border-bottom:2px solid var(--line); margin-bottom:16px; }
.rec-tab { padding:10px 18px; text-decoration:none; font-weight:700; color:#4a5568; border-bottom:3px solid transparent; margin-bottom:-2px; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
.rec-tab.on { color:#0e5a34; border-bottom-color:#0e5a34; }
.rec-badge { background:#e2e8f0; color:#1a202c; border-radius:999px; padding:2px 8px; font-size:11px; }
.rec-tab.on .rec-badge { background:#c6e6d3; color:#0e5a34; }
.user-avatar-sm { width:38px; height:38px; border-radius:50%; object-fit:cover; display:inline-flex; align-items:center; justify-content:center; background:#e2e8f0; font-weight:700; font-size:14px; color:#4a5568; }
.table-wrap { overflow-x:auto; }
</style>

<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:12px;background:#0e5a34;color:#fff;border-radius:12px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="margin:0;font-size:22px;color:#fff">📁 <?= __('Company Records') ?></h1>
      <small style="color:#d1fae5"><?= __('Owner/Admin exclusive — Complete historical dossier of departed staff, former salesmen, and archived records.') ?></small>
    </div>
    <div style="font-size:12px;background:rgba(255,255,255,.15);padding:6px 12px;border-radius:8px">
      🔒 <?= __('Owner / Admin Only') ?>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:14px">
  <div class="stat-chip" style="border-left:4px solid #b91c1c">
    <div class="sc-lbl"><?= __('Departed Salesmen') ?></div>
    <div class="sc-val" style="color:#b91c1c"><?= (int)$archivedSalesmenCount ?></div>
    <div class="sc-sub"><?= (int)$activeSalesmenCount ?> <?= __('currently active') ?></div>
  </div>
  <div class="stat-chip" style="border-left:4px solid #b91c1c">
    <div class="sc-lbl"><?= __('Departed Staff') ?></div>
    <div class="sc-val" style="color:#b91c1c"><?= (int)$archivedEmployeesCount ?></div>
    <div class="sc-sub"><?= (int)$activeEmployeesCount ?> <?= __('currently active') ?></div>
  </div>
</div>

<nav class="rec-tabs" aria-label="<?= __('Records tabs') ?>">
  <a href="<?= e(url('records', ['tab' => 'salesmen'])) ?>" class="rec-tab <?= $tab === 'salesmen' ? 'on' : '' ?>">
    👔 <?= __('Departed Salesmen') ?>
    <span class="rec-badge"><?= (int)$archivedSalesmenCount ?></span>
  </a>
  <a href="<?= e(url('records', ['tab' => 'employees'])) ?>" class="rec-tab <?= $tab === 'employees' ? 'on' : '' ?>">
    👷 <?= __('Departed Employees / Staff Records') ?>
    <span class="rec-badge"><?= (int)$archivedEmployeesCount ?></span>
  </a>
  <a href="<?= e(url('records', ['tab' => 'masters'])) ?>" class="rec-tab <?= $tab === 'masters' ? 'on' : '' ?>">
    📦 <?= __('Other Archives') ?>
  </a>
</nav>

<?php if ($tab === 'salesmen'): ?>
  <div class="toolbar" style="margin-bottom:12px;display:flex;gap:10px;align-items:center">
    <form method="get" action="<?= e(url('records')) ?>" style="display:flex;gap:8px;flex:1">
      <input type="hidden" name="r" value="records">
      <input type="hidden" name="tab" value="salesmen">
      <input class="f" name="q" placeholder="<?= e(__('Search by name, code, phone, CNIC, area…')) ?>" value="<?= e($_GET['q'] ?? '') ?>" style="max-width:320px">
      <button class="btn btn-outline" type="submit"><?= __('Search') ?></button>
    </form>
    <a class="btn btn-outline" href="<?= e(url('salesmen')) ?>">← <?= __('Back to Active Salesmen') ?></a>
  </div>

  <div class="panel">
    <?php if (!$salesmen): ?>
      <div style="text-align:center;padding:36px;color:#6b7280">
        <div style="font-size:32px;margin-bottom:8px">📂</div>
        <p><?= __('No departed salesmen found in records archive.') ?></p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th><?= __('Salesman') ?></th>
              <th><?= __('Code & Phone') ?></th>
              <th><?= __('Warehouse') ?></th>
              <th><?= __('Joined Date') ?></th>
              <th style="color:#b91c1c"><?= __('Left Date') ?></th>
              <th><?= __('Leaving Reason') ?></th>
              <th class="num"><?= __('Total Sales') ?></th>
              <th style="text-align:right"><?= __('Actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($salesmen as $s): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <?php if (!empty($s['photo_path'])): ?>
                      <img class="user-avatar-sm" src="<?= e(photo_data_uri($s['photo_path'])) ?>" alt="<?= e($s['name']) ?>">
                    <?php else: ?>
                      <div class="user-avatar-sm"><?= e(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?></div>
                    <?php endif; ?>
                    <div>
                      <strong><?= e($s['name']) ?></strong>
                      <?php if (!empty($s['area'])): ?><br><small class="muted"><?= e($s['area']) ?></small><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <code><?= e($s['code']) ?></code>
                  <?php if (!empty($s['phone'])): ?><br><small class="muted">📞 <?= e($s['phone']) ?></small><?php endif; ?>
                </td>
                <td><?= e($s['warehouse_name'] ?: '—') ?></td>
                <td><?= $s['join_date'] ? dt_display($s['join_date']) : '—' ?></td>
                <td><strong style="color:#b91c1c"><?= $s['left_date'] ? dt_display($s['left_date']) : '—' ?></strong></td>
                <td><span style="background:#fef2f2;border:1px solid #fee2e2;color:#991b1b;padding:2px 8px;border-radius:6px;font-size:12px"><?= e($s['departure_reason'] ?: __('Left company / Resigned')) ?></span></td>
                <td class="num"><strong><?= money((float)$s['total_sales_amount']) ?></strong><br><small class="muted"><?= (int)$s['total_invoices'] ?> <?= __('invoices') ?></small></td>
                <td style="text-align:right;white-space:nowrap">
                  <div style="display:inline-flex;gap:6px">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$s['id'] . '/profile')) ?>"><?= __('Dossier') ?></a>
                    <form method="post" action="<?= e(url('records/restore')) ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="salesmen">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit" data-confirm="<?= e(__('Restore this salesman back to active staff?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Restore')) ?>" data-confirm-variant="approve"><?= __('Restore') ?></button>
                    </form>
                    <form method="post" action="<?= e(url('records/delete')) ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="salesmen">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <input type="hidden" name="reason" value="Permanently deleted from records archive by admin">
                      <button class="btn btn-danger btn-sm" type="submit" data-confirm="<?= e(__('Delete permanently? Only allowed if no transactions exist.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger"><?= __('Delete') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'employees'): ?>
  <div class="toolbar" style="margin-bottom:12px;display:flex;gap:10px;align-items:center">
    <form method="get" action="<?= e(url('records')) ?>" style="display:flex;gap:8px;flex:1">
      <input type="hidden" name="r" value="records">
      <input type="hidden" name="tab" value="employees">
      <input class="f" name="q" placeholder="<?= e(__('Search by name, code, CNIC, designation, department…')) ?>" value="<?= e($_GET['q'] ?? '') ?>" style="max-width:320px">
      <button class="btn btn-outline" type="submit"><?= __('Search') ?></button>
    </form>
    <a class="btn btn-outline" href="<?= e(url('employees')) ?>">← <?= __('Back to Active Staff') ?></a>
  </div>

  <div class="panel">
    <?php if (!$employees): ?>
      <div style="text-align:center;padding:36px;color:#6b7280">
        <div style="font-size:32px;margin-bottom:8px">📂</div>
        <p><?= __('No departed employees found in records archive.') ?></p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th><?= __('Employee') ?></th>
              <th><?= __('Code & Contact') ?></th>
              <th><?= __('Job Title & Dept') ?></th>
              <th><?= __('Joined Date') ?></th>
              <th style="color:#b91c1c"><?= __('Left Date') ?></th>
              <th><?= __('Leaving Reason') ?></th>
              <th class="num"><?= __('Total Salary Paid') ?></th>
              <th style="text-align:right"><?= __('Actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $e): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <?php if (!empty($e['photo_path'])): ?>
                      <img class="user-avatar-sm" src="<?= e(photo_data_uri($e['photo_path'])) ?>" alt="<?= e($e['name']) ?>">
                    <?php else: ?>
                      <div class="user-avatar-sm"><?= e(mb_strtoupper(mb_substr($e['name'], 0, 1))) ?></div>
                    <?php endif; ?>
                    <div>
                      <strong><?= e($e['name']) ?></strong>
                      <?php if (!empty($e['cnic'])): ?><br><small class="muted"><?= e($e['cnic']) ?></small><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <code><?= e($e['code']) ?></code>
                  <?php if (!empty($e['phone'])): ?><br><small class="muted">📞 <?= e($e['phone']) ?></small><?php endif; ?>
                </td>
                <td>
                  <strong><?= e($e['designation'] ?: __('Employee')) ?></strong>
                  <?php if (!empty($e['department'])): ?><br><small class="muted"><?= e($e['department']) ?></small><?php endif; ?>
                </td>
                <td><?= $e['join_date'] ? dt_display($e['join_date']) : '—' ?></td>
                <td><strong style="color:#b91c1c"><?= $e['left_date'] ? dt_display($e['left_date']) : '—' ?></strong></td>
                <td><span style="background:#fef2f2;border:1px solid #fee2e2;color:#991b1b;padding:2px 8px;border-radius:6px;font-size:12px"><?= e($e['departure_reason'] ?: __('Left company / Resigned')) ?></span></td>
                <td class="num"><strong><?= money((float)$e['total_salary_paid']) ?></strong></td>
                <td style="text-align:right;white-space:nowrap">
                  <div style="display:inline-flex;gap:6px">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('employees/' . (int)$e['id'])) ?>"><?= __('Dossier') ?></a>
                    <form method="post" action="<?= e(url('records/restore')) ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="employees">
                      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit" data-confirm="<?= e(__('Restore this employee back to active staff?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Restore')) ?>" data-confirm-variant="approve"><?= __('Restore') ?></button>
                    </form>
                    <form method="post" action="<?= e(url('records/delete')) ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="employees">
                      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                      <input type="hidden" name="reason" value="Permanently deleted from records archive by admin">
                      <button class="btn btn-danger btn-sm" type="submit" data-confirm="<?= e(__('Delete permanently? Only allowed if no transactions exist.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger"><?= __('Delete') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'masters'): ?>
  <div class="panel">
    <h3><?= __('Other Archives') ?></h3>
    <p class="muted" style="margin-bottom:16px"><?= __('Historical master records archived across the system.') ?></p>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th><?= __('Module') ?></th>
            <th class="num"><?= __('Active Count') ?></th>
            <th class="num"><?= __('Archived Count') ?></th>
            <th style="text-align:right"><?= __('Manage') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($masters as $m): ?>
            <tr>
              <td><strong><?= e($m['label']) ?></strong><br><small class="muted mono"><?= e($m['key']) ?></small></td>
              <td class="num"><?= (int)$m['active'] ?></td>
              <td class="num" style="color:<?= $m['archived'] > 0 ? '#b91c1c' : '#0e5a34' ?>"><strong><?= (int)$m['archived'] ?></strong></td>
              <td style="text-align:right">
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/archives/' . $m['key'])) ?>"><?= __('View Records →') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
