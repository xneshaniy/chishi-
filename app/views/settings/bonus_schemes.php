<?php $title = __('Bonus Schemes & Rules'); ?>
<div class="alert alert-warn"><?= __('Restricted schemes and bonus issues above the threshold require') ?><strong><?= __('Owner/Admin approval') ?></strong><?= __('— stock will not move and no journal posts until approval. Below the threshold, restricted') ?><strong><?= __('salesman bonus issues') ?></strong><?= __('and') ?><strong><?= __('customer bonus invoices') ?></strong><?= __('flow through automatically (still audited).') ?></div>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Approval Rules') ?></h2>
    <form method="post" action="<?= e(url('settings/bonus-schemes')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save_rules">
      <label class="f"><?= __('Bonus Approval Threshold (Rs) — any bonus above this needs Admin approval') ?></label>
      <input class="f" type="number" name="bonus_approval_threshold" min="0" step="0.01" value="<?= e($threshold) ?>">
      <p class="help"><?= __('Compares against the estimated') ?><em><?= __('cost value') ?></em><?= __('of bonus units at issue time (salesman stock and customer invoices).') ?></p>
      <button class="btn btn-primary mt" type="submit"><?= __('Save Rules') ?></button>
    </form>
    <h2 class="mt"><?= __('Add Bonus Scheme') ?></h2>
    <form method="post"><?= csrf_field() ?>
      <div class="form-grid"><div><label class="f"><?= __('Name *') ?></label><input class="f" name="name" required placeholder="<?= e(__('e.g. Customer 10+1 Free')) ?>"></div>
      <div><label class="f"><?= __('Kind') ?></label><select class="f" name="kind"><?php foreach (['salesman','customer','purchase','promotion','special'] as $k): ?><option value="<?= $k ?>"><?= ucfirst($k) ?></option><?php endforeach; ?></select></div></div>
      <label class="f mt"><?= __('Description') ?></label><input class="f" name="description" placeholder="<?= e(__('e.g. festive offer for retail shops')) ?>">
      <label class="mt" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="requires_approval" value="1"><?= __('Requires Owner/Admin approval') ?></label>
      <button class="btn btn-primary mt" type="submit"><?= __('Add Scheme') ?></button>
    </form>
  </div>
  <div class="panel"><h2><?= __('Configured Schemes (') ?><?= count($rows) ?>)</h2>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Name') ?></th><th><?= __('Kind') ?></th><th><?= __('Description') ?></th><th><?= __('Approval') ?></th><th><?= __('Status') ?></th></tr></thead>
    <tbody><?php foreach ($rows as $s): ?><tr><td><strong><?= e($s['name']) ?></strong></td><td><span class="badge gray"><?= e($s['kind']) ?></span></td><td><?= e($s['description'] ?? '') ?></td><td><?= $s['requires_approval'] ? '<span class="badge red">RESTRICTED</span>' : '<span class="badge green">free</span>' ?></td><td><?= $s['is_active'] ? 'Active' : 'Off' ?></td></tr><?php endforeach; ?></tbody></table></div>
    <p class="help">Purchase bonus is recorded as a bonus line on the GRN form (qty added to stock at cost 0). Customer bonus is a Bonus line on the invoice with scheme + discount reason. Salesman bonus is on the Issue Stock form.</p>
  </div>
</div>
