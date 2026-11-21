<?php $title = 'Recovery Statement — ' . $sm['name']; ?>
<div class="no-print" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
  <form method="get" class="toolbar" style="margin:0">
    <input type="hidden" name="r" value="salesmen/<?= (int)$id ?>/recovery/statement">
    <label class="muted"><?= __('From') ?></label><input class="f" type="date" name="from" value="<?= e($from) ?>">
    <label class="muted"><?= __('To') ?></label><input class="f" type="date" name="to" value="<?= e($to) ?>">
    <label class="muted"><?= __('Invoice') ?></label>
    <select class="f" name="invoice_id"><option value=""><?= __('— all —') ?></option>
      <?php foreach ($invoices as $inv): ?><option value="<?= (int)$inv['id'] ?>" <?= (int)$invoice_id === (int)$inv['id'] ? 'selected' : '' ?>><?= e($inv['issue_no']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= __('Show') ?></button>
  </form>
  <div class="btn-group">
    <button class="btn btn-dark" type="button" onclick="window.print()">🖨 <?= __('Print / Save as PDF') ?></button>
    <a class="btn btn-outline" href="<?= e(url('salesmen/' . (int)$id . '/recovery')) ?>"><?= __('Back to Recovery') ?></a>
  </div>
</div>

<div class="print-area">
  <?= partial('partials/doc_head', ['docTitle' => __('SALESMAN RECOVERY STATEMENT'), 'docNo' => $sm['code'] ?? '', 'docDate' => date_display($to)]) ?>
  <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
    <div>
      <div class="help"><?= __('SALESMAN') ?></div>
      <strong style="font-size:16px"><?= e($sm['name']) ?></strong><br>
      <span class="muted"><?= __('Code:') ?> <?= e($sm['code'] ?: '—') ?></span>
    </div>
    <div style="text-align:right">
      <div class="help"><?= __('PERIOD') ?></div>
      <strong><?= date_display($from) ?> → <?= date_display($to) ?></strong><br>
      <span class="muted"><?= __('Generated') ?> <?= dt_display(now_local()) ?></span>
    </div>
  </div>

  <div class="att-counts" style="margin-top:14px">
    <span class="att-count"><?= __('Total Goods Issued') ?><b><?= money($total_invoiced) ?></b></span>
    <span class="att-count"><?= __('Total Recovered') ?><b><?= money($total_recovered) ?></b></span>
    <span class="att-count"><?= __('Cash Received') ?><b><?= money($cash_received) ?></b></span>
    <span class="att-count"><?= __('Bank Received') ?><b><?= money($bank_received) ?></b></span>
    <span class="att-count"><?= __('Goods Return Credit') ?><b><?= money($return_credit) ?></b></span>
    <span class="att-count" style="border-color:<?= $outstanding > 0 ? '#f2b8b5' : '#cfe8d5' ?>"><?= __('Outstanding') ?><b style="color:<?= $outstanding > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money($outstanding) ?></b></span>
    <span class="att-count"><?= __('Pending Invoices') ?><b><?= (int)$pending_invoices ?></b></span>
  </div>

  <h2 style="margin-top:18px"><?= __('Invoice Position') ?></h2>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Invoice #') ?></th><th><?= __('Date') ?></th><th class="num"><?= __('Invoice Amount') ?></th><th class="num"><?= __('Recovered') ?></th><th class="num"><?= __('Return Credit') ?></th><th class="num"><?= __('Remaining') ?></th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td class="mono"><?= e($inv['issue_no']) ?></td>
          <td><?= date_display($inv['date']) ?></td>
          <td class="num"><?= money($inv['amount'], false) ?></td>
          <td class="num"><?= money($inv['paid'], false) ?></td>
          <td class="num"><?= money($inv['returned'], false) ?></td>
          <td class="num"><strong><?= money($inv['remaining'], false) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="6" class="muted"><?= __('No posted goods invoices.') ?></td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr style="font-weight:800;background:#f7f7f7">
      <td colspan="2"><?= __('TOTAL') ?></td>
      <td class="num"><?= money($total_invoiced, false) ?></td>
      <td class="num"><?= money($total_paid, false) ?></td>
      <td class="num"><?= money($total_return_credit, false) ?></td>
      <td class="num"><?= money(round($total_invoiced - $total_paid - $total_return_credit, 2), false) ?></td>
    </tr></tfoot>
  </table></div>

  <h2 style="margin-top:18px"><?= __('Chronological Recovery History') ?></h2>
  <p class="help"><?= __('Opening balance') ?>: <?= money($history['opening']) ?> · <?= __('Closing balance') ?>: <?= money($history['closing']) ?></p>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Date') ?></th><th><?= __('Invoice Number') ?></th><th><?= __('Type / Reference') ?></th><th class="num"><?= __('Goods Issued') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th class="num"><?= __('Return Credit') ?></th><th class="num"><?= __('Remaining Outstanding') ?></th></tr></thead>
    <tbody>
      <?php foreach ($history['rows'] as $e): ?>
        <tr>
          <td><?= date_display($e['date']) ?></td>
          <td class="mono"><?= e($e['invoice_no'] ?: '—') ?></td>
          <td><strong><?= e(ucfirst($e['type'])) ?></strong><br><span class="muted mono"><?= e($e['doc_no']) ?></span>
            <?php if (!empty($e['reference'])): ?><br><span class="muted" style="font-size:11px"><?= e($e['reference']) ?></span><?php endif; ?>
            <?php if ($e['notes'] !== ''): ?><br><span class="muted" style="font-size:11px"><?= e($e['notes']) ?></span><?php endif; ?>
          </td>
          <td class="num"><?= $e['goods'] > 0 ? money($e['goods'], false) : '—' ?></td>
          <td class="num"><?= $e['cash'] > 0 ? money($e['cash'], false) : '—' ?></td>
          <td class="num"><?= $e['bank'] > 0 ? money($e['bank'], false) : '—' ?></td>
          <td class="num"><?= $e['return_credit'] > 0 ? money($e['return_credit'], false) : '—' ?></td>
          <td class="num"><strong><?= money($e['balance'], false) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$history['rows']): ?><tr><td colspan="8" class="muted"><?= __('No transactions in this period.') ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <p class="help" style="margin-top:16px"><?= __('Formula') ?>: <?= __('Posted goods invoices − cash payments − bank payments − approved goods return credits − approved adjustments. Salary, expenses and advances are excluded.') ?></p>
  <p class="help" style="text-align:right"><?= __('Software: JUSTI NUMBER ONE ERP') ?></p>
</div>
