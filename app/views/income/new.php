<?php $title = __('Record Other Income'); ?>
<form class="panel simple-form" method="post" action="<?= e(url('income/new')) ?>">
  <?= csrf_field() ?><?= idempotency_input() ?>
  <h2><?= __('Record Other Income') ?></h2>
  <p class="help"><?= __('Any income outside sales: rent, commission, scrap sales, interest, deposits... It is recorded as pending and Owner/Admin approves it exactly once before it posts to the ledger (Dr Cash/Bank → Cr Other Income).') ?></p>
  <div class="big-fields">
    <div><label class="f"><?= __('Category') ?></label>
      <select class="f" name="category">
        <?php foreach (['other' => 'Other', 'rent' => 'Rent', 'commission' => 'Commission', 'scrap' => 'Scrap sale', 'interest' => 'Interest'] as $k => $v): ?><option value="<?= e($k) ?>"><?= e(__($v)) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Date') ?> *</label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div class="span-2"><label class="f"><?= __('Payer / Source') ?></label><input class="f" name="payer" placeholder="<?= e(__('e.g. Ahmed Plaza (rent), scrap dealer')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description') ?></label><input class="f" name="description" placeholder="<?= e(__('e.g. Nov rent for shop #2')) ?>" maxlength="255"></div>
    <div>
      <label class="f"><?= __('Payment Type') ?></label>
      <select class="f" name="payment_type" id="payment_type">
        <option value="cash"><?= __('Cash') ?></option><option value="bank"><?= __('Bank') ?></option>
      </select>
    </div>
    <div><label class="f"><?= __('Cash / Bank Account *') ?></label>
      <select class="f" name="account_id" required>
        <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e(strtoupper($a['type'])) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="span-2"><label class="f"><?= __('Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required placeholder="e.g. 75000" autofocus></div>
  </div>
  <div class="save-bar">
    <button class="btn btn-primary btn-xl" type="submit"><?= __('Save (Pending Approval)') ?></button>
    <a class="btn btn-outline" href="<?= e(url('income')) ?>"><?= __('Cancel') ?></a>
  </div>
</form>
