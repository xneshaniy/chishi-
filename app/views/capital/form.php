<?php $title = $kind === 'invest' ? __('Owner Investment') : __('Owner Drawing'); ?>
<form class="panel simple-form" method="post" action="<?= e(url($kind === 'invest' ? 'capital/invest' : 'capital/draw')) ?>">
  <?= csrf_field() ?><?= idempotency_input() ?>
  <h2><?= $kind === 'invest' ? __('Record Owner Investment') : __('Record Owner Drawing') ?></h2>
  <p class="help"><?= $kind === 'invest' ? __('Owner puts money into the business. Dr Cash/Bank → Cr Owner Capital. Available to Owner/Admin only — it posts immediately.') : __('Owner withdraws money for personal use. Dr Owner Drawings → Cr Cash/Bank. Available to Owner/Admin only — it posts immediately.') ?></p>
  <div class="big-fields">
    <div class="span-2"><label class="f"><?= __('Cash / Bank Account *') ?></label>
      <select class="f" name="account_id" required>
        <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e(strtoupper($a['type'])) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Date *') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required placeholder="e.g. 500000" autofocus></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" rows="2" name="notes" placeholder="<?= e(__('e.g. personal savings deposited / car for family')) ?>"></textarea></div>
  </div>
  <div class="save-bar">
    <button class="btn btn-primary btn-xl" type="submit"><?= $kind === 'invest' ? __('Post Investment') : __('Post Drawing') ?></button>
    <a class="btn btn-outline" href="<?= e(url('capital')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>
