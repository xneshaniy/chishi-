<?php
/**
 * Searchable salesman picker — photo grid with live filter.
 * Expects: $salesmen (id, code, name, phone, cnic, photo_path), optional $filterRoute prefix,
 *          optional $currentId. Clicking navigates to <prefix><id>. Post-render JS reads data-id.
 */
$routeFor = $routeFor ?? function (int $id) { return 'salesmen/' . $id . '/issue'; };
?>
<input class="f" id="smPickerSearch" placeholder="<?= e(__('Search by name / ID / CNIC…')) ?>" style="margin-bottom:10px" autocomplete="off">
<div class="emp-grid" id="smPickerGrid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <?php foreach ($salesmen as $s): ?>
    <a class="emp-card" href="<?= e(url($routeFor((int)$s['id']))) ?>" data-search="<?= e(strtolower($s['name'] . ' ' . $s['code'] . ' ' . ($s['cnic'] ?? ''))) ?>">
      <?php if (!empty($s['photo_path'])): ?>
        <img class="emp-photo" src="<?= e(photo_data_uri($s['photo_path'])) ?>" alt="<?= e($s['name']) ?>">
      <?php else: ?>
        <div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="emp-name"><?= e($s['name']) ?></div>
      <div class="emp-code"><?= e($s['code']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<script>
(function(){
  var input = document.getElementById('smPickerSearch');
  var cards = document.querySelectorAll('#smPickerGrid .emp-card');
  if (!input) return;
  input.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    cards.forEach(function(c){ c.style.display = (!q || c.getAttribute('data-search').indexOf(q) !== -1) ? '' : 'none'; });
  });
})();
</script>
