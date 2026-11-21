<?php $title = __('Collections — ') . e($sm['name']) ?>
<div class="simple-hero no-print" style="padding:16px 20px;margin-bottom:10px;display:flex;gap:16px;justify-content:space-between;align-items:center;flex-wrap:wrap">
  <div class="greet"><span style="font-size:24px;font-weight:800"><?= __('Collections — ') ?><?= e($sm['name']) ?></span><br><small><?= __('Money the salesman collected from his customers (van receipts). No GL entry until handover to company.') ?></small></div>
  <a class="btn btn-outline btn-sm" href="<?= e(url('salesmen/' . (int)$sm['id'] . '/settlement')) ?>">← <?= __('Hisab') ?></a>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Collected') ?></div><div class="sc-val"><?= money((float)($totals['total_collected'] ?? 0)) ?></div><div class="sc-sub"><?= __('all-time, all customers') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Cash Collected') ?></div><div class="sc-val"><?= money((float)($totals['cash_total'] ?? 0)) ?></div><div class="sc-sub"><?= __('from all Van collections') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Bank Collected') ?></div><div class="sc-val"><?= money((float)($totals['bank_total'] ?? 0)) ?></div><div class="sc-sub"><?= __('directly deposited / transferred') ?></div></div>
</div>

<div class="panel" style="margin-bottom:12px">
  <h2><?= __('Record Customer Collection') ?></h2>
  <p class="help"><?= __('Use this for money the salesman received from any customer. Amount still held by him shows in the Hisab tab until he hands it over.') ?></p>
  <form method="post" action="<?= e(url('salesmen/' . (int)$sm['id'] . '/collections')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="idem_key" value="<?= e($idemKey) ?>">
    <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>" required></div>
    <div class="span-2"><label class="f"><?= __('Customer') ?></label><select class="f" name="customer_id"><option value=""><?= __('— not registered / walk-in —') ?></option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?></select><span class="muted" style="font-size:11px"><?= __('Optional — pick the shop, or enter who paid below.') ?></span></div>
    <div class="span-2"><label class="f"><?= __('Paid By (person / shop name)') ?></label><input class="f" name="contact_name" placeholder="<?= e(__('e.g. Ahmed Traders / Counter sale')) ?>"><span class="muted" style="font-size:11px"><?= __('Required when no customer is selected.') ?></span></div>
    <div><label class="f"><?= __('Total Amount (Rs) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" id="scolTotal" required></div>
    <div><label class="f"><?= __('Cash Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="cash_amount" id="scolCash" value="0.00"></div>
    <div><label class="f"><?= __('Bank Received (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="bank_amount" id="scolBank" value="0.00"></div>
    <div><label class="f"><?= __('Bank Account') ?></label><select class="f" name="account_id"><option value=""><?= __('— cash only / not deposited —') ?></option><?php foreach ($bankAccounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div class="span-2"><label class="f"><?= __('Receipt / Reference No.') ?></label><input class="f" name="reference" placeholder="<?= e(__('e.g. CHQ-000123 / TRX-987654321')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><input class="f" name="notes" placeholder="<?= e(__('e.g. weekly van collection')) ?>"></div>
    <div class="span-2"><div id="scolMsg" class="help" role="status" style="font-weight:700"></div></div>
    <div class="span-2"><button class="btn btn-primary btn-xl" type="submit"><?= __('Record Collection') ?></button></div>
  </form>
</div>

<div class="panel">
  <h2><?= __('Collection History') ?></h2>
  <?php if (!$rows): ?><p class="muted"><?= __('No collections recorded yet. Add one above.') ?></p><?php else: ?>
    <div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Customer') ?></th><th class="num"><?= __('Total') ?></th><th class="num"><?= __('Cash') ?></th><th class="num"><?= __('Bank') ?></th><th><?= __('Reference') ?></th><th><?= __('Notes') ?></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?><tr>
        <td><?= date_display($r['date']) ?></td>
        <td><div><strong><?= e($r['customer_name'] ?: '—') ?></strong><?php if ($r['customer_code']): ?><br><span class="muted"><?= e($r['customer_code']) ?></span><?php endif; ?></div></td>
        <td class="num"><?= money($r['amount']) ?></td>
        <td class="num"><?= money($r['cash_amount']) ?></td>
        <td class="num"><?= money($r['bank_amount']) ?></td>
        <td class="mono"><?= e($r['reference'] ?? '—') ?></td>
        <td><?= e($r['notes'] ?: '—') ?></td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var t = document.getElementById('scolTotal'), c = document.getElementById('scolCash'), b = document.getElementById('scolBank'), msg = document.getElementById('scolMsg');
  function check() {
    var total = parseFloat(t.value || '0'), cash = parseFloat(c.value || '0'), bank = parseFloat(b.value || '0');
    var diff = Math.round((total - cash - bank) * 100) / 100;
    if (isNaN(total) || total <= 0) { msg.textContent = ''; msg.style.color = ''; return; }
    if (Math.abs(diff) > 0.009) { msg.textContent = diff > 0 ? ('Remaining to account for: ' + diff.toFixed(2)) : ('Over by ' + (-diff).toFixed(2)); msg.style.color = '#8f1d13'; }
    else { msg.textContent = '✓ Cash + Bank = Total'; msg.style.color = '#1d6530'; }
  }
  [t, c, b].forEach(function (el) { el.addEventListener('input', check); });
  check();
})();
</script>
