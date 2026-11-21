<?php $title = __('Gifts'); ?>
<div class="simple-hero" style="padding:14px 18px;margin-bottom:12px">
  <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div><span style="font-size:22px;font-weight:800"><?= __('Gifts') ?></span><br><small><?= __('Owner-given gifts — money and goods from store stock. Approvals exactly like other owner-funded expenses. Not sales, not receivables.') ?></small></div>
    <?php if (!empty($canCreate)): ?><div class="btn-group"><a class="btn btn-primary" href="<?= e(url('gifts/new-money')) ?>"><?= __('+ Money Gift') ?></a><a class="btn btn-outline" href="<?= e(url('gifts/new-goods')) ?>"><?= __('+ Goods Gift') ?></a></div><?php endif; ?>
  </div>
</div>
<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Money Gifts') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($summary['money']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Goods Gifts (Packets)') ?></div><div class="sc-val"><?= qty($summary['goods_packets']) ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Gifts') ?></div><div class="sc-val"><?= (int)$summary['count'] ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Pending Approval') ?></div><div class="sc-val"><?= (int)$summary['pending'] ?></div></div>
</div>
<div class="toolbar"><form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="r" value="gifts">
  <select class="f" name="status" onchange="this.form.submit()"><option value="all"><?= __('All statuses') ?></option><?php foreach (['pending','posted','rejected'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(strtoupper(__($s === 'pending' ? 'Pending' : ($s === 'posted' ? 'Posted' : 'Rejected')))) ?></option><?php endforeach; ?></select>
  <select class="f" name="type" onchange="this.form.submit()"><option value="all"><?= __('Money + Goods') ?></option><option value="money" <?= $type === 'money' ? 'selected' : '' ?>><?= __('Money') ?></option><option value="goods" <?= $type === 'goods' ? 'selected' : '' ?>><?= __('Goods') ?></option></select>
  <input class="f" name="q" placeholder="<?= e(__('Search recipient / gift # / reason')) ?>" value="<?= e($q) ?>">
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
</form></div>
<?php if (is_owner_or_admin()): ?>
  <?php $pending = array_filter($rows, fn($r) => $r['status'] === 'pending'); if ($pending): ?>
  <div class="alert alert-info" style="margin:10px 0"><?= __('Pending gift requests are decided in the Admin Console (/admin/approvals) — exactly once per approval.') ?></div>
<?php endif; endif; ?>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Date') ?></th><th><?= __('Gift #') ?></th><th><?= __('Type') ?></th><th><?= __('Recipient') ?></th><th><?= __('Reason') ?></th><th><?= __('Detail') ?></th><th><?= __('Status') ?></th><th></th></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr>
  <td><?= e(date_display($r['gift_date'])) ?></td>
  <td class="mono"><a href="<?= e(url('gifts/' . (int)$r['id'])) ?>"><?= e($r['gift_no']) ?></a></td>
  <td><span class="badge <?= $r['gift_type'] === 'money' ? 'amber' : 'blue' ?>"><?= e(strtoupper($r['gift_type'])) ?></span></td>
  <td><?= e($r['recipient_name']) ?><?php if ($r['recipient_type']): ?><br><span class="muted" style="font-size:11px"><?= e(strtoupper($r['recipient_type'])) ?></span><?php endif; ?></td>
  <td><?= e($r['reason'] ?? '—') ?></td>
  <td><?= $r['gift_type'] === 'money' ? '<strong>' . money($r['amount']) . '</strong>' : ('<strong>' . e(rtrim(rtrim(number_format((float)$r['qty'], 3, '.', ''), '0'), '.')) . ' ' . e(strtoupper($r['qty_unit'])) . ' · ' . qty($r['qty_pieces']) . ' Packets</strong>' . ($r['product_name'] ? '<br><span class="muted">' . e($r['product_name']) . '</span>' : '')) ?></td>
  <td><span class="badge <?= $r['status'] === 'posted' ? 'green' : ($r['status'] === 'rejected' ? 'red' : 'amber') ?>"><?= e(strtoupper($r['status'])) ?></span></td>
  <td><a class="btn btn-outline btn-sm" href="<?= e(url('gifts/' . (int)$r['id'])) ?>"><?= __('View') ?></a></td>
</tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:30px"><?= __('No gifts recorded yet.') ?></td></tr><?php endif; ?></tbody></table></div></div>
