<?php
/**
 * Unified employee profile tab bar.
 * Expects $employee (row with id) and $tab (one of: profile, salary, attendance, advances, bonus, deductions, history, ledger).
 */
$employeeId = (int)($employee['id'] ?? 0);
$tab = $tab ?? 'profile';
$items = [
    'profile' => ['label' => __('Biodata / Documents'), 'route' => 'employees/' . $employeeId],
    'salary' => ['label' => __('Salary'), 'route' => 'employees/' . $employeeId . '?tab=salary'],
    'attendance' => ['label' => __('Attendance'), 'route' => 'employees/' . $employeeId . '?tab=attendance'],
    'advances' => ['label' => __('Advances'), 'route' => 'employees/' . $employeeId . '?tab=advances'],
    'bonus' => ['label' => __('Bonus'), 'route' => 'employees/' . $employeeId . '?tab=bonus'],
    'deductions' => ['label' => __('Deductions'), 'route' => 'employees/' . $employeeId . '?tab=deductions'],
    'history' => ['label' => __('Work History'), 'route' => 'employees/' . $employeeId . '?tab=history'],
    'ledger' => ['label' => __('Complete Ledger'), 'route' => 'employees/' . $employeeId . '?tab=ledger'],
];
?>
<nav class="tabs-bar" aria-label="<?= e(__('Employee profile tabs')) ?>">
  <?php foreach ($items as $key => $t): ?>
    <a href="<?= e(url($t['route'])) ?>" class="<?= $tab === $key ? 'on' : '' ?>" aria-current="<?= $tab === $key ? 'page' : 'false' ?>"><?= e($t['label']) ?></a>
  <?php endforeach; ?>
</nav>
