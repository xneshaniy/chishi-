<?php $title = __('Company Profile'); $logo = setting('logo_path', 'assets/img/ctc-logo.svg'); ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('settings/company')) ?>">
<?= csrf_field() ?>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Company Logo') ?></h2>
    <div style="text-align:center;background:#f7f7f7;border:1px dashed var(--line);border-radius:10px;padding:16px">
      <img src="<?= e($logo) ?>" alt="<?= __('Company logo') ?>" style="max-width:280px;max-height:200px">
    </div>
    <label class="f mt"><?= __('Upload new logo (PNG/JPG/SVG/WebP, max 2 MB)') ?></label>
    <input class="f" type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp">
    <p class="help"><?= __('The default logo recreated from the company artwork is used until you upload the official file. It appears on the sidebar, login page, invoices and statements.') ?></p>
    <h2 class="mt"><?= __('Identity') ?></h2>
    <label class="f"><?= __('Company Name') ?></label><input class="f" name="company_name" value="<?= e(setting('company_name', cfg('app.company'))) ?>">
    <label class="f mt"><?= __('Address') ?></label><textarea class="f" name="address" rows="2"><?= e(setting('address')) ?></textarea>
    <div class="form-grid"><div><label class="f"><?= __('Phone') ?></label><input class="f" name="phone" value="<?= e(setting('phone')) ?>"></div>
    <div><label class="f"><?= __('Email') ?></label><input class="f" name="email" type="email" value="<?= e(setting('email')) ?>"></div></div>
    <label class="f mt"><?= __('Website') ?></label><input class="f" name="website" value="<?= e(setting('website')) ?>">
  </div>
  <div class="panel">
    <h2><?= __('Registration & Banking') ?></h2>
    <div class="form-grid">
      <div><label class="f"><?= __('NTN') ?></label><input class="f" name="ntn" value="<?= e(setting('ntn')) ?>"></div>
      <div><label class="f"><?= __('STRN (Sales Tax)') ?></label><input class="f" name="strn" value="<?= e(setting('strn')) ?>"></div>
    </div>
    <label class="f mt"><?= __('Bank Name (printed on invoices)') ?></label><input class="f" name="bank_name" value="<?= e(setting('bank_name')) ?>">
    <label class="f mt"><?= __('Account Title') ?></label><input class="f" name="bank_account_title" value="<?= e(setting('bank_account_title')) ?>">
    <label class="f mt"><?= __('Account No / IBAN') ?></label><input class="f" name="bank_account_no" value="<?= e(setting('bank_account_no')) ?>">
    <h2 class="mt"><?= __('Invoice Footer & Terms') ?></h2>
    <label class="f"><?= __('Terms & Conditions') ?></label><textarea class="f" name="terms" rows="3"><?= e(setting('terms')) ?></textarea>
    <label class="f mt"><?= __('Footer Note (e.g. “Goods once sold will not be taken back…”)') ?></label><textarea class="f" name="invoice_footer" rows="2"><?= e(setting('invoice_footer')) ?></textarea>
    <h2 class="mt"><?= __('Payroll Policy') ?></h2>
    <label class="f"><?= __('Maximum Salary Advance (Rs) — 0 = no limit') ?></label>
    <input class="f" type="number" step="0.01" min="0" name="advance_max_amount" value="<?= e(setting('advance_max_amount', '0')) ?>">
    <p class="help"><?= __('Advance requests above this amount from non-Owner/Admin users are rejected before approval. Owner/Admin can always request any amount (still approval-gated).') ?></p>
    <button class="btn btn-primary mt" type="submit"><?= __('Save Company Profile') ?></button>
  </div>
</div>
</form>
