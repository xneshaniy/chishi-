<?php $title = __('Advance Voucher —') . e($a['employee']); ?>
<div class="print-area">
<div class="panel" style="box-shadow:none;border:none;max-width:760px;margin:0 auto">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px double var(--charcoal);padding-bottom:10px;margin-bottom:14px">
    <img src="<?= e(setting('logo_path', 'assets/img/ctc-logo.svg')) ?>" alt="CTC" style="height:80px">
    <div style="text-align:right"><strong style="font-size:17px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br><span class="muted"><?= e(setting('address')) ?></span></div>
  </div>
  <h2 style="justify-content:center"><?= __('SALARY ADVANCE VOUCHER') ?></h2>
  <div class="form-grid" style="margin-bottom:10px">
    <div><strong><?= __('Voucher No:') ?></strong> ADV-<?= str_pad((string)(int)$a['id'], 5, '0', STR_PAD_LEFT) ?></div>
    <div><strong><?= __('Date:') ?></strong> <?= date_display($a['date']) ?></div>
    <div><strong><?= __('Employee:') ?></strong> <?= e($a['employee']) ?> (<?= e($a['code']) ?>)</div>
    <div><strong><?= __('Designation:') ?></strong> <?= e($a['designation'] ?? '—') ?></div>
    <div><strong><?= __('Department:') ?></strong> <?= e($a['department'] ?? '—') ?></div>
    <div><strong><?= __('Status:') ?></strong> <?= strtoupper(e($a['status'])) ?></div>
    <div><strong><?= __('Pay Out From:') ?></strong> <?= e($a['account'] ?? '—') ?></div>
    <div><strong><?= __('Monthly Deduction:') ?></strong> <?= $a['monthly_deduction'] !== null ? money($a['monthly_deduction']) : __('Full remaining') ?></div>
  </div>
  <div style="text-align:center;border-top:3px double var(--charcoal);margin-top:14px;padding-top:12px">
    <div class="help"><?= __('ADVANCE AMOUNT') ?></div>
    <div style="font-size:30px;font-weight:800;color:var(--charcoal)"><?= __('Rs') ?><?= number_format($a['amount'], 2) ?></div>
    <div class="help mt"><?= __('Amount in words:') ?><?= e(amount_in_words((float)$a['amount'])) ?></div>
  </div>
  <p class="help mt"><strong><?= __('Reason:') ?></strong> <?= e($a['notes'] ?? '—') ?></p>
  <p class="help"><?= __('Deducted automatically from future salary runs; capped at the outstanding balance. Approved by Owner/Admin — this voucher prints once.') ?><?php if (!empty($a['approved_by'])): ?><br><strong><?= __('Approved by:') ?></strong> <?= e($a['approver'] ?? 'Owner/Admin') ?><?= !empty($a['approved_at']) ? ' · ' . e(dt_display($a['approved_at'])) : '' ?><?php endif; ?></p>
  <div class="two-col mt" style="margin-top:40px">
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Employee Signature') ?></span></div>
    <div style="border-top:1px solid #555;padding-top:6px;text-align:center"><span class="help"><?= __('Authorized Signatory') ?></span></div>
  </div>
  <p class="help" style="text-align:center;margin-top:12px"><?= __('Computer-generated voucher —') ?><?= e(setting('company_name', cfg('app.company'))) ?></p>
</div>
</div>
<div class="btn-group no-print mt"><button class="btn btn-dark" onclick="window.print()"><?= __('🖨 Print / Save as PDF') ?></button><a class="btn btn-outline" href="<?= e(url('employees/' . (int)$a['employee_id'] . '/edit')) ?>"><?= __('Back') ?></a></div>
