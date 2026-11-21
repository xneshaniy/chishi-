<?php $title = __('Statement —') . e($customer['name']); ?>
<div class="print-area">
  <div class="panel" style="box-shadow:none;border:none">
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:center;border-bottom:2px solid var(--green);padding-bottom:10px">
      <img src="<?= asset('assets/img/ctc-logo.svg') ?>" alt="CTC" style="height:90px">
      <div style="text-align:right">
        <strong style="font-size:16px"><?= e(setting('company_name', cfg('app.company'))) ?></strong><br>
        <span class="muted"><?= e(setting('address')) ?><?= setting('phone') ? ' · ' . e(setting('phone')) : '' ?></span><br>
        <?php if (setting('ntn') || setting('strn')): ?><span class="muted"><?= __('NTN:') ?><?= e(setting('ntn')) ?> <?= setting('strn') ? '· STRN: ' . e(setting('strn')) : '' ?></span><?php endif; ?>
      </div>
    </div>
    <h2 style="justify-content:space-between"><?= __('Customer Statement —') ?><?= e($customer['name']) ?> <span class="no-print"><button class="btn btn-dark btn-sm" onclick="window.print()"><?= __('🖨 Print') ?></button></span></h2>
    <div class="form-grid">
      <div><strong><?= __('Code:') ?></strong> <?= e($customer['code']) ?></div>
      <div><strong><?= __('Phone:') ?></strong> <?= e($customer['phone']) ?></div>
      <div><strong><?= __('Area:') ?></strong> <?= e($customer['area'] ?? '—') ?></div>
      <div><strong><?= __('Credit Limit:') ?></strong> <?= money($customer['credit_limit']) ?></div>
    </div>
    <form method="get" class="toolbar no-print mt"><input type="hidden" name="r" value="customers/<?= (int)$customer['id'] ?>/statement">
      <label class="muted"><?= __('From') ?></label><input type="date" name="from" value="<?= e($from) ?>">
      <label class="muted"><?= __('To') ?></label><input type="date" name="to" value="<?= e($to) ?>">
      <button class="btn btn-outline" type="submit"><?= __('Apply') ?></button>
    </form>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= __('Date') ?></th><th><?= __('Voucher') ?></th><th><?= __('Type') ?></th><th><?= __('Memo') ?></th><th class="num"><?= __('Debit') ?></th><th class="num"><?= __('Credit') ?></th><th class="num"><?= __('Balance') ?></th></tr></thead>
      <tbody>
        <tr><td><?= $from ? date_display($from) : '—' ?></td><td><?= __('OB') ?></td><td><?= __('OPENING') ?></td><td><?= __('Opening balance as of') ?><?= $from ? date_display($from) : 'start' ?></td><td class="num"><?= $periodOpening > 0 ? number_format($periodOpening, 2) : '' ?></td><td class="num"><?= $periodOpening < 0 ? number_format(-$periodOpening, 2) : '' ?></td><td class="num"><?= number_format($periodOpening, 2) ?></td></tr>
        <?php $run = $periodOpening; foreach ($lines as $l): $run += $l['debit'] - $l['credit']; ?>
        <tr><td><?= date_display($l['date']) ?></td><td class="mono"><?= e($l['entry_no']) ?></td><td><?= e($l['voucher_type']) ?></td><td><?= e($l['memo']) ?></td>
            <td class="num"><?= $l['debit'] ? number_format($l['debit'], 2) : '' ?></td><td class="num"><?= $l['credit'] ? number_format($l['credit'], 2) : '' ?></td><td class="num"><?= number_format($run, 2) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="6"><?= __('Ledger Balance (opening + all posted journals)') ?></th><th class="num"><?= money($openingSigned + $gl) ?></th></tr></tfoot>
    </table></div>

    <div class="two-col mt">
      <div>
        <h3 style="font-size:13px"><?= __('Aging of Outstanding (FIFO allocation)') ?></h3>
        <ul class="list-clean">
          <li><span><?= __('Current / not yet due') ?></span><strong><?= money($aging['buckets']['current']) ?></strong></li>
          <li><span><?= __('1–30 days overdue') ?></span><strong><?= money($aging['buckets']['d1_30']) ?></strong></li>
          <li><span><?= __('31–60 days') ?></span><strong><?= money($aging['buckets']['d31_60']) ?></strong></li>
          <li><span><?= __('61–90 days') ?></span><strong><?= money($aging['buckets']['d61_90']) ?></strong></li>
          <li><span><?= __('Over 90 days') ?></span><strong><?= money($aging['buckets']['d90_plus']) ?></strong></li>
          <li><span><strong><?= __('Total outstanding (open invoices)') ?></strong></span><strong><?= money($aging['total_outstanding']) ?></strong></li>
        </ul>
      </div>
      <div>
        <p class="help">Statement includes opening balance and all posted journal lines tagged to this customer (sales, receipts, returns). Print or save as PDF from the print dialog (choose “Save as PDF”).</p>
        <p class="help"><?= __('Generated:') ?><?= dt_display(now_local()) ?> · This is a system-generated document.</p>
      </div>
    </div>
  </div>
</div>
