<?php
$title = 'Advance Payment Voucher — ' . e($p['name']);
$imgSrc = !empty($p['photo_path']) ? photo_data_uri($p['photo_path']) : null;
$balanceAfter = max(0, (float)$stats['outstanding']);
?>
<div class="print-area">
  <?= partial('partials/doc_head', ['docTitle' => __('ADVANCE PAYMENT VOUCHER'), 'docNo' => 'ADV-' . str_pad((string)$adv['id'], 5, '0', STR_PAD_LEFT), 'docDate' => date_display($adv['date'] ?: today())]) ?>

  <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
    <?php if ($imgSrc): ?><img src="<?= e($imgSrc) ?>" alt="<?= e($p['name']) ?>" style="width:88px;height:88px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:88px;height:88px;border-radius:50%;background:var(--green-soft);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:28px"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></div><?php endif; ?>
    <div>
      <div class="help"><?= __('ISSUED TO') ?></div>
      <strong style="font-size:18px"><?= e($p['name']) ?></strong><br>
      <span class="muted"><?= e($p['code']) ?> · <?= e(PeopleDirectory::kindLabel($p['kind'])) ?> · <?= e($p['designation']) ?></span><br>
      <span class="muted"><?= $p['phone'] ? 'Phone · ' . e($p['phone']) : '' ?></span>
    </div>
    <div style="margin-left:auto;text-align:right">
      <div class="help"><?= __('VOUCHER') ?></div>
      <strong class="mono" style="font-size:20px">ADV-<?= str_pad((string)$adv['id'], 5, '0', STR_PAD_LEFT) ?></strong><br>
      <span class="muted"><?= __('Date:') ?><?= date_display($adv['date'] ?: today()) ?></span>
    </div>
  </div>

  <table class="tbl"><tbody>
    <tr><td style="width:45%"><?= __('Advance Amount') ?></td><td><strong style="font-size:18px"><?= __('Rs') ?><?= money($adv['amount'], false) ?></strong></td></tr>
    <tr><td><?= __('Reason / Notes') ?></td><td><?= e($adv['reason'] ?: '—') ?></td></tr>
    <tr><td><?= __('Method') ?></td><td><?= e(strtoupper($adv['method'] ?? 'cash')) ?></td></tr>
    <tr><td><?= __('Total Advances Given') ?></td><td><?= money($stats['total_given'], false) ?></td></tr>
    <tr><td><?= __('Recovered So Far') ?></td><td><?= money($stats['total_recovered'], false) ?></td></tr>
    <tr><td><?= __('Remaining / Outstanding') ?></td><td><strong><?= money($balanceAfter, false) ?></strong></td></tr>
    <tr><td><?= __('Amount in words') ?></td><td><strong><?= e(amount_in_words((float)$adv['amount'])) ?></strong></td></tr>
  </tbody></table>

  <p class="help" style="margin-top:12px"><?= __('This advance will be adjusted against upcoming salary / commission. The salesman authorises this deduction with their signature below.') ?></p>

  <div style="display:flex;justify-content:space-between;margin-top:40px;gap:40px">
    <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Prepared by') ?></div>
    <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Received by') ?></div>
    <div style="text-align:center;min-width:140px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Manager') ?></div>
  </div>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()">🖨 <?= __('Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$p['id'] . '/salary')) ?>">← <?= __('Back to salesman') ?></a>
</div>
