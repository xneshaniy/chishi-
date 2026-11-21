<?php $title = 'Statement — ' . $sm['name']; ?>
<div class="print-area">
<?= partial('partials/doc_head', ['docTitle' => __('COMPLETE SALESMAN STATEMENT'), 'docNo' => ($sm['code'] ?? ('#' . (int)$sm['id'])), 'docDate' => date_display($to)]) ?>
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <div class="help"><?= __('STATEMENT FOR (SALESMAN)') ?></div>
    <strong style="font-size:17px"><?= e($sm['name']) ?></strong><br>
    <span class="muted"><?= e($sm['area'] ?? '') ?><?= !empty($sm['phone']) ? ' · ' . e($sm['phone']) : '' ?> · <?= e($sm['code']) ?></span>
  </div>
  <div style="text-align:right">
    <div class="help"><?= __('PERIOD') ?></div>
    <strong><?= date_display($from) ?> → <?= date_display($to) ?></strong><br>
    <span class="muted"><?= __('Generated') ?><?= dt_display(now_local()) ?></span>
  </div>
</div>
<div style="background:<?= $stats['outstanding'] > 0 ? '#fff5f5' : '#f2fbf5' ?>;border:1px solid var(--line);border-radius:12px;padding:12px 16px;margin-top:12px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
  <div><div class="help"><?= __('REMAINING PAYABLE TO COMPANY') ?></div>
    <div style="font-size:28px;font-weight:800;color:<?= $stats['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= money(abs((float)$stats['outstanding']), false) ?></div></div>
  <div style="text-align:right"><div class="help"><?= __('AMOUNT IN WORDS') ?></div><strong><?= e(amount_in_words(abs((float)$stats['outstanding']))) ?></strong></div>
</div>

<table class="tbl" style="margin-top:12px">
  <thead><tr><th colspan="2"><?= __('Goods & Stock') ?></th></tr></thead>
  <tbody>
    <tr><td style="width:55%"><?= __('Total goods issued (paid value, posted)') ?></td><td class="num"><?= money($stats['goodsIssued'], false) ?></td></tr>
    <tr><td><?= __('Bonus / free goods (Packets · value)') ?></td><td class="num"><?= qty($stats['bonusQty']) ?> · <?= money($stats['bonusValue'], false) ?></td></tr>
    <tr><td><?= __('Goods returned (good Packets · value)') ?></td><td class="num"><?= qty($stats['retGoodUnits']) ?> · <?= money($stats['retValue'], false) ?></td></tr>
    <tr><td><?= __('Stock currently with salesman (Packets · value)') ?></td><td class="num"><?= qty($stats['stockUnits']) ?> · <?= money($stats['stockValue'], false) ?></td></tr>
    <tr><td><strong><?= __('Total goods payable (ledger)') ?></strong></td><td class="num"><strong><?= money($stats['goodsPayable'], false) ?></strong></td></tr>
  </tbody>
</table>
<table class="tbl" style="margin-top:10px">
  <thead><tr><th colspan="2"><?= __('Collections & Payments') ?></th></tr></thead>
  <tbody>
    <tr><td><?= __('Total customer collections') ?></td><td class="num"><?= money((float)($stats['hisab']['collected'] ?? 0), false) ?></td></tr>
    <tr><td><?= __('Cash received by company') ?></td><td class="num"><?= money($stats['handCash'], false) ?></td></tr>
    <tr><td><?= __('Bank received by company') ?></td><td class="num"><?= money($stats['handBank'], false) ?></td></tr>
    <tr><td><strong><?= __('Total money handed over') ?></strong></td><td class="num"><strong><?= money($stats['handTotal'], false) ?></strong></td></tr>
  </tbody>
</table>
<table class="tbl" style="margin-top:10px">
  <thead><tr><th colspan="2"><?= __('Expenses (approved)') ?></th></tr></thead>
  <tbody>
    <tr><td><?= __('Vehicle / Petrol·Diesel') ?></td><td class="num"><?= money($stats['expVehicle'], false) ?></td></tr>
    <tr><td><?= __('Food / Meals') ?></td><td class="num"><?= money($stats['expFood'], false) ?></td></tr>
    <tr><td><?= __('Other expenses') ?></td><td class="num"><?= money($stats['expOther'], false) ?></td></tr>
    <tr><td><strong><?= __('Total approved expenses') ?></strong></td><td class="num"><strong><?= money($stats['expTotal'], false) ?></strong></td></tr>
  </tbody>
</table>
<table class="tbl" style="margin-top:10px">
  <thead><tr><th colspan="2"><?= __('Salary & Advances') ?></th></tr></thead>
  <tbody>
    <tr><td><?= __('Salary earned / paid / remaining') ?></td><td class="num"><?= money($stats['salaryEarned'], false) ?> / <?= money($stats['salaryPaid'], false) ?> / <?= money($stats['salaryRemaining'], false) ?></td></tr>
    <tr><td><?= __('Advances received / recovered / outstanding') ?></td><td class="num"><?= money($stats['advGiven'], false) ?> / <?= money($stats['advRecovered'], false) ?> / <?= money($stats['advOutstanding'], false) ?></td></tr>
  </tbody>
</table>

<?php if (!empty($events)): ?>
<h3 style="margin:14px 0 6px"><?= __('Transaction History (period)') ?></h3>
<table class="tbl" style="font-size:12px"><thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Detail') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th class="num"><?= __('Balance') ?></th></tr></thead>
  <tbody><?php $n = 0; foreach ($events as $e): $n++; if ($n > 60) break; ?><tr>
    <td><?= date_display($e['date']) ?></td>
    <td><?= e($e['type']) ?></td>
    <td><?= e(mb_substr($e['descr'], 0, 60)) ?><?= $e['ref'] ? ' · ' . e($e['ref']) : '' ?></td>
    <td class="num"><?= $e['cash'] ? money($e['cash'], false) : '—' ?></td>
    <td class="num"><?= $e['bank'] ? money($e['bank'], false) : '—' ?></td>
    <td class="num"><?= $e['balance'] !== null ? money($e['balance'], false) : '—' ?></td>
  </tr><?php endforeach; ?></tbody>
</table>
<?php if (count($events) > 60): ?><p class="help"><?= __('… and') ?> <?= count($events) - 60 ?> <?= __('more rows in the selected period (see the Overview tab for the full list).') ?></p><?php endif; ?>
<?php endif; ?>
<p class="help" style="margin-top:12px"><?= __('Status:') ?>POSTED<?= __(' · Generated') ?> <?= dt_display(now_local()) ?><?= __(' · Software: JUSTI NUMBER ONE ERP') ?></p>
<div style="display:flex;justify-content:space-between;margin-top:28px;gap:40px">
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Received by') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Salesman signature') ?></div>
  <div style="text-align:center;min-width:150px;border-top:1px solid var(--charcoal);padding-top:6px"><?= __('Manager') ?></div>
</div>
</div>
<div class="btn-group no-print mt">
  <button class="btn btn-dark" onclick="window.print()">🖨 <?= __('Print / Save as PDF') ?></button>
  <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/overview')) ?>"><?= __('Back to Complete Overview') ?></a>
</div>
