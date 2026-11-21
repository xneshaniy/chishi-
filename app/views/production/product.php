<?php
$title = __('Production — ') . e($product['name']);
$imgSrc = !empty($product['photo_path']) ? photo_data_uri($product['photo_path']) : null;
$unitLbl = (($product['kind'] ?? '') === 'finished') ? __('Packets') : ($product['unit_short'] ?: __('units'));
?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:12px">
  <div class="greet" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <?php if ($imgSrc): ?><img src="<?= e($imgSrc) ?>" alt="<?= e($product['name']) ?>" style="width:110px;height:110px;border-radius:14px;object-fit:cover;border:3px solid var(--green-soft)"><?php endif; ?>
    <div style="flex:1;min-width:240px">
      <span style="font-size:22px;font-weight:800"><?= e($product['name']) ?></span><br>
      <small><?= e($product['sku']) ?> · <?= e($product['category'] ?: __('Uncategorised')) ?><?= $product['pack_size'] ? ' · ' . e($product['pack_size']) : '' ?><?= $product['unit_short'] ? ' · ' . e($product['unit_short']) : '' ?></small><br>
      <?php if (!empty($product['description'])): ?><span class="muted" style="font-size:12px"><?= e($product['description']) ?></span><?php endif; ?>
    </div>
    <div class="btn-group" style="flex-wrap:wrap;gap:6px">
      <?php if (Auth::can('products.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('products/' . (int)$product['id'] . '/edit')) ?>"><?= __('Edit Product') ?></a><?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('production')) ?>"><?= __('All Products') ?></a>
      <?php if (Auth::can('production.create')): ?><a class="btn btn-dark btn-sm" href="<?= e(url('production/run')) ?>">+ <?= __('Daily Production') ?></a><?php endif; ?>
    </div>
  </div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('On Hand') ?></div><div class="sc-val"><?= number_format($onHand, 0) ?></div><div class="sc-sub"><?= e($unitLbl) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Today’s Production') ?></div><div class="sc-val"><?= number_format($todayQty, 0) ?></div><div class="sc-sub"><?= e($unitLbl) ?> · <?= e(today()) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('This Month’s Production') ?></div><div class="sc-val"><?= number_format($monthQty, 0) ?></div><div class="sc-sub"><?= e($unitLbl) ?> · <?= date('F Y') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Production') ?></div><div class="sc-val"><?= number_format($overallQty, 0) ?></div><div class="sc-sub"><?= e($unitLbl) ?> <?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('In Range') ?></div><div class="sc-val"><?= number_format($rangeQty, 0) ?></div><div class="sc-sub"><?= e($unitLbl) ?> <?= __('filtered total') ?></div></div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="production/<?= (int)$product['id'] ?>">
  <label class="muted" style="align-self:center"><?= __('From') ?></label><input type="date" class="f" name="from" value="<?= e($from) ?>">
  <label class="muted" style="align-self:center"><?= __('To') ?></label><input type="date" class="f" name="to" value="<?= e($to) ?>">
  <label class="muted" style="align-self:center"><?= __('Month') ?></label><input type="month" class="f" name="month" value="<?= e(substr((string)$from, 0, 7)) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('production/' . (int)$product['id'])) ?>"><?= __('Reset') ?></a>
</form>

<div class="panel">
  <h2><?= __('Production History') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= $from ?> → <?= $to ?>)</span></h2>
  <?php if (!$history): ?><p class="muted"><?= __('No production records in this range.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Source') ?></th><th><?= __('Reference') ?></th><th class="num"><?= __('Qty Produced') ?> (<?= e($unitLbl) ?>)</th><th><?= __('Batch') ?></th><th><?= __('Recorded By') ?></th><th><?= __('Notes') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($history as $h): ?><tr>
        <td><?= date_display($h['row_date']) ?></td>
        <td><span class="badge <?= $h['source'] === 'daily' ? 'green' : 'blue' ?>"><?= $h['source'] === 'daily' ? __('DAILY') : __('ORDER') ?></span></td>
        <td class="mono"><?= e($h['run_no']) ?></td>
        <td class="num"><?= number_format((float)$h['qty'], 0) ?></td>
        <td><?= e($h['batch_no'] ?: '—') ?></td>
        <td><?= e($h['recorded_by'] ?: '—') ?></td>
        <td><?= e($h['line_notes'] ?: '—') ?></td>
        <td>
          <?php if ($h['source'] === 'daily' && ($h['status'] ?? '') === 'posted' && is_owner_or_admin()): ?>
            <form method="post" action="<?= e(url('production/runs/' . (int)$h['run_id'] . '/reverse')) ?>" data-confirm="<?= e(__('Reverse this production entry? Stock will be issued back out.')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="reason" value="Reversed from history"><button class="btn btn-outline btn-sm" type="submit" style="color:#8f1d13;border-color:#f2b8b5"><?= __('Reverse') ?></button></form>
          <?php endif; ?>
        </td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($reversed): ?>
<div class="panel" style="margin-top:12px">
  <h2><?= __('Reversed Entries') ?></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Run') ?></th><th class="num"><?= __('Qty') ?> (<?= e($unitLbl) ?>)</th><th><?= __('Reason') ?></th><th><?= __('Reversed By') ?></th></tr></thead>
    <tbody><?php foreach ($reversed as $r): ?><tr><td><?= date_display($r['date']) ?></td><td class="mono"><?= e($r['run_no']) ?></td><td class="num"><?= number_format((float)$r['qty'], 0) ?></td><td><?= e($r['reversal_reason'] ?: '—') ?></td><td><?= e($r['reversed_by'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>
