<?php
/** Print-document company header used by all printable vouchers/reports.
 *  Params: $docTitle (e.g. 'PAYMENT VOUCHER'), $docNo, $docDate, $docType (optional second line e.g. 'PURCHASE INVOICE') */
$co = cfg('app.company');
$logo = setting('logo_path', 'assets/img/ctc-logo.svg');
$docType = $docType ?? $docTitle;
?>
<div class="doc-head" style="display:flex;justify-content:space-between;gap:20px;border-bottom:3px double var(--charcoal);padding-bottom:12px;margin-bottom:14px">
  <div><img src="<?= e($logo) ?>" alt="<?= __('Logo') ?>" style="height:100px"></div>
  <div style="text-align:right">
    <strong style="font-size:20px"><?= e(setting('company_name', $co)) ?></strong><br>
    <span class="muted"><?= e(setting('address')) ?></span><br>
    <?php if (setting('phone')): ?><span class="muted"><?= __('Phone:') ?><?= e(setting('phone')) ?></span><br><?php endif; ?>
    <?php if (setting('ntn') || setting('strn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?> <?= setting('strn') ? '· STRN: ' . e(setting('strn')) : '' ?></span><?php endif; ?>
    <div class="help" style="margin-top:6px"><?= e($docTitle) ?></div>
    <?php if (!empty($docNo)): ?><strong class="mono" style="font-size:18px"><?= e($docNo) ?></strong><?php endif; ?>
    <?php if (!empty($docDate)): ?><br><span class="muted"><?= __('Date:') ?><?= e($docDate) ?></span><?php endif; ?>
  </div>
</div>
