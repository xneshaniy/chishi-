<?php $title = __('Branches / Factories'); ?>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Add Branch / Factory') ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div><label class="f"><?= __('Code *') ?></label><input class="f" name="code" required maxlength="20" placeholder="MAIN, LAHORE…"></div>
        <div><label class="f"><?= __('Name *') ?></label><input class="f" name="name" required></div>
        <div><label class="f"><?= __('City') ?></label><input class="f" name="city"></div>
        <div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone"></div>
        <div><label class="f"><?= __('Email') ?></label><input class="f" name="email" type="email"></div>
      </div>
      <label class="f mt"><?= __('Address') ?></label><input class="f" name="address">
      <label class="mt" style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="is_main" value="1"><?= __('Main branch / head office') ?></label>
      <button class="btn btn-primary mt" type="submit"><?= __('Add Branch') ?></button>
    </form>
  </div>
  <div class="panel">
    <h2><?= __('Branches (') ?><?= count($rows) ?>)</h2>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= __('Code') ?></th><th><?= __('Name') ?></th><th><?= __('City') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $b): ?>
        <tr>
          <td><?= e($b['code']) ?><?= $b['is_main'] ? ' <span class="badge gold" style="background:#fdf0d5;color:#8a6d1a">MAIN</span>' : '' ?></td>
          <td><?= e($b['name']) ?></td><td><?= e($b['city'] ?: '—') ?></td>
          <td><span class="badge <?= $b['is_active'] ? 'green' : 'gray' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= __('Toggle') ?></button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
</div>
