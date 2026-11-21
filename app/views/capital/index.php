<?php $title = __('Capital & Drawings'); ?>
<div class="stat-strip" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-chip"><div class="sc-lbl"><?= __('Owner Capital (GL)') ?></div><div class="sc-val"><?= money($capNet) ?></div><div class="sc-sub"><?= __('Current balance sheet equity') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Invested') ?></div><div class="sc-val" style="color:var(--green)"><?= money($invested) ?></div><div class="sc-sub"><?= __('Owner put into business') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Total Withdrawn') ?></div><div class="sc-val" style="color:var(--red)"><?= money($withdrawn) ?></div><div class="sc-sub"><?= __('Owner took out (drawings)') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Net Change') ?></div><div class="sc-val"><?= money(max(0.0, $invested - $withdrawn)) ?></div><div class="sc-sub"><?= __('Invested − withdrawn') ?></div></div>
</div>

<div class="tile-grid" style="grid-template-columns:repeat(2,1fr);margin-top:4px">
  <a class="tile" href="<?= e(url('capital/invest')) ?>"><span class="tile-ic" style="background:#e7f6ec"><svg viewBox="0 0 24 24" fill="none" stroke="#127a3f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v14M7 12l5 5 5-5"/><path d="M4 21h16"/></svg></span><span class="tile-lbl"><?= __('Owner Investment') ?></span><span class="tile-sub"><?= __('Owner money into business') ?></span></a>
  <a class="tile" href="<?= e(url('capital/draw')) ?>"><span class="tile-ic" style="background:#fbe4e1"><svg viewBox="0 0 24 24" fill="none" stroke="#b3261e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V8M7 13l5-5 5 5"/><path d="M4 4h16"/></svg></span><span class="tile-lbl"><?= __('Owner Drawing') ?></span><span class="tile-sub"><?= __('Owner money out (drawings)') ?></span></a>
</div>

<div class="panel" style="margin-top:16px">
  <h2><?= __('Capital Transactions') ?> <span class="muted" style="font-weight:400;font-size:12px">(<?= count($transactions) ?>)</span></h2>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th><?= __('Date') ?></th><th><?= __('Type') ?></th><th><?= __('Cash/Bank') ?></th><th class="num"><?= __('Amount') ?></th><th><?= __('Notes') ?></th><th><?= __('Recorded By') ?></th></tr></thead>
    <tbody>
      <?php foreach ($transactions as $t):
        $isInvest = $t['reference_type'] === 'capital_investment';
      ?>
      <tr>
        <td><?= date_display($t['date']) ?></td>
        <td><span class="badge <?= $isInvest ? 'green' : 'red' ?>"><?= $isInvest ? __('Investment') : __('Drawing') ?></span></td>
        <td><?= e($t['cash_account'] ?? '—') ?></td>
        <td class="num"><?= money((float)$t['amount'], false) ?></td>
        <td><?= e($t['memo']) ?></td>
        <td><?= e($t['actor'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><tr><td colspan="6" class="muted"><?= __('No capital transactions yet.') ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
