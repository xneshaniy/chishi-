<?php $title = __('Issue to Salesman'); ?>
<div class="simple-wrap">
<div class="simple-hero" style="background:linear-gradient(135deg,#1f6a39,#2f7a4c 70%,#3f9d63 100%)">
  <div class="greet"><?= __('Who gets the stock?') ?><br><small><?= __('Tap a salesman to open the quick issue form.') ?></small></div>
</div>
<?php $picker = 'salesmen/'; require __DIR__ . '/../partials/salesman_picker.php'; ?>
<?php if (!$salesmen): ?><div class="panel"><p class="muted"><?= __('No salesmen on file yet.') ?></p></div><?php endif; ?>
</div>
