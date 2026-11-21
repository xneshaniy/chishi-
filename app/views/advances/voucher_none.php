<?php $title = 'Advance Payment Voucher — ' . e($p['name']); ?>
<div class="panel" style="max-width:600px">
  <h2 style="margin:0"><?= __('No advance yet') ?></h2>
  <p class="help"><?= __('No advance has been recorded for this person yet.') ?></p>
  <div class="mt">
    <?php if ($personType === 'employee'): ?>
      <a class="btn btn-primary" href="<?= e(url('employees/' . (int)$p['id'] . '/advance')) ?>"><?= __('Record Advance Payment') ?></a>
      <a class="btn btn-outline" href="<?= e(url('salary/employee/' . (int)$p['id'])) ?>">← <?= __('Back to salary') ?></a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(url('salesmen/' . (int)$p['id'] . '/salary')) ?>"><?= __('Record Advance Payment') ?></a>
      <a class="btn btn-outline" href="<?= e(url('salary/salesman/' . (int)$p['id'])) ?>">← <?= __('Back to salary') ?></a>
    <?php endif; ?>
  </div>
</div>
