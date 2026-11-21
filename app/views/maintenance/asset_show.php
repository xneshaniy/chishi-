<?php $title = __('Asset') . ' — ' . e($asset['name']); ?>
<div class="btn-group no-print" style="margin-bottom:10px">
  <a class="btn btn-outline btn-sm" href="<?= e(url('maintenance/assets')) ?>">&larr; <?= __('Back to Assets') ?></a>
  <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</div>
<div class="simple-hero" style="padding:14px 18px;margin-bottom:12px">
  <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div>
      <?php if (!empty($asset['photo_path'])): ?><img src="<?= e(photo_data_uri($asset['photo_path'])) ?>" alt="<?= e($asset['name']) ?>" style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid var(--line)"><?php endif; ?>
      <h1 style="margin:8px 0 0;font-size:22px"><?= e($asset['name']) ?></h1>
      <p class="muted"><?= e($asset['asset_no'] ?: '—') ?> <?= $asset['category'] ? ' · ' . e($asset['category']) : '' ?> <?= $asset['department'] ? ' · ' . e($asset['department']) : '' ?> <?= $asset['location'] ? ' · ' . e($asset['location']) : '' ?></p>
      <?php if ($asset['purchase_date']): ?><p class="muted" style="margin:2px 0 0"><?= __('Purchased') ?>: <?= e(date_display($asset['purchase_date'])) ?></p><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div class="sc-lbl"><?= __('Total Repair Cost') ?></div>
      <div style="font-size:28px;font-weight:800"><?= money($totCost) ?></div>
      <div class="muted" style="font-size:12px"><?= (int)count($records) . ' ' . __('repair jobs') ?></div>
    </div>
  </div>
</div>
<div class="panel print-area">
  <h2 style="margin-top:0"><?= __('Maintenance History') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Problem') ?></th><th><?= __('Work') ?></th><th><?= __('Vendor') ?></th><th class="num"><?= __('Parts') ?></th><th class="num"><?= __('Labour') ?></th><th class="num"><?= __('Other') ?></th><th class="num"><?= __('Total') ?></th><th><?= __('Status') ?></th></tr></thead>
  <tbody><?php foreach ($records as $r): ?><tr>
    <td><?= e(date_display($r['problem_date'])) ?></td>
    <td><strong><?= e(mb_substr($r['problem'], 0, 120)) ?></strong></td>
    <td class="muted" style="max-width:240px"><?= e(mb_substr((string)($r['work_performed'] ?? ''), 0, 120)) ?></td>
    <td><?= e($r['vendor'] ?? '—') ?></td>
    <td class="num"><?= money($r['parts_cost'], false) ?></td><td class="num"><?= money($r['labour_cost'], false) ?></td><td class="num"><?= money($r['other_cost'], false) ?></td>
    <td class="num"><strong><?= money($r['total_cost'], false) ?></strong></td>
    <td><span class="badge <?= $r['status'] === 'posted' ? 'green' : ($r['status'] === 'rejected' ? 'red' : 'amber') ?>"><?= e(strtoupper($r['status'])) ?></span></td>
  </tr><?php endforeach; ?>
  <?php if (!$records): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:30px"><?= __('No repairs recorded for this asset yet.') ?></td></tr><?php else: ?>
  <tr style="font-weight:800;background:#faf7ee"><td colspan="5"><?= __('TOTAL') ?></td><td class="num"><?= money(array_sum(array_map(fn($r) => (float)$r['labour_cost'], $records)), false) ?></td><td colspan="1"></td><td class="num"><?= money($totCost, false) ?></td><td></td></tr>
  <?php endif; ?></tbody></table></div>
</div>
