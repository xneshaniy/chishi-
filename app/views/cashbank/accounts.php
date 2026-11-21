<?php $title = __('Cash & Bank Accounts'); ?>
<div class="toolbar"><span class="muted"><?= __('Book balance = opening + all posted GL movements on the linked account.') ?></span><span style="flex:1"></span><a class="btn btn-primary" href="<?= e(url('cashbank/accounts/new')) ?>"><?= __('+ New Account') ?></a></div>
<div class="panel"><div class="table-wrap"><table class="tbl"><thead><tr><th><?= __('Name') ?></th><th><?= __('Type') ?></th><th><?= __('Bank / Account No') ?></th><th class="num"><?= __('Opening') ?></th><th class="num"><?= __('GL Movement') ?></th><th class="num"><?= __('Book Balance') ?></th><th><?= __('Status') ?></th></tr></thead>
<tbody><?php foreach ($rows as $a): ?><tr><td><?= e($a['name']) ?></td><td><span class="badge <?= $a['type'] === 'cash' ? 'green' : 'blue' ?>"><?= e(strtoupper($a['type'])) ?></span></td>
<td><?= e(trim(($a['bank_name'] ?? '') . ' ' . ($a['account_no'] ?? '')) ?: '—') ?></td>
<td class="num"><?= money($a['opening_balance'], false) ?></td><td class="num"><?= money($a['gl_net'], false) ?></td><td class="num"><strong><?= money($a['balance']) ?></strong></td>
<td><span class="badge <?= $a['is_active'] ? 'green' : 'gray' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
