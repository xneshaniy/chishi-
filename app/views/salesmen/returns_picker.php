<?php $title = __('Goods Returns'); ?>
<div class="simple-wrap">
  <div class="simple-hero" style="background:linear-gradient(135deg,#3d2b14,#7a5a17 70%,#b8892f 100%)">
    <div class="greet"><?= __('Who is returning goods?') ?><br><small><?= __('Tap a salesman to open their goods-return form — accepted returns restock the warehouse, rejected ones stay on the salesman ledger.') ?></small></div>
  </div>
  <?php $routeFor = function (int $id) { return 'salesmen/' . $id . '/return'; }; $picker = 'salesmen/'; require __DIR__ . '/../partials/salesman_picker.php'; ?>
  <?php if (!$salesmen): ?><div class="panel"><p class="muted"><?= __('No salesmen on file yet.') ?></p></div><?php endif; ?>
</div>
