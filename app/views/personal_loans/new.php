<?php
$title = __('New Loan') . ' — ' . dasti_label();
$accounts = $accounts ?? db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
?>

<div class="simple-hero no-print" style="padding:14px 20px;margin-bottom:14px">
  <div class="greet">
    <span style="font-size:22px;font-weight:800"><?= __('+ New Loan / New Personal Loan Record') ?></span><br>
    <small><?= __('کسی شخص کو دی گئی رقم، واپسی کی تاریخ، اور کیش یا بینک اکاؤنٹ کا ریکارڈ') ?></small>
  </div>
</div>

<form method="post" action="<?= e(url('personal-loans/new')) ?>" class="panel print-area" style="max-width:850px;margin:0 auto">
  <?= csrf_field() ?>
  <h2 style="margin-top:0"><?= __('Loan Details') ?></h2>
  
  <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
    <div>
      <label class="f"><?= __('Borrower / Person Full Name *') ?></label>
      <input class="f" name="person_name" required placeholder="<?= e(__('e.g. Muhammad Ali')) ?>">
    </div>
    
    <div>
      <label class="f"><?= __('Contact Phone') ?></label>
      <input class="f" name="phone" placeholder="03xx-xxxxxxx">
    </div>

    <div>
      <label class="f"><?= __('Loan Date *') ?></label>
      <input class="f" type="date" name="loan_date" required value="<?= e(today()) ?>">
    </div>

    <div>
      <label class="f"><?= __('Expected Return Date *') ?></label>
      <input class="f" type="date" name="expected_date" required value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
      <small class="muted"><?= __('اس تاریخ تک رقم واپس ملنی ہے') ?></small>
    </div>

    <div>
      <label class="f"><?= __('Loan Amount Given *') ?></label>
      <input class="f num" type="number" step="0.01" min="0.01" name="original_amount" required placeholder="0.00" style="font-size:16px;font-weight:700;color:#1e40af">
    </div>

    <div>
      <label class="f"><?= __('Paid From Account *') ?></label>
      <select class="f" name="account_id" required>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option>
        <?php endforeach; ?>
      </select>
      <small class="muted"><?= __('اس اکاؤنٹ کے بیلنس سے رقم نکلے گی') ?></small>
    </div>

    <div class="span-2">
      <label class="f"><?= __('Description / Reason / Details') ?></label>
      <input class="f" name="description" placeholder="<?= e(__('e.g. Personal need / emergency / friend loan')) ?>">
    </div>

    <div class="span-2">
      <label class="f"><?= __('Notes (Optional)') ?></label>
      <textarea class="f" name="notes" rows="2" placeholder="<?= e(__('کوئی بھی اضافی شرائط یا تفصیل')) ?>"></textarea>
    </div>
  </div>

  <div class="btn-group mt" style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
    <a class="btn btn-outline" href="<?= e(url('personal-loans')) ?>"><?= __('Cancel') ?></a>
    <button class="btn btn-primary btn-xl" type="submit"><?= __('Save Loan / Save Loan Record') ?> →</button>
  </div>
</form>
