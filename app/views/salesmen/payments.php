<?php
$title = __('Payments / Collection - ') . e($sm['name']);
$tab = 'payments';
require __DIR__ . '/../partials/sm_tabs.php';
$canRecord = Auth::can('salesmen.settle');
?>

<h2 style="font-size:16px;margin:14px 0 6px"><?= __('Outstanding Goods Payment') ?></h2>
<p class="help"><?= __('Paid goods issued at issue-rate. Free bonus goods and returns are excluded/credited separately. Salary and expenses are never mixed in here.') ?></p>
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:14px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Issued (Payable)') ?></div><div class="sc-val"><?= money($issued) ?></div><div class="sc-sub"><?= __('paid goods only') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash Received') ?></div><div class="sc-val"><?= money($cashReceived) ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank Received') ?></div><div class="sc-val"><?= money($bankReceived) ?></div><div class="sc-sub"><?= __('all-time') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Received') ?></div><div class="sc-val"><?= money($totalReceived) ?></div><div class="sc-sub"><?= __('cash + bank') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Return Credit') ?></div><div class="sc-val"><?= money($returnCredit) ?></div><div class="sc-sub"><?= __('approved good goods returns') ?></div></div>
  <div class="stat-chip" style="border-color:<?= $remaining > 0 ? '#f2b8b5' : '#cfe8d5' ?>"><div class="sc-lbl"><?= __('Remaining Goods Payable') ?></div><div class="sc-val" style="color:<?= $remaining > 0 ? '#8f1d13' : '#1d6530' ?>"><?= money(abs($remaining)) ?></div><div class="sc-sub"><?= $remaining >= 0 ? __('outstanding') : __('overpaid / in advance') ?></div></div>
</div>

<div class="two-col" style="margin-bottom:14px">
  <div class="panel">
    <h2><?= __('Receive Payment') ?></h2>
    <p class="help"><?= __('Record once on save. Cash goes to the selected cash account; bank goes to the selected bank account.') ?></p>
    <?php if ($canRecord): ?>
    <form method="post" action="<?= e(url('salesmen/' . (int)$id . '/payments')) ?>" class="form-grid" id="payForm"><?= csrf_field() ?><input type="hidden" name="idem_key" value="<?= e($idemKey) ?>">
      <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
      <div><label class="f"><?= __('Salesman') ?></label><input class="f" value="<?= e($sm['code'] . ' — ' . $sm['name']) ?>" disabled></div>
      <div class="span-2"><label class="f"><?= __('Total Payment Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="total_amount" id="totalAmount" required></div>
      <div><label class="f"><?= __('Cash Amount Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="cash_amount" id="cashAmount" value="0.00"></div>
      <div><label class="f"><?= __('Bank Amount Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="bank_amount" id="bankAmount" value="0.00"></div>
      <div><label class="f"><?= __('Cash Account') ?></label><select class="f" name="cash_account_id" id="cashAccount"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="f"><?= __('Bank Account') ?></label><select class="f" name="bank_account_id" id="bankAccount"><option value=""><?= __('— not used —') ?></option><?php foreach ($bankAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div class="span-2"><label class="f"><?= __('Bank Reference / Transaction No.') ?> <span id="bankRefHint" class="help"><?= __('(required when bank amount > 0)') ?></span></label><input class="f" name="bank_reference" placeholder="<?= e(__('e.g. CHQ-000123 / TRX-987654321')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Notes / Remarks') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. weekly collection from van')) ?>"></div>
      <div class="span-2"><label class="f"><?= __('Received By') ?></label><input class="f" value="<?= e($currentUserName) ?>" disabled></div>
      <div class="span-2"><div id="splitMsg" class="help" role="status" style="font-weight:700"></div></div>
      <div class="span-2"><button class="btn btn-primary btn-xl" type="submit" id="paySubmit"><?= __('Receive Payment') ?></button></div>
    </form>
    <?php else: ?>
      <p class="muted"><?= __('Only authorized users (Owner / Admin / Manager) can record payments.') ?></p>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2><?= __('How It Works') ?></h2>
    <ul class="pay-notes" style="line-height:1.9;font-size:13px">
      <li><?= __('One payment can split between cash and bank — the two amounts must add up to the total.') ?></li>
      <li><?= __('Saving posts the entry immediately (money in) and credits the salesman’s goods payable.') ?></li>
      <li><?= __('A unique receipt number is generated from the journal voucher.') ?></li>
      <li><?= __('Bonus (free) goods never reduce goods payable; good-condition returns are credited automatically.') ?></li>
      <li><?= __('Salary, expenses and other adjustments are kept separate and never netted against goods payable.') ?></li>
    </ul>
    <div class="stat-strip" style="grid-template-columns:1fr;max-width:340px;margin-top:12px">
      <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Payable Balance') ?></div><div class="sc-val"><?= money($remaining) ?></div><div class="sc-sub"><?= __('from issue minus returns minus payments') ?></div></div>
    </div>
  </div>
</div>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h2 style="margin:0"><?= __('Payment History') ?> <span class="muted" style="font-size:11px;font-weight:400">(<?= count($payments) ?>)</span></h2>
    <?php if ($payments): ?><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/statement')) ?>" target="_blank"><?= __('Printable Statement') ?></a><?php endif; ?>
  </div>
  <?php if (!$payments): ?><p class="muted" style="margin-top:10px"><?= __('No payments recorded yet.') ?></p><?php else: ?>
    <div class="table-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Receipt #') ?></th><th class="num"><?= __('Total') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th><?= __('Bank Account') ?></th><th><?= __('Received By') ?></th><th><?= __('Notes') ?></th><th><?= __('Print') ?></th></tr></thead>
      <tbody><?php foreach ($payments as $p): ?><tr>
        <td><?= date_display($p['date']) ?></td>
        <td class="mono"><?= e($p['receipt_no'] ?: '#' . $p['id']) ?></td>
        <td class="num"><?= money($p['total_amount']) ?></td>
        <td class="num"><?= money($p['cash_amount']) ?></td>
        <td class="num"><?= money($p['bank_amount']) ?></td>
        <td><?= e($p['bank_account'] ?: '—') ?><?= !empty($p['bank_reference']) ? '<br><span class="muted" style="font-size:11px">' . e($p['bank_reference']) . '</span>' : '' ?></td>
        <td><?= e($p['received_full_name'] ?: $p['received_username'] ?: '—') ?></td>
        <td><?= e($p['notes'] ?: '—') ?></td>
        <td><a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$id . '/payments/' . (int)$p['id'] . '/print')) ?>" target="_blank"><?= __('Receipt') ?></a></td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var total = document.getElementById('totalAmount');
  var cash = document.getElementById('cashAmount');
  var bank = document.getElementById('bankAmount');
  var msg = document.getElementById('splitMsg');
  if (!total || !cash || !bank || !msg) return;
  function check() {
    var t = parseFloat(total.value || '0'), c = parseFloat(cash.value || '0'), b = parseFloat(bank.value || '0');
    var diff = Math.round((t - c - b) * 100) / 100;
    if (Math.abs(diff) > 0.009) {
      msg.textContent = diff > 0 ? ('Remaining to account for: ' + diff.toFixed(2)) : ('Over by ' + (-diff).toFixed(2));
      msg.style.color = '#8f1d13';
    } else {
      msg.textContent = '✓ Cash + Bank = Total';
      msg.style.color = '#1d6530';
    }
  }
  [total, cash, bank].forEach(function (el) { el.addEventListener('input', check); });
  check();
})();
</script>
