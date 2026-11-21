<?php $title = __('Record Grinding'); ?>
<form class="panel simple-form" method="post" action="<?= e(url('jampur/grind')) ?>">
  <?= csrf_field() ?>
  <h2><?= __('Record Grinding') ?></h2>
  <p class="help"><?= __('When grinding is completed, record the output here. The sent quantity is never overwritten — it stays in the record.') ?></p>
  <div class="big-fields">
    <div class="span-2"><label class="f"><?= __('Select Jampur Record *') ?></label>
      <select class="f" name="send_id" required>
        <option value=""><?= __('— select record —') ?></option>
        <?php foreach ($sends as $s): $remain = max(0, (float)$s['qty_kg'] - (float)$s['processed_kg'] - (float)$s['loss_kg'] - (float)$s['returned_kg']); ?>
          <option value="<?= (int)$s['id'] ?>" data-sent="<?= (float)$s['qty_kg'] ?>"><?= e($s['send_no']) ?> · <?= date_display($s['date']) ?> · <?= e($s['product']) ?> — <?= number_format((float)$s['qty_kg'], 2) ?> KG (<?= __('remaining') ?>: <?= number_format($remain, 2) ?> KG)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Grinding Date') ?> *</label><input class="f" type="date" name="grind_date" value="<?= e(today()) ?>" required></div>
    <div><label class="f"><?= __('Quantity Received After Grinding (KG) *') ?></label><input class="f" type="number" step="0.001" min="0.001" name="qty_received_kg" required placeholder="e.g. 950"></div>
    <div><label class="f"><?= __('Grinding Charges (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="charges" placeholder="e.g. 12500"></div>
    <div><label class="f"><?= __('Processing Loss / Difference (KG)') ?></label><input class="f" type="number" step="0.001" min="0" name="loss_kg" placeholder="e.g. 50"></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" rows="2" name="notes" placeholder="<?= e(__('Optional')) ?>"></textarea></div>
  </div>
  <div class="save-bar">
    <button class="btn btn-primary btn-xl" type="submit"><?= __('Save Grinding Record') ?></button>
    <a class="btn btn-outline" href="<?= e(url('jampur')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>
