<?php $title = __('Return to Ahmedpur'); ?>
<div class="two-col">
  <form class="panel simple-form" method="post" action="<?= e(url('jampur/returns')) ?>">
    <?= csrf_field() ?>
    <h2><?= __('Material Returned to Ahmedpur') ?></h2>
    <p class="help"><?= __('Only material that physically leaves Jampur increases Ahmedpur stock. Multiple partial returns from the same record are supported.') ?></p>
    <div class="big-fields">
      <div class="span-2"><label class="f"><?= __('Select Jampur Record *') ?></label>
        <select class="f" name="send_id" required>
          <option value=""><?= __('— select record —') ?></option>
          <?php foreach ($sends as $s): $remain = max(0, (float)$s['processed_kg'] - (float)$s['returned_kg']); ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['send_no']) ?> · <?= date_display($s['date']) ?> · <?= e($s['product']) ?> — <?= __('processed') ?>: <?= number_format((float)$s['processed_kg'], 2) ?> KG, <?= __('returned') ?>: <?= number_format((float)$s['returned_kg'], 2) ?> KG (<?= __('can return') ?>: <?= number_format($remain, 2) ?> KG)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="f"><?= __('Return Date') ?> *</label><input class="f" type="date" name="return_date" value="<?= e(today()) ?>" required></div>
      <div><label class="f"><?= __('Quantity Returned (KG) *') ?></label><input class="f" type="number" step="0.001" min="0.001" name="qty_kg" required placeholder="e.g. 600"></div>
      <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" rows="2" name="notes" placeholder="<?= e(__('Optional')) ?>"></textarea></div>
    </div>
    <div class="save-bar">
      <button class="btn btn-primary btn-xl" type="submit"><?= __('Save Return') ?></button>
      <a class="btn btn-outline" href="<?= e(url('jampur')) ?>"><?= __('Cancel') ?></a>
    </div>
  </form>

  <div class="panel">
    <h2><?= __('Recent Returns') ?></h2>
    <?php if ($returns): ?><div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($returns as $g): ?><div class="doc-row"><div class="doc-ic" style="background:var(--green-soft);color:#0a4d29">R</div><div style="flex:1"><div style="font-weight:700"><?= e($g['product']) ?> — <?= number_format((float)$g['qty_kg'], 2) ?> KG</div><div class="muted" style="font-size:11px"><?= e($g['send_no']) ?> · <?= e($g['party_name']) ?> · <?= date_display($g['return_date']) ?></div></div></div><?php endforeach; ?>
    </div><?php else: ?><p class="muted"><?= __('No returns recorded yet.') ?></p><?php endif; ?>
  </div>
</div>
