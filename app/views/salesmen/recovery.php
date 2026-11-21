<?php
$title = __('Recovery / Payment Collection - ') . e($sm['name']);
$tab = 'recovery';
require __DIR__ . '/../partials/sm_tabs.php';
$canRecord = Auth::can('salesmen.settle');
$rateMapJson = json_encode($rateMap ?? [], JSON_UNESCAPED_SLASHES);
$invoiceOptions = [];
foreach ($invoices as $inv) {
    $invoiceOptions[] = ['id' => $inv['id'], 'label' => $inv['issue_no'] . ' · ' . date_display($inv['date']) . ' · ' . money($inv['remaining'], false) . ' due'];
}
?>
<div class="simple-hero no-print" style="padding:14px 20px;margin-bottom:10px;display:flex;gap:16px;justify-content:space-between;align-items:center;flex-wrap:wrap">
  <div class="greet">
    <span style="font-size:17px;font-weight:800"><?= __('Recovery / Payment Collection') ?> — <?= e($sm['name']) ?></span><br>
    <small><?= __('All issued product invoices, cash/bank recoveries and goods return credits in one place. Salary, expenses and advances are never included.') ?></small>
  </div>
  <a class="btn btn-outline btn-sm no-print" href="<?= e(url('salesmen/' . (int)$id . '/recovery/statement', array_filter(['from' => $filters['from'], 'to' => $filters['to'], 'invoice_id' => $filters['invoice_id'] ?: null]))) ?>" target="_blank">🖨 <?= __('Print Recovery Statement') ?></a>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(155px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Goods Issued Amount') ?></div><div class="sc-val"><?= money($total_invoiced) ?></div><div class="sc-sub"><?= count($invoices) ?> <?= __('posted invoice(s)') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Amount Recovered') ?></div><div class="sc-val"><?= money($total_recovered) ?></div><div class="sc-sub"><?= __('payments + return credits') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash Received') ?></div><div class="sc-val"><?= money($cash_received) ?></div><div class="sc-sub"><?= __('Cash-in-Hand') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank Received') ?></div><div class="sc-val"><?= money($bank_received) ?></div><div class="sc-sub"><?= __('Bank account') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Value of Goods Returned') ?></div><div class="sc-val"><?= money($return_credit) ?></div><div class="sc-sub"><?= __('accepted good returns') ?></div></div>
  <div class="stat-chip" style="border-color:<?= $outstanding > 0 ? '#f2b8b5' : '#cfe8d5' ?>"><div class="sc-lbl"><?= __('Total Outstanding Amount') ?></div><div class="sc-val" style="color:<?= $outstanding > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money(abs($outstanding)) ?></div><div class="sc-sub"><?= $outstanding >= 0 ? __('still payable') : __('credit / advance') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Pending Invoices') ?></div><div class="sc-val"><?= (int)$pending_invoices ?></div><div class="sc-sub"><?= __('with balance due') ?></div></div>
</div>
<?php if ($on_account_payment > 0.009 || $on_account_return > 0.009): ?>
  <p class="help"><?= __('On account / advance:') ?> <?= money(round($on_account_payment + $on_account_return, 2)) ?> <?= __('— payment or return credit above today’s outstanding invoices. It reduces future invoices automatically.') ?></p>
<?php endif; ?>
<?php if ($adjustment_credit > 0 || $adjustment_debit > 0): ?>
  <p class="help"><?= __('Approved adjustments included in the formula:') ?> <?= money(round($adjustment_credit - $adjustment_debit, 2)) ?></p>
<?php endif; ?>

