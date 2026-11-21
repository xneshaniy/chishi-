<?php $title = __('Jampur Grinding'); ?>
<div class="stat-strip" style="grid-template-columns:repeat(6,1fr)">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Sent to Jampur') ?></div><div class="sc-val"><?= number_format($sent, 1) ?> <span style="font-size:12px">KG</span></div><div class="sc-sub"><?= __('Total sent') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Grinding Done') ?></div><div class="sc-val"><?= number_format($processed, 1) ?> <span style="font-size:12px">KG</span></div><div class="sc-sub"><?= __('Processed') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Grinding Loss') ?></div><div class="sc-val"><?= number_format($loss, 1) ?> <span style="font-size:12px">KG</span></div><div class="sc-sub"><?= __('Explicit loss') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Returned to Ahmedpur') ?></div><div class="sc-val"><?= number_format($returned, 1) ?> <span style="font-size:12px">KG</span></div><div class="sc-sub"><?= __('Back in stock') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Remaining at Jampur') ?></div><div class="sc-val" style="color:var(--red)"><?= number_format($remaining, 1) ?> <span style="font-size:12px">KG</span></div><div class="sc-sub"><?= __('Still at Jampur') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Pending Charges') ?></div><div class="sc-val"><?= money($pendingCharges) ?></div><div class="sc-sub"><?= (int)$pendingSends ?> <?= __('send(s) awaiting grinding') ?></div></div>
</div>
<?php if ($canTransfer): ?>
<div class="section-head"><h3><span class="tick"></span><?= __('What do you want to do?') ?></h3></div>
<div class="tile-grid" style="grid-template-columns:repeat(4,1fr)">
  <a class="tile" href="<?= e(url('jampur/send')) ?>"><span class="tile-ic" style="background:#e7f6ec"><svg viewBox="0 0 24 24" fill="none" stroke="#127a3f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V8M7 13l5-5 5 5"/><path d="M4 4h16"/></svg></span><span class="tile-lbl"><?= __('Send Material for Grinding') ?></span><span class="tile-sub"><?= __('Ahmedpur → Jampur') ?></span></a>
  <a class="tile" href="<?= e(url('jampur/grind')) ?>"><span class="tile-ic" style="background:#fdf0d5"><svg viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V8l6 4V8l6 4V4h6v17z"/><circle cx="7" cy="17" r="1"/><circle cx="12" cy="17" r="1"/><circle cx="17" cy="17" r="1"/></svg></span><span class="tile-lbl"><?= __('Record Grinding') ?></span><span class="tile-sub"><?= __('After grinding is done') ?></span></a>
  <a class="tile" href="<?= e(url('jampur/returns')) ?>"><span class="tile-ic" style="background:#e1edfb"><svg viewBox="0 0 24 24" fill="none" stroke="#0b5cab" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v14M7 12l5 5 5-5"/><path d="M4 21h16"/></svg></span><span class="tile-lbl"><?= __('Return to Ahmedpur') ?></span><span class="tile-sub"><?= __('Processed tobacco comes back') ?></span></a>
  <a class="tile" href="<?= e(url('jampur/report')) ?>"><span class="tile-ic" style="background:#f3e8ff"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg></span><span class="tile-lbl"><?= __('Jampur Reports') ?></span><span class="tile-sub"><?= __('Sent · Grinding · Returns · Remaining') ?></span></a>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h2><?= __('Jampur Records') ?> <span class="muted" style="font-weight:400;font-size:12px">(<?= count($sends) ?>)</span></h2>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Ref') ?></th><th><?= __('Date') ?></th><th><?= __('Material') ?></th><th><?= __('Grinding Party') ?></th><th class="num"><?= __('Sent (KG)') ?></th><th class="num"><?= __('Processed (KG)') ?></th><th class="num"><?= __('Loss (KG)') ?></th><th class="num"><?= __('Returned (KG)') ?></th><th class="num"><?= __('Remaining (KG)') ?></th></tr></thead>
    <tbody>
      <?php foreach ($sends as $s): $remain = max(0, (float)$s['qty_kg'] - (float)$s['returned_kg'] - (float)$s['loss_kg']); ?>
        <tr>
          <td class="mono"><?= e($s['send_no']) ?></td>
          <td><?= date_display($s['date']) ?></td>
          <td><?= e($s['product']) ?></td>
          <td><?= e($s['party_name']) ?></td>
          <td class="num"><?= number_format((float)$s['qty_kg'], 2) ?></td>
          <td class="num"><?= number_format((float)$s['processed_kg'], 2) ?></td>
          <td class="num"><?= number_format((float)$s['loss_kg'], 2) ?></td>
          <td class="num"><?= number_format((float)$s['returned_kg'], 2) ?></td>
          <td class="num"><strong><?= number_format($remain, 2) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sends): ?><tr><td colspan="9" class="muted"><?= __('No material sent to Jampur yet.') ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="two-col" style="margin-top:16px">
  <div class="panel">
    <h2><?= __('Recent Grinding') ?></h2>
    <?php if ($recentGrinding): ?><div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($recentGrinding as $g): ?><div class="doc-row"><div class="doc-ic">G</div><div style="flex:1"><div style="font-weight:700"><?= e($g['product']) ?> — <?= number_format((float)$g['qty_received_kg'], 2) ?> KG</div><div class="muted" style="font-size:11px"><?= e($g['send_no']) ?> · <?= date_display($g['grind_date']) ?> · <?= __('Charges') ?> Rs <?= number_format((float)$g['charges'], 2) ?> (<?= $g['charges_status'] === 'paid' ? __('Paid') : __('Pending') ?>)</div></div></div><?php endforeach; ?>
    </div><?php else: ?><p class="muted"><?= __('No grinding recorded yet.') ?></p><?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('Recent Returns') ?></h2>
    <?php if ($recentReturns): ?><div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($recentReturns as $g): ?><div class="doc-row"><div class="doc-ic" style="background:var(--green-soft);color:#0a4d29">R</div><div style="flex:1"><div style="font-weight:700"><?= e($g['product']) ?> — <?= number_format((float)$g['qty_kg'], 2) ?> KG</div><div class="muted" style="font-size:11px"><?= e($g['send_no']) ?> · <?= date_display($g['return_date']) ?></div></div></div><?php endforeach; ?>
    </div><?php else: ?><p class="muted"><?= __('No returns recorded yet.') ?></p><?php endif; ?>
  </div>
</div>
