<?php $title = __('Chart of Accounts');
$colors = ['asset' => 'green', 'liability' => 'red', 'equity' => 'blue', 'income' => 'green', 'expense' => 'amber']; ?>
<div class="toolbar"><span class="muted"><?= __('System accounts (used by auto-posting) are protected from deletion. P&L and Balance Sheet derive from these codes.') ?></span><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('accounting/accounts/new')) ?>"><?= __('+ New Account') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Code') ?></th><th><?= __('Account Name') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Balance (Dr−Cr)') ?></th><th><?= __('Posting') ?></th></tr></thead>
<tbody><?php $currentType = null; foreach ($rows as $a): if ($currentType !== $a['type']): $currentType = $a['type']; ?>
<tr><th colspan="5" style="background:#eef3ef;text-transform:uppercase;letter-spacing:.08em"><?= e($a['type']) ?></th></tr>
<?php endif; ?>
<tr><td class="mono"><?= e($a['code']) ?></td><td><?= e($a['name']) ?><?= $a['system_key'] ? ' <span class="badge gray" title="System account">SYS</span>' : '' ?></td>
<td><span class="badge <?= $colors[$a['type']] ?? 'gray' ?>"><?= e($a['type']) ?></span></td>
<td class="num"><?= number_format((float)$a['net'], 2) ?></td>
<td><?= $a['allow_posting'] ? '✓' : 'header' ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