<div class="panel" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h2 style="margin:0"><?= __('All Issued Invoices') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= count($invoices) ?>)</span></h2>
    <span class="help"><?= __('Every posted goods invoice appears automatically — no selection needed.') ?></span>
  </div>
  <?php if (!$invoices): ?><p class="muted" style="margin-top:10px"><?= __('No posted goods invoices for this salesman yet.') ?></p><?php else: ?>
  <div class="table-wrap" style="margin-top:10px"><table class="tbl">
    <thead><tr><th><?= __('Invoice #') ?></th><th><?= __('Date') ?></th><th><?= __('Product Details') ?></th><th class="num"><?= __('Invoice Amount') ?></th><th class="num"><?= __('Recovered (Cash/Bank)') ?></th><th class="num"><?= __('Return Credit') ?></th><th class="num"><?= __('Remaining') ?></th><th><?= __('Status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv):
        $rem = (float)$inv['remaining'];
        $status = $rem <= 0.009 ? __('Settled') : (((float)$inv['paid'] > 0 || (float)$inv['returned'] > 0) ? __('Partial') : __('Pending'));
      ?>
      <tr>
        <td class="mono"><?= e($inv['issue_no']) ?></td>
        <td><?= date_display($inv['date']) ?></td>
        <td>
          <?php foreach ($inv['items'] as $it): ?>
            <div style="margin-bottom:2px"><strong><?= e($it['sku']) ?></strong> <?= e($it['name']) ?>
              <span class="muted">— <?= qty($it['paid_qty']) ?> <?= __('Packets') ?> @ <?= money($it['issue_rate'], false) ?></span>
              <?php if ((float)$it['bonus_qty'] > 0): ?><span class="muted"> · +<?= qty($it['bonus_qty']) ?> <?= __('bonus Packets') ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </td>
        <td class="num"><?= money($inv['amount']) ?></td>
        <td class="num"><?= money($inv['paid']) ?></td>
        <td class="num"><?= money($inv['returned']) ?></td>
        <td class="num" style="font-weight:700;color:<?= $rem > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money($rem) ?></td>
        <td><span class="badge" style="background:<?= $status === __('Settled') ? '#d9f1df' : ($status === __('Partial') ? '#fff1ce' : '#f8d7da') ?>"><?= e($status) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:800;background:#f7f7f7">
      <td colspan="3"><?= __('TOTAL') ?></td>
      <td class="num"><?= money($total_invoiced) ?></td>
      <td class="num"><?= money($total_paid) ?></td>
      <td class="num"><?= money($total_return_credit) ?></td>
      <td class="num"><?= money(round($total_invoiced - $total_paid - $total_return_credit, 2)) ?></td>
      <td></td>
    </tr></tfoot>
  </table></div>
  <?php endif; ?>
</div>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Add New Recovery / Payment Entry') ?></h2>
    <p class="help"><?= __('Cash only, bank only, or cash + bank. Cash + bank must equal the total received.') ?></p>
    <?php if (!$canRecord): ?>
      <p class="muted"><?= __('Only authorized users (Owner / Admin / Manager) can record recoveries. No separate payment approval is required for money received.') ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('salesmen/' . (int)$id . '/recovery')) ?>" class="form-grid" id="payForm">
      <?= csrf_field() ?><input type="hidden" name="recovery_action" value="payment"><input type="hidden" name="idem_key" value="<?= e($idemKey) ?>">
      <div><label class="f"><?= __('Recovery Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
      <div><label class="f"><?= __('Salesman Name') ?></label><input class="f" value="<?= e($sm['code'] . ' — ' . $sm['name']) ?>" disabled></div>
      <div class="span-2"><label class="f"><?= __('Total Payment Received (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="total_amount" id="totalAmount" required></div>
      <div><label class="f"><?= __('Cash Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="cash_amount" id="cashAmount" value="0.00"></div>
      <div><label class="f"><?= __('Bank Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="bank_amount" id="bankAmount" value="0.00"></div>
      <div><label class="f"><?= __('Cash Account (Cash-in-Hand)') ?></label><select class="f" name="cash_account_id" id="cashAccount"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="f"><?= __('Bank Account') ?></label><select class="f" name="bank_account_id" id="bankAccount"><option value=""><?= __('— not used —') ?></option><?php foreach ($bankAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div class="span-2"><label class="f"><?= __('Bank Transaction Reference') ?> <span id="bankRefHint" class="help"><?= __('(required when bank amount > 0)') ?></span></label><input class="f" name="bank_reference" placeholder="<?= e(__('e.g. CHQ-000123 / TRX-987654321')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. weekly collection from van')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Received By') ?></label><input class="f" value="<?= e($currentUserName) ?>" disabled></div>
      <div class="span-2">
        <label class="f" style="display:inline-block;margin-right:16px"><input type="radio" name="allocation_mode" value="auto" checked> <?= __('Automatic — oldest invoice first') ?></label>
        <label class="f" style="display:inline-block"><input type="radio" name="allocation_mode" value="manual" id="manualMode"> <?= __('Manual allocation') ?></label>
      </div>
      <div class="span-2" id="allocPanel" style="display:none">
        <div class="help" style="font-weight:700;margin-bottom:4px"><?= __('Allocate this payment against outstanding invoices') ?></div>
        <div class="table-wrap"><table class="tbl" style="font-size:12px"><thead><tr><th><?= __('Invoice') ?></th><th><?= __('Due') ?></th><th class="num"><?= __('Allocate (Rs)') ?></th></tr></thead><tbody>
          <?php foreach ($invoices as $inv): if ((float)$inv['remaining'] <= 0.009) continue; ?>
          <tr><td class="mono"><?= e($inv['issue_no']) ?> <span class="muted"><?= date_display($inv['date']) ?></span></td><td><?= money($inv['remaining']) ?></td>
            <td class="num"><input class="f allocInput" style="width:110px" type="number" step="0.01" min="0" max="<?= e(number_format((float)$inv['remaining'], 2, '.', '')) ?>" name="allocations[<?= (int)$inv['id'] ?>]" value="0.00"></td></tr>
          <?php endforeach; ?>
          <?php if ((int)$pending_invoices === 0): ?><tr><td colspan="3" class="muted"><?= __('No outstanding invoices.') ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <div id="allocMsg" class="help" role="status" style="font-weight:700;margin-top:5px"></div>
      </div>
      <div class="span-2"><div id="splitMsg" class="help" role="status" style="font-weight:700"></div></div>
      <div class="span-2"><button class="btn btn-primary btn-xl" type="submit" id="paySubmit"><?= __('Receive Recovery Payment') ?></button></div>
    </form>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2><?= __('Goods Returned by Salesman') ?></h2>
    <p class="help"><?= __('Unsold saleable goods go back to Main Warehouse and reduce the payable at the original issue rate. Damaged goods are kept separate with no credit.') ?></p>
    <?php if (!$canRecord): ?>
      <p class="muted"><?= __('Only authorized users can record goods returns here.') ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('salesmen/' . (int)$id . '/recovery')) ?>" class="form-grid" id="returnForm">
      <?= csrf_field() ?><input type="hidden" name="recovery_action" value="return">
      <div><label class="f"><?= __('Return Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
      <div><label class="f"><?= __('Condition') ?></label><select class="f" name="return_condition" id="retCondition"><option value="good"><?= __('Good / Accepted') ?></option><option value="damaged"><?= __('Damaged / Rejected') ?></option></select></div>
      <div class="span-2"><label class="f"><?= __('Related Salesman Invoice') ?> <span class="help"><?= __('(where possible)') ?></span></label>
        <select class="f" name="related_issue_id" id="retInvoice"><option value=""><?= __('— none — use latest issue rate') ?></option>
          <?php foreach ($invoiceOptions as $opt): ?><option value="<?= (int)$opt['id'] ?>"><?= e($opt['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="f"><?= __('Product') ?></label><select class="f" name="product_id" id="retProduct" required><option value=""><?= __('— select —') ?></option>
          <?php foreach ($vanStock as $p): ?><option value="<?= (int)$p['id'] ?>" data-rate="<?= e(number_format((float)$p['issue_rate'], 2, '.', '')) ?>"><?= e($p['sku'] . ' — ' . $p['name'] . ' (van: ' . qty($p['qty']) . ')') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="f"><?= __('Returned Qty (Packets)') ?></label><input class="f" type="number" step="0.001" min="0.001" name="qty" id="retQty" value="0" required></div>
      <div><label class="f"><?= __('Original Issue Rate (Rs)') ?></label><input class="f" type="text" id="retRate" readonly value="0.00"></div>
      <div><label class="f"><?= __('Goods Return Credit (Rs)') ?></label><input class="f" type="text" id="retCredit" readonly value="0.00" style="font-weight:800"></div>
      <div class="span-2"><label class="f"><?= __('Reference') ?></label><input class="f" name="reference" placeholder="<?= e(__('e.g. GRN-102 / carrier note')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. unsold stock from route 3')) ?>"></div>
      <div class="span-2"><div id="retMsg" class="help" role="status" style="font-weight:700"></div></div>
      <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Post Goods Return') ?></button></div>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h2 style="margin:0"><?= __('Recovery History') ?></h2>
    <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/recovery/statement', array_filter(['from' => $filters['from'], 'to' => $filters['to'], 'invoice_id' => $filters['invoice_id'] ?: null]))) ?>" target="_blank">🖨 <?= __('Print Statement') ?></a>
  </div>
  <form method="get" class="toolbar no-print"><input type="hidden" name="r" value="salesmen/<?= (int)$id ?>/recovery">
    <label class="muted"><?= __('From') ?></label><input class="f" type="date" name="from" value="<?= e($filters['from']) ?>">
    <label class="muted"><?= __('To') ?></label><input class="f" type="date" name="to" value="<?= e($filters['to']) ?>">
    <label class="muted"><?= __('Invoice') ?></label><select class="f" name="invoice_id"><option value=""><?= __('— all —') ?></option>
      <?php foreach ($invoices as $inv): ?><option value="<?= (int)$inv['id'] ?>" <?= (int)$filters['invoice_id'] === (int)$inv['id'] ? 'selected' : '' ?>><?= e($inv['issue_no']) ?></option><?php endforeach; ?>
    </select>
    <label class="muted"><?= __('Method') ?></label><select class="f" name="method">
      <?php foreach (['all' => __('All'), 'cash' => __('Cash'), 'bank' => __('Bank'), 'split' => __('Cash + Bank'), 'return' => __('Goods Return')] as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $filters['method'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= __('Filter') ?></button>
  </form>
  <p class="help"><?= __('Opening') ?>: <?= money($history['opening']) ?> · <?= __('Closing') ?>: <?= money($history['closing']) ?></p>
  <?php if (!$history['rows']): ?><p class="muted"><?= __('No transactions match these filters.') ?></p><?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Date') ?></th><th><?= __('Invoice Number') ?></th><th class="num"><?= __('Goods Issued') ?></th><th class="num"><?= __('Cash Received') ?></th><th class="num"><?= __('Bank Received') ?></th><th class="num"><?= __('Goods Return Credit') ?></th><th class="num"><?= __('Total Recovered') ?></th><th class="num"><?= __('Remaining Outstanding') ?></th><th><?= __('Transaction Reference') ?></th></tr></thead>
    <tbody><?php foreach ($history['rows'] as $e): ?>
      <tr>
        <td><?= date_display($e['date']) ?></td>
        <td>
          <span class="badge" style="background:<?= $e['type'] === 'invoice' ? '#e7f0f8' : ($e['type'] === 'return' ? '#fff1ce' : ($e['type'] === 'payment' ? '#d9f1df' : '#eee')) ?>"><?= e(ucfirst($e['type'])) ?></span>
          <?= $e['invoice_no'] !== '' ? '<br><span class="mono">' . e($e['invoice_no']) . '</span>' : '' ?>
          <?= $e['doc_no'] !== '' && $e['doc_no'] !== $e['invoice_no'] ? '<br><span class="muted mono">' . e($e['doc_no']) . '</span>' : '' ?>
        </td>
        <td class="num"><?= $e['goods'] > 0 ? money($e['goods']) : '—' ?></td>
        <td class="num"><?= $e['cash'] > 0 ? money($e['cash']) : '—' ?></td>
        <td class="num"><?= $e['bank'] > 0 ? money($e['bank']) : '—' ?></td>
        <td class="num"><?= $e['return_credit'] > 0 ? money($e['return_credit']) : '—' ?></td>
        <td class="num"><?= $e['total_recovered'] > 0 ? money($e['total_recovered']) : '—' ?></td>
        <td class="num" style="font-weight:700"><?= money($e['balance']) ?></td>
        <td>
          <?php if (!empty($e['reference'])): ?><span class="mono"><?= e($e['reference']) ?></span><br><?php endif; ?>
          <?php if (!empty($e['payment_id'])): ?><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/payments/' . (int)$e['payment_id'] . '/print')) ?>" target="_blank"><?= __('Receipt') ?></a><?php endif; ?>
          <?php if ($e['notes'] !== ''): ?><div class="muted" style="font-size:11px"><?= e($e['notes']) ?></div><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($payments): ?>
<div class="panel" style="margin-bottom:14px">
  <h2><?= __('Recovery Payments') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= count($payments) ?>)</span></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Receipt #') ?></th><th class="num"><?= __('Total') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th><?= __('Cash Account') ?></th><th><?= __('Bank Account / Reference') ?></th><th><?= __('Received By') ?></th><th><?= __('Notes') ?></th><th><?= __('Print') ?></th></tr></thead>
    <tbody><?php foreach ($payments as $p): ?><tr>
      <td><?= date_display($p['date']) ?></td>
      <td class="mono"><?= e($p['receipt_no'] ?: '#' . $p['id']) ?></td>
      <td class="num"><?= money($p['total_amount']) ?></td>
      <td class="num"><?= money($p['cash_amount']) ?></td>
      <td class="num"><?= money($p['bank_amount']) ?></td>
      <td><?= e($p['cash_account'] ?: '—') ?></td>
      <td><?= e($p['bank_account'] ?: '—') ?><?= !empty($p['bank_reference']) ? '<br><span class="muted" style="font-size:11px">' . e($p['bank_reference']) . '</span>' : '' ?></td>
      <td><?= e($p['received_full_name'] ?: $p['received_username'] ?: '—') ?></td>
      <td><?= e($p['notes'] ?: '—') ?></td>
      <td><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/payments/' . (int)$p['id'] . '/print')) ?>" target="_blank"><?= __('Receipt') ?></a></td>
    </tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<?php if ($returns): ?>
<div class="panel">
  <h2><?= __('Goods Returns') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= count($returns) ?>)</span></h2>
  <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Return #') ?></th><th><?= __('Invoice') ?></th><th class="num"><?= __('Good (Packets)') ?></th><th class="num"><?= __('Damaged (Packets)') ?></th><th class="num"><?= __('Credit') ?></th><th><?= __('Reference / Notes') ?></th></tr></thead>
    <tbody><?php foreach ($returns as $r): ?><tr>
      <td><?= date_display($r['date']) ?></td>
      <td class="mono"><?= e($r['issue_no']) ?></td>
      <td class="mono"><?= e($r['invoice_no'] ?: '—') ?></td>
      <td class="num"><?= qty($r['good_qty']) ?></td>
      <td class="num"><?= qty($r['damaged_qty']) ?></td>
      <td class="num"><?= money($r['credit']) ?></td>
      <td><?= e($r['notes'] ?: '—') ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<script>
(function () {
  var rateMap = <?= $rateMapJson ?: '{}' ?>;
  var total = document.getElementById('totalAmount');
  var cash = document.getElementById('cashAmount');
  var bank = document.getElementById('bankAmount');
  var msg = document.getElementById('splitMsg');
  var manual = document.getElementById('manualMode');
  var panel = document.getElementById('allocPanel');
  var allocs = [].slice.call(document.querySelectorAll('.allocInput'));
  var allocMsg = document.getElementById('allocMsg');

  function money(n) { return 'Rs ' + (Math.round((parseFloat(n) || 0) * 100) / 100).toFixed(2); }
  function checkSplit() {
    if (!total || !cash || !bank || !msg) return 0;
    var t = parseFloat(total.value || '0'), c = parseFloat(cash.value || '0'), b = parseFloat(bank.value || '0');
    var diff = Math.round((t - c - b) * 100) / 100;
    if (Math.abs(diff) > 0.009) {
      msg.textContent = diff > 0 ? ('Remaining to account for: ' + money(diff)) : ('Over by ' + money(-diff));
      msg.style.color = '#8f1d13';
    } else {
      msg.textContent = '✓ Cash + Bank = Total';
      msg.style.color = '#1d6530';
    }
    return t;
  }
  function checkAlloc() {
    if (!allocMsg) return;
    var sum = 0, bad = false;
    allocs.forEach(function (el) {
      var v = parseFloat(el.value || '0'), max = parseFloat(el.getAttribute('max') || '0');
      if (v < 0 || v > max + 0.009) bad = true;
      sum += v;
    });
    sum = Math.round(sum * 100) / 100;
    var t = checkSplit();
    var left = Math.round((t - sum) * 100) / 100;
    if (bad) { allocMsg.textContent = 'Allocation exceeds an invoice balance.'; allocMsg.style.color = '#8f1d13'; return; }
    if (left > 0.009) { allocMsg.textContent = 'Allocated ' + money(sum) + ' — ' + money(left) + ' will be applied oldest-first automatically.'; allocMsg.style.color = '#1d6530'; }
    else if (left < -0.009) { allocMsg.textContent = 'Allocated ' + money(sum) + ' — over by ' + money(-left) + '.'; allocMsg.style.color = '#8f1d13'; }
    else { allocMsg.textContent = '✓ Fully allocated: ' + money(sum); allocMsg.style.color = '#1d6530'; }
  }
  [total, cash, bank].forEach(function (el) { if (el) el.addEventListener('input', function () { if (panel && panel.style.display !== 'none') checkAlloc(); else checkSplit(); }); });
  if (manual && panel) {
    manual.addEventListener('change', function () {
      panel.style.display = manual.checked ? 'block' : 'none';
      if (manual.checked) checkAlloc(); else checkSplit();
    });
  }
  allocs.forEach(function (el) { el.addEventListener('input', checkAlloc); });
  checkSplit();

  var rInvoice = document.getElementById('retInvoice');
  var rProduct = document.getElementById('retProduct');
  var rQty = document.getElementById('retQty');
  var rRate = document.getElementById('retRate');
  var rCredit = document.getElementById('retCredit');
  var rCond = document.getElementById('retCondition');
  var rMsg = document.getElementById('retMsg');
  function updateReturn() {
    if (!rProduct || !rRate || !rCredit) return;
    var pid = rProduct.value ? parseInt(rProduct.value, 10) : 0;
    var iid = rInvoice && rInvoice.value ? parseInt(rInvoice.value, 10) : 0;
    var rate = 0;
    if (iid && rateMap[iid]) {
      if (rateMap[iid][pid] === undefined) {
        rate = 0;
        if (rMsg) { rMsg.textContent = 'This product is not on the selected invoice. Choose another invoice or leave it unselected.'; rMsg.style.color = '#8f1d13'; }
      } else { rate = parseFloat(rateMap[iid][pid]) || 0; }
    } else {
      var opt = rProduct.selectedOptions[0];
      rate = opt ? (parseFloat(opt.getAttribute('data-rate')) || 0) : 0;
    }
    if (rMsg && (!iid || (rateMap[iid] && rateMap[iid][pid] !== undefined))) { rMsg.textContent = ''; }
    rRate.value = rate.toFixed(2);
    var credit = (rCond && rCond.value === 'damaged') ? 0 : Math.round((parseFloat(rQty ? rQty.value : '0') || 0) * rate * 100) / 100;
    rCredit.value = credit.toFixed(2);
  }
  [rInvoice, rProduct, rQty, rCond].forEach(function (el) { if (el) el.addEventListener('change', updateReturn); if (el) el.addEventListener('input', updateReturn); });
  updateReturn();
})();
</script>
