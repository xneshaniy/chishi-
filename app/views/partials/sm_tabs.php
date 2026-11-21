<?php
/**
 * Salesman profile tab bar — Account (3 sections), Biodata, Salary, Goods Issue, Bonus, Goods Return,
 * Expenses, Recovery / Payment Collection, Collections, Claims, Daily Settlement, Complete Overview.
 * Expects $sm (salesman row) and $tab (one of: account, biodata, salary, issue, bonus, return, expense, recovery, collections, claims, daily, overview).
 */
$tab = $tab ?? 'biodata';
$id  = (int)($sm['id'] ?? 0);
$items = [
    'account' => ['label' => __('Account'),              'route' => 'salesmen/' . $id . '/account'],
    'biodata' => ['label' => __('Biodata'),              'route' => 'salesmen/' . $id . '/profile'],
    'salary'  => ['label' => __('Salary'),               'route' => 'salesmen/' . $id . '/salary'],
    'advances'=> ['label' => __('Advances'),             'route' => 'salesmen/' . $id . '/advances'],
    'issue'   => ['label' => __('Goods Issued / Stock Received'), 'route' => 'salesmen/' . $id . '/issue'],
    'bonus'   => ['label' => __('Bonus'),                'route' => 'salesmen/' . $id . '/bonus'],
    'return'  => ['label' => __('Goods Return'),         'route' => 'salesmen/' . $id . '/return'],
    'expense' => ['label' => __('Expenses'),             'route' => 'salesmen/' . $id . '/expense'],
    'recovery'=> ['label' => __('Recovery / Payment Collection'),'route' => 'salesmen/' . $id . '/recovery'],
    'collections'=> ['label' => __('Collections'),    'route' => 'salesmen/' . $id . '/collections'],
    'claims'  => ['label' => __('Claims'),                'route' => 'salesmen/' . $id . '/claims'],
    'daily'   => ['label' => __('Daily Settlement'),      'route' => 'salesmen/' . $id . '/daily'],
    'overview'=> ['label' => __('Complete Overview'),     'route' => 'salesmen/' . $id . '/overview'],
];
?>
<nav class="tabs-bar" aria-label="<?= e(__('Salesman tabs')) ?>">
  <?php foreach ($items as $key => $t): ?>
    <a href="<?= e(url($t['route'])) ?>" class="<?= $tab === $key ? 'on' : '' ?>" aria-current="<?= $tab === $key ? 'page' : 'false' ?>"><?= e($t['label']) ?></a>
  <?php endforeach; ?>
</nav>
