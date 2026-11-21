<?php
$title = __('Salesman Account — ') . e($sm['name']);
$tab = 'account';
require __DIR__ . '/../partials/sm_tabs.php';

$canRecord = (bool)($canRecord ?? false);
$rateMapJson = json_encode($rateMap ?? [], JSON_UNESCAPED_SLASHES);
$statementUrl = url('salesmen/' . (int)$id . '/recovery/statement', array_filter([
    'from' => $filters['from'] ?: null,
    'to'   => $filters['to'] ?: null,
]));

$sumRegular = 0.0; $sumBonus = 0.0; $sumLineQty = 0.0; $sumPayable = 0.0;
foreach ($issuedLines as $ln) {
    $sumRegular = round($sumRegular + (float)$ln['regular_qty'], 3);
    $sumBonus = round($sumBonus + (float)$ln['bonus_qty'], 3);
    $sumLineQty = round($sumLineQty + (float)$ln['line_qty'], 3);
    $sumPayable = round($sumPayable + (float)$ln['payable'], 2);
}

$acceptedQty = 0.0; $damagedQty = 0.0;
foreach ($returnRows as $rr) {
    if (($rr['return_condition'] ?? 'good') === 'damaged') $damagedQty = round($damagedQty + (float)$rr['qty'], 3);
    else $acceptedQty = round($acceptedQty + (float)$rr['qty'], 3);
}
?>
<style>
  .account-section { margin-bottom:16px; scroll-margin-top:12px; }
  .account-section > h2 { margin-top:0; }
  .account-totals { grid-template-columns:repeat(auto-fill,minmax(165px,1fr)); margin:10px 0 12px; }
  .account-toolbar { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; }
  .account-toolbar form { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
  .account-toolbar label { display:block; font-size:11px; font-weight:700; margin-bottom:3px; }
  @media print {
    .tabs-bar, .no-print, .btn-group { display:none !important; }
    .account-section { break-inside:avoid; }
  }
</style>

<header class="simple-hero" style="padding:14px 20px;margin-bottom:10px">
  <div class="greet">
    <span style="font-size:17px;font-weight:800"><?= __('Salesman Account') ?> — <?= e($sm['name']) ?></span><br>
    <small><?= __('Goods Issued → Money Collection → Goods Return. Cash, bank and goods-return credits stay separate; salary and expenses are never deducted here.') ?></small>
  </div>
  <div class="btn-group no-print" style="flex-wrap:wrap;gap:6px">
    <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">🖨 <?= __('Print This Account') ?></button>
    <a class="btn btn-outline btn-sm" href="<?= e($statementUrl) ?>" target="_blank">🖨 <?= __('Print Date-wise Statement') ?></a>
  </div>
</header>

<div class="panel no-print" style="margin-bottom:10px">
  <div class="account-toolbar">
    <form method="get" id="accountSalesmanForm">
      <input type="hidden" name="r" value="salesmen/<?= (int)$id ?>/account">
      <div style="min-width:230px">
        <label for="accountSmSearch"><?= __('Select Salesman') ?></label>
        <input class="f" id="accountSmSearch" placeholder="<?= e(__('Type to search name / code / area…')) ?>" autocomplete="off">
      </div>
      <div style="min-width:260px">
        <label for="accountSmSelect"><?= __('Salesman') ?></label>
        <select class="f" name="switch_id" id="accountSmSelect">
          <?php foreach ($salesmen as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-hay="<?= e(strtolower($s['code'] . ' ' . $s['name'] . ' ' . ($s['area'] ?? '') . ' ' . ($s['phone'] ?? ''))) ?>" <?= (int)$s['id'] === $id ? 'selected' : '' ?>>
              <?= e($s['code'] . ' — ' . $s['name'] . (!empty($s['area']) ? ' — ' . $s['area'] : '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <form method="get">
      <input type="hidden" name="r" value="salesmen/<?= (int)$id ?>/account">
      <div><label for="accountFrom"><?= __('History From') ?></label><input class="f" type="date" id="accountFrom" name="from" value="<?= e($filters['from']) ?>"></div>
      <div><label for="accountTo"><?= __('History To') ?></label><input class="f" type="date" id="accountTo" name="to" value="<?= e($filters['to']) ?>"></div>
      <div><button class="btn btn-outline" type="submit"><?= __('Apply Date Filter') ?></button></div>
    </form>
  </div>
  <p class="help" style="margin:8px 0 0"><?= __('Totals are all-time. The date filter applies to the three history tables below.') ?></p>
</div>

<section class="panel account-section" id="goods-issued">
  <h2>1. <?= __('Goods Issued') ?></h2>
  <div class="stat-strip account-totals">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Products Issued') ?></div><div class="sc-val"><?= (int)$productsIssued ?></div><div class="sc-sub"><?= __('distinct products') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Issued Quantity (Packets)') ?></div><div class="sc-val"><?= qty($totalIssuedQty) ?></div><div class="sc-sub"><?= __('regular + bonus') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Goods Issued Amount') ?></div><div class="sc-val"><?= money($total_invoiced) ?></div><div class="sc-sub"><?= __('payable goods only') ?></div></div>
    <div class="stat-chip" style="border-color:<?= $outstanding > 0 ? '#f2b8b5' : '#cfe8d5' ?>"><div class="sc-lbl"><?= __('Outstanding Invoice Amount') ?></div><div class="sc-val" style="color:<?= $outstanding > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money(abs($outstanding)) ?></div><div class="sc-sub"><?= $outstanding >= 0 ? __('still payable') : __('credit / advance') ?></div></div>
  </div>

  <?php if (!$canRecord): ?>
    <p class="muted no-print"><?= __('Only authorized users can issue goods.') ?></p>
  <?php else: ?>
  <form method="post" action="<?= e(url($back ?? ('salesmen/' . (int)$id . '/account'))) ?>" class="form-grid no-print" id="goodsIssueForm">
    <?= csrf_field() ?>
    <input type="hidden" name="recovery_action" value="issue">
    <input type="hidden" name="idem_key" value="<?= e($idemKey) ?>-iss">
    <div><label class="f"><?= __('Issue Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div><label class="f"><?= __('Salesman') ?></label><input class="f" value="<?= e($sm['code'] . ' — ' . $sm['name']) ?>" disabled></div>
    <div class="span-2"><label class="f"><?= __('Product') ?></label><select class="f" name="product_id" id="issueProduct" required><option value=""><?= __('— select product —') ?></option>
      <?php foreach ($mainProducts ?? [] as $p): ?>
        <option value="<?= (int)$p['id'] ?>" data-rate="<?= e(number_format((float)$p['issue_rate'], 2, '.', '')) ?>" data-avail="<?= e(number_format((float)$p['available_qty'], 3, '.', '')) ?>">
          <?= e($p['sku'] . ' — ' . $p['name'] . ' (Main WH: ' . qty($p['available_qty']) . ' Packets · Default Rate: Rs ' . number_format((float)$p['issue_rate'], 2) . ')') ?>
        </option>
      <?php endforeach; ?>
    </select></div>
    <div><label class="f"><?= __('Regular Quantity (Packets)') ?></label><input class="f" type="number" step="0.001" min="0" name="paid_qty" id="issuePaidQty" value="0"></div>
    <div><label class="f"><?= __('Bonus Quantity (Packets)') ?></label><input class="f" type="number" step="0.001" min="0" name="bonus_qty" id="issueBonusQty" value="0"></div>
    <div><label class="f"><?= __('Total Quantity (Packets)') ?></label><input class="f" id="issueTotalQty" value="0" readonly style="font-weight:700"></div>
    <div><label class="f"><?= __('Issue Rate (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="issue_rate" id="issueRate" value="0.00"></div>
    <div><label class="f"><?= __('Total Payable Amount (auto)') ?></label><input class="f" id="issuePayableText" value="Rs 0.00" readonly style="font-weight:800"></div>
    <div><label class="f"><?= __('Reference / Batch No') ?></label><input class="f" name="reference" placeholder="<?= e(__('e.g. BATCH-01 / INV-01')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Notes / Reason') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. daily route issue / promotional bonus')) ?>"></div>
    <div class="span-2"><p class="help" id="issueMsg" role="status"><?= __('Regular quantity creates payable debt at issue rate. Bonus quantity is free goods (zero payable debt). Stock is transferred from Main Warehouse to salesman van.') ?></p></div>
    <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Issue Goods to Salesman') ?></button></div>
  </form>
  <?php endif; ?>

  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Goods Issued History') ?></h3>
  <?php if (!$issuedLines): ?>
    <p class="muted"><?= __('No issued goods match this date range.') ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr>
      <th><?= __('Date') ?></th><th><?= __('Invoice Number') ?></th><th><?= __('Product Name') ?></th>
      <th class="num"><?= __('Regular Qty (Packets)') ?></th><th class="num"><?= __('Bonus Qty (Packets)') ?></th>
      <th class="num"><?= __('Total Qty') ?></th><th class="num"><?= __('Issue Rate') ?></th><th class="num"><?= __('Total Payable Amount') ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($issuedLines as $ln): ?>
      <tr>
        <td><?= date_display($ln['date']) ?></td>
        <td class="mono"><?= e($ln['issue_no']) ?></td>
        <td><strong><?= e($ln['sku']) ?></strong> <?= e($ln['name']) ?></td>
        <td class="num"><?= qty($ln['regular_qty']) ?></td>
        <td class="num"><?= qty($ln['bonus_qty']) ?></td>
        <td class="num"><?= qty($ln['line_qty']) ?></td>
        <td class="num"><?= money($ln['rate']) ?></td>
        <td class="num"><?= money($ln['payable']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:800;background:#f7f7f7">
      <td colspan="3"><?= __('TOTAL') ?></td>
      <td class="num"><?= qty($sumRegular) ?></td><td class="num"><?= qty($sumBonus) ?></td>
      <td class="num"><?= qty($sumLineQty) ?></td><td></td><td class="num"><?= money($sumPayable) ?></td>
    </tr></tfoot>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel account-section" id="money-collection">
  <h2>2. <?= __('Money Collection') ?></h2>
  <div class="stat-strip account-totals">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Goods Amount Payable') ?></div><div class="sc-val"><?= money($total_invoiced) ?></div><div class="sc-sub"><?= __('before credits') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Cash Received') ?></div><div class="sc-val"><?= money($cash_received) ?></div><div class="sc-sub"><?= __('Cash-in-Hand') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Bank Received') ?></div><div class="sc-val"><?= money($bank_received) ?></div><div class="sc-sub"><?= __('Bank account') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Total Money Collected') ?></div><div class="sc-val"><?= money(round($cash_received + $bank_received, 2)) ?></div><div class="sc-sub"><?= __('cash + bank only') ?></div></div>
    <div class="stat-chip" style="border-color:<?= $outstanding > 0 ? '#f2b8b5' : '#cfe8d5' ?>"><div class="sc-lbl"><?= __('Remaining Amount Payable') ?></div><div class="sc-val" style="color:<?= $outstanding > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money(abs($outstanding)) ?></div><div class="sc-sub"><?= __('after cash, bank, returns, adjustments') ?></div></div>
  </div>

  <?php if (!$canRecord): ?>
    <p class="muted no-print"><?= __('Only authorized users can record money collections.') ?></p>
  <?php else: ?>
  <form method="post" action="<?= e(url($back ?? ('salesmen/' . (int)$id . '/account'))) ?>" class="form-grid no-print" id="moneyCollectionForm">
    <?= csrf_field() ?>
    <input type="hidden" name="recovery_action" value="payment">
    <input type="hidden" name="allocation_mode" value="auto">
    <input type="hidden" name="idem_key" value="<?= e($idemKey) ?>">
    <input type="hidden" name="total_amount" id="moneyTotalHidden" value="0.00">
    <div><label class="f"><?= __('Collection Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div><label class="f"><?= __('Salesman Name') ?></label><input class="f" value="<?= e($sm['code'] . ' — ' . $sm['name']) ?>" disabled></div>
    <div><label class="f"><?= __('Cash Amount (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="cash_amount" id="moneyCash" value="0.00"></div>
    <div><label class="f"><?= __('Bank Amount (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="bank_amount" id="moneyBank" value="0.00"></div>
    <div><label class="f"><?= __('Total Collected (auto)') ?></label><input class="f" id="moneyTotalText" value="Rs 0.00" readonly style="font-weight:800"></div>
    <div><label class="f"><?= __('Cash Account (Cash-in-Hand)') ?></label><select class="f" name="cash_account_id"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Bank Account') ?></label><select class="f" name="bank_account_id" id="moneyBankAccount"><option value=""><?= __('— not used —') ?></option><?php foreach ($bankAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="f"><?= __('Bank Transaction Reference') ?></label><input class="f" name="bank_reference" id="moneyBankRef" placeholder="<?= e(__('e.g. TRX-000123')) ?>"></div>
    <div><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. weekly van collection')) ?>"></div>
    <div class="span-2"><p class="help" id="moneyMsg" role="status"><?= __('Cash + Bank is calculated automatically. Collections are applied to the oldest outstanding invoices first.') ?></p></div>
    <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Add Money Collection') ?></button></div>
  </form>
  <?php endif; ?>

  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Collection History') ?></h3>
  <?php if (!$collections): ?>
    <p class="muted"><?= __('No money collections match this date range.') ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Date') ?></th><th><?= __('Collection / Receipt') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th class="num"><?= __('Total Received') ?></th><th class="num"><?= __('Remaining Balance') ?></th><th><?= __('Reference / Notes') ?></th><th><?= __('Print') ?></th></tr></thead>
    <tbody>
      <?php foreach ($collections as $c): ?>
      <tr>
        <td><?= date_display($c['date']) ?></td>
        <td class="mono"><?= e($c['doc_no'] ?: '#' . $c['key']) ?></td>
        <td class="num"><?= money($c['cash']) ?></td>
        <td class="num"><?= money($c['bank']) ?></td>
        <td class="num" style="font-weight:800"><?= money($c['total']) ?></td>
        <td class="num"><?= money($c['balance']) ?></td>
        <td><?= e($c['reference'] ?: ($c['notes'] ?: '—')) ?><?= $c['method'] !== '' ? '<br><span class="muted">' . e(strtoupper($c['method'])) . '</span>' : '' ?></td>
        <td><?php if (!empty($c['payment_id']) || strpos($c['key'], 'p') === 0): ?><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/payments/' . (int)substr($c['key'], 1) . '/print')) ?>" target="_blank"><?= __('Receipt') ?></a><?php else: ?>—<?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel account-section" id="goods-return">
  <h2>3. <?= __('Goods Return') ?></h2>
  <div class="stat-strip account-totals">
    <div class="stat-chip"><div class="sc-lbl"><?= __('Accepted Saleable Qty') ?></div><div class="sc-val"><?= qty($acceptedQty) ?></div><div class="sc-sub"><?= __('Packets restocked to Main Warehouse') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Damaged / Rejected Qty') ?></div><div class="sc-val"><?= qty($damagedQty) ?></div><div class="sc-sub"><?= __('kept out of saleable stock') ?></div></div>
    <div class="stat-chip"><div class="sc-lbl"><?= __('Approved Goods Return Credit') ?></div><div class="sc-val"><?= money($return_credit) ?></div><div class="sc-sub"><?= __('reduces goods payable') ?></div></div>
  </div>

  <?php if (!$canRecord): ?>
    <p class="muted no-print"><?= __('Only authorized users can record goods returns.') ?></p>
  <?php else: ?>
  <form method="post" action="<?= e(url($back ?? ('salesmen/' . (int)$id . '/account'))) ?>" class="form-grid no-print" id="goodsReturnForm">
    <?= csrf_field() ?><input type="hidden" name="recovery_action" value="return">
    <div><label class="f"><?= __('Return Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div><label class="f"><?= __('Return Status') ?></label><select class="f" name="return_condition" id="accountRetCondition"><option value="good"><?= __('Accepted / Saleable') ?></option><option value="damaged"><?= __('Damaged / Rejected') ?></option></select></div>
    <div><label class="f"><?= __('Product') ?></label><select class="f" name="product_id" id="accountRetProduct" required><option value=""><?= __('— select —') ?></option>
      <?php foreach ($vanStock as $p): ?><option value="<?= (int)$p['id'] ?>" data-rate="<?= e(number_format((float)$p['issue_rate'], 2, '.', '')) ?>"><?= e($p['sku'] . ' — ' . $p['name'] . ' (van: ' . qty($p['qty']) . ')') ?></option><?php endforeach; ?>
    </select></div>
    <div><label class="f"><?= __('Returned Quantity (Packets)') ?></label><input class="f" type="number" step="0.001" min="0.001" name="qty" id="accountRetQty" value="0" required></div>
    <div class="span-2"><label class="f"><?= __('Related Invoice') ?> <span class="help"><?= __('(where possible)') ?></span></label>
      <select class="f" name="related_issue_id" id="accountRetInvoice"><option value=""><?= __('— none — use latest issue rate') ?></option>
        <?php foreach ($invoices as $inv): ?><option value="<?= (int)$inv['id'] ?>"><?= e($inv['issue_no'] . ' · ' . date_display($inv['date'])) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="f"><?= __('Original Issue Rate') ?></label><input class="f" id="accountRetRate" value="0.00" readonly></div>
    <div><label class="f"><?= __('Returned Goods Value') ?></label><input class="f" id="accountRetValue" value="0.00" readonly style="font-weight:800"></div>
    <div><label class="f"><?= __('Reference') ?></label><input class="f" name="reference" placeholder="<?= e(__('e.g. GRN-102')) ?>"></div>
    <div><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. unsold stock')) ?>"></div>
    <div class="span-2"><p class="help" id="accountRetMsg" role="status"></p></div>
    <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Save Goods Return') ?></button></div>
  </form>
  <?php endif; ?>

  <h3 style="font-size:13px;margin:14px 0 6px"><?= __('Goods Return History') ?></h3>
  <?php if (!$returnRows): ?>
    <p class="muted"><?= __('No goods returns match this date range.') ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Product Name') ?></th><th class="num"><?= __('Returned Quantity (Packets)') ?></th><th class="num"><?= __('Original Issue Rate') ?></th><th class="num"><?= __('Total Return Value') ?></th><th><?= __('Return Date') ?></th><th><?= __('Return Status') ?></th><th><?= __('Invoice / Notes') ?></th></tr></thead>
    <tbody>
      <?php foreach ($returnRows as $rr): $isDamaged = ($rr['return_condition'] ?? 'good') === 'damaged'; ?>
      <tr>
        <td><strong><?= e($rr['sku'] ?? '') ?></strong> <?= e($rr['name'] ?? '—') ?></td>
        <td class="num"><?= qty($rr['qty']) ?></td>
        <td class="num"><?= money($rr['issue_rate']) ?></td>
        <td class="num" style="font-weight:800"><?= money($rr['line_payable']) ?></td>
        <td><?= date_display($rr['date']) ?></td>
        <td><span class="badge" style="background:<?= $isDamaged ? '#f8d7da' : '#d9f1df' ?>"><?= $isDamaged ? __('Damaged / Rejected') : __('Accepted / Saleable') ?></span></td>
        <td class="mono"><?= e($rr['issue_no']) ?><br><span class="muted"><?= __('Invoice') ?>: <?= e($rr['invoice_no'] ?: '—') ?></span><?= !empty($rr['notes']) ? '<br><span class="muted">' . e($rr['notes']) . '</span>' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<script>
(function () {
  /* Searchable salesman selector */
  var smSearch = document.getElementById('accountSmSearch');
  var smSelect = document.getElementById('accountSmSelect');
  if (smSearch && smSelect) {
    smSearch.addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      smSelect.querySelectorAll('option[data-hay]').forEach(function (o) {
        var show = !q || o.getAttribute('data-hay').indexOf(q) !== -1;
        o.hidden = !show;
        o.style.display = show ? '' : 'none';
      });
    });
    smSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); smSelect.focus(); } });
    smSelect.addEventListener('change', function () {
      if (this.value) {
        window.location = 'index.php?r=salesmen/' + encodeURIComponent(this.value) + '/account';
      }
    });
  }

  /* Goods issue: live calculate total quantity (regular + bonus) and payable */
  var iProduct = document.getElementById('issueProduct');
  var iPaidQty = document.getElementById('issuePaidQty');
  var iBonusQty = document.getElementById('issueBonusQty');
  var iTotalQty = document.getElementById('issueTotalQty');
  var iRate = document.getElementById('issueRate');
  var iPayable = document.getElementById('issuePayableText');
  var iMsg = document.getElementById('issueMsg');
  function syncIssue() {
    if (!iProduct) return;
    var paid = Math.max(0, parseFloat(iPaidQty ? iPaidQty.value : '0') || 0);
    var bonus = Math.max(0, parseFloat(iBonusQty ? iBonusQty.value : '0') || 0);
    var total = Math.round((paid + bonus) * 1000) / 1000;
    if (iTotalQty) iTotalQty.value = total;

    var opt = iProduct.selectedOptions ? iProduct.selectedOptions[0] : null;
    var avail = opt ? (parseFloat(opt.getAttribute('data-avail')) || 0) : 0;
    var rate = Math.max(0, parseFloat(iRate ? iRate.value : '0') || 0);
    var payable = Math.round(paid * rate * 100) / 100;
    if (iPayable) iPayable.value = 'Rs ' + payable.toFixed(2);

    if (iMsg) {
      if (opt && opt.value && total > avail + 0.0001) {
        iMsg.textContent = '⚠ <?= __('Warning: Total quantity exceeds available Main Warehouse stock (') ?>' + avail + ' <?= __('Packets') ?>).';
        iMsg.style.color = '#8f1d13';
      } else if (total > 0) {
        iMsg.textContent = '✓ <?= __('Total') ?> ' + total + ' <?= __('Packets') ?> (' + paid + ' <?= __('regular') ?> + ' + bonus + ' <?= __('bonus') ?>) · <?= __('Payable') ?>: Rs ' + payable.toFixed(2);
        iMsg.style.color = '#1d6530';
      } else {
        iMsg.textContent = '<?= __('Enter regular or bonus quantity in Packets.') ?>';
        iMsg.style.color = '';
      }
    }
  }
  if (iProduct) {
    iProduct.addEventListener('change', function () {
      var opt = this.selectedOptions ? this.selectedOptions[0] : null;
      if (opt && opt.value) {
        var defaultRate = parseFloat(opt.getAttribute('data-rate')) || 0;
        if (iRate) iRate.value = defaultRate.toFixed(2);
      }
      syncIssue();
    });
  }
  [iPaidQty, iBonusQty, iRate].forEach(function (el) {
    if (el) { el.addEventListener('input', syncIssue); el.addEventListener('change', syncIssue); }
  });
  syncIssue();

  /* Money collection: Cash + Bank = Total, calculated automatically */
  var cash = document.getElementById('moneyCash');
  var bank = document.getElementById('moneyBank');
  var totalText = document.getElementById('moneyTotalText');
  var totalHidden = document.getElementById('moneyTotalHidden');
  var bankAccount = document.getElementById('moneyBankAccount');
  var bankRef = document.getElementById('moneyBankRef');
  var moneyMsg = document.getElementById('moneyMsg');
  function syncMoney() {
    var c = Math.round((parseFloat(cash ? cash.value : '0') || 0) * 100) / 100;
    var b = Math.round((parseFloat(bank ? bank.value : '0') || 0) * 100) / 100;
    var t = Math.round((c + b) * 100) / 100;
    if (totalText) totalText.value = 'Rs ' + t.toFixed(2);
    if (totalHidden) totalHidden.value = t.toFixed(2);
    if (bankRef && bankAccount) {
      bankRef.required = b > 0;
      bankAccount.required = b > 0;
    }
    if (moneyMsg) {
      if (t <= 0) { moneyMsg.textContent = '<?= __('Enter a cash or bank amount to add a collection.') ?>'; moneyMsg.style.color = '#8f1d13'; }
      else { moneyMsg.textContent = '✓ <?= __('Total Collected') ?>: Rs ' + t.toFixed(2) + ' — <?= __('applied oldest invoice first') ?>'; moneyMsg.style.color = '#1d6530'; }
    }
  }
  [cash, bank].forEach(function (el) { if (el) el.addEventListener('input', syncMoney); });
  syncMoney();

  /* Goods return: fetch the original issue rate and calculate credit */
  var rateMap = <?= $rateMapJson ?: '{}' ?>;
  var rInvoice = document.getElementById('accountRetInvoice');
  var rProduct = document.getElementById('accountRetProduct');
  var rQty = document.getElementById('accountRetQty');
  var rRate = document.getElementById('accountRetRate');
  var rValue = document.getElementById('accountRetValue');
  var rCond = document.getElementById('accountRetCondition');
  var rMsg = document.getElementById('accountRetMsg');
  function syncReturn() {
    if (!rProduct || !rRate || !rValue) return;
    var pid = rProduct.value ? parseInt(rProduct.value, 10) : 0;
    var iid = rInvoice && rInvoice.value ? parseInt(rInvoice.value, 10) : 0;
    var rate = 0;
    if (iid && rateMap[iid]) {
      if (rateMap[iid][pid] === undefined) {
        rate = 0;
        if (rMsg) { rMsg.textContent = '<?= __('This product is not on the selected invoice.') ?>'; rMsg.style.color = '#8f1d13'; }
      } else { rate = parseFloat(rateMap[iid][pid]) || 0; }
    } else {
      var opt = rProduct.selectedOptions[0];
      rate = opt ? (parseFloat(opt.getAttribute('data-rate')) || 0) : 0;
    }
    if (rMsg && (!iid || (rateMap[iid] && rateMap[iid][pid] !== undefined))) { rMsg.textContent = ''; }
    rRate.value = 'Rs ' + rate.toFixed(2);
    var damaged = rCond && rCond.value === 'damaged';
    var value = damaged ? 0 : Math.round((parseFloat(rQty ? rQty.value : '0') || 0) * rate * 100) / 100;
    rValue.value = 'Rs ' + value.toFixed(2);
  }
  [rInvoice, rProduct, rQty, rCond].forEach(function (el) {
    if (el) { el.addEventListener('change', syncReturn); el.addEventListener('input', syncReturn); }
  });
  syncReturn();
})();
</script>
