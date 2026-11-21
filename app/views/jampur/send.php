<?php $title = __('Send Material for Grinding'); ?>
<form class="panel simple-form" method="post" action="<?= e(url('jampur/send')) ?>">
  <?= csrf_field() ?>
  <h2><?= __('Send Material for Grinding') ?></h2>
  <p class="help"><?= __('Tobacco leaves your Ahmedpur main warehouse and is tracked at Jampur until it is ground and returned.') ?></p>
  <div class="big-fields">
    <div><label class="f"><?= __('Date') ?> *</label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div class="span-2"><label class="f"><?= __('Tobacco / Raw Material *') ?></label>
      <select class="f" name="product_id" required>
        <option value=""><?= __('— select material —') ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['sku']) ?> · <?= e($p['name']) ?> (<?= number_format((float)$p['on_hand'], 1) ?> KG <?= __('in stock') ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f"><?= __('Quantity Sent (KG) *') ?></label>
      <div style="display:flex;gap:8px">
        <input class="f" type="number" step="0.001" min="0.001" name="qty" required placeholder="1000" style="flex:1">
        <select class="f" name="unit" style="width:90px"><option value="kg">KG</option><option value="ton">TON</option><option value="man">MAN</option></select>
      </div>
      <div class="help"><?= __('TON and MAN are converted to KG automatically.') ?></div>
    </div>
    <div class="span-2"><label class="f"><?= __('Grinding Party / Factory Name *') ?></label><input class="f" name="party_name" required placeholder="e.g. Jampur Grinding Works"></div>
    <div><label class="f"><?= __('Grinding Charges per KG (Rs, optional)') ?></label><input class="f" type="number" step="0.01" min="0" name="charges_per_kg" placeholder="e.g. 12.50"></div>
    <div><label class="f"><?= __('Vehicle / Transport Details') ?></label><input class="f" name="vehicle" placeholder="e.g. Dyna LEB-1234, driver 0300-xxxxxxx"></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" rows="2" name="notes" placeholder="<?= e(__('Optional')) ?>"></textarea></div>
  </div>
  <div class="save-bar">
    <button class="btn btn-primary btn-xl" type="submit"><?= __('Send to Jampur') ?></button>
    <a class="btn btn-outline" href="<?= e(url('jampur')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>
