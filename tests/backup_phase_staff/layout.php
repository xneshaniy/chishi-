<?php
/** App layout — modern sidebar + topbar. $content is the view captured via output buffering in index.php */
$currentUser = Auth::user();
$notifUnread = $currentUser ? Notifications::unreadCount((int)$currentUser['id']) : 0;
$notifPrefs = $currentUser ? Notifications::prefs((int)$currentUser['id']) : ['sound' => 0, 'desktop' => 0];

/* Inline stroke icons (24×24, currentColor) */
if (!function_exists('erp_icon')) {
    function erp_icon(string $name): string {
        static $icons = null;
        if ($icons === null) {
            $p = [
                'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
                'invoice' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
                'order' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/>',
                'return' => '<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6.7L3 13"/>',
                'salesman' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><path d="M17 11h5M19.5 8.5v5"/>',
                'customer' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M9 13h1M9 17h1M14 9h1M14 13h1M14 17h1"/>',
                'claim' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.8.3-.9 1-.9 1.7"/><circle cx="12" cy="16.5" r=".5"/>',
                'po' => '<path d="M9 5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
                'grn' => '<path d="M21 8l-9-5-9 5 9 5 9-5v8l-9 5-9-5"/><path d="M3 8l9 5 9-5"/>',
                'raw' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 16.5l9 5 9-5"/>',
                'supplier' => '<rect x="2" y="7" width="13" height="10" rx="1.5"/><path d="M15 10h4l3 3v4h-7"/><circle cx="6" cy="17" r="1.6"/><circle cx="18" cy="17" r="1.6"/>',
                'product' => '<path d="M21 8l-9-5-9 5 9 5 9-5v8l-9 5-9-5"/><path d="M12 12l9-5M12 12v9M12 12L3 7"/>',
                'stock' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5M16 4v5"/>',
                'ledger' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h13"/>',
                'adjust' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h13"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="19" cy="18" r="2"/>',
                'transfer' => '<path d="M4 7h13M13 3l4 4-4 4"/><path d="M20 17H7M11 13l-4 4 4 4"/>',
                'opening' => '<path d="M12 3v12M8 11l4 4 4-4"/><path d="M4 21h16"/>',
                'count' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
                'production' => '<path d="M3 21V8l6 4V8l6 4V4h6v17z"/><path d="M7 17h.01M12 17h.01M17 17h.01"/>',
                'bom' => '<path d="M4 6h7M4 12h7M4 18h7"/><path d="M15 6l4 3-4 3M15 12l4 3-4 3M15 18l4 3-4 3"/>',
                'bank' => '<rect x="2" y="8" width="20" height="11" rx="2"/><circle cx="16" cy="13.5" r="2.5"/><path d="M5 8l2.2-4h5.6L15 8"/>',
                'receipt' => '<path d="M12 3v14M7 12l5 5 5-5"/><path d="M4 21h16"/>',
                'payment' => '<path d="M12 21V8M7 13l5-5 5 5"/><path d="M4 4h16"/>',
                'swap' => '<path d="M7 4L3 8l4 4M3 8h13"/><path d="M17 12l4 4-4 4M21 16H8"/>',
                'cheque' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 11h4M6 14h3M13 14l2-2 2 2"/>',
                'reconcile' => '<path d="M9 12l2 2 4-5"/><circle cx="12" cy="12" r="9"/>',
                'accounts' => '<path d="M2 9l10-6 10 6-10 6z"/><path d="M5 11v5c0 1.5 3 3 7 3s7-1.5 7-3v-5"/>',
                'journal' => '<path d="M12 20h9M16 4l4 4L8 20l-5 1 1-5z"/>',
                'pnl' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
                'balance' => '<path d="M12 3v18M8 21h8M12 6l7 3M12 6L5 9"/><path d="M3 13a4 4 0 0 0 4 2 4 4 0 0 0 4-2l-4-6zM13 13a4 4 0 0 0 4 2 4 4 0 0 0 4-2l-4-6z"/>',
                'cashflow' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
                'closing' => '<rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
                'expense' => '<path d="M12 3v18M8 7h6a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h7"/>',
                'tag' => '<path d="M20.6 13.4L12 22 2 12V2h10l8.6 8.6z"/><circle cx="7" cy="7" r="1.2"/>',
                'wrench' => '<path d="M14.7 6.3a5 5 0 0 0-6.9 6.9L2 20l2 2 6.8-5.8a5 5 0 0 0 6.9-6.9L13.5 11l-2.5-2.5z"/>',
                'toolbox' => '<rect x="2" y="8" width="20" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>',
                'employee' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M7 17c1.2-1.5 3-2.3 5-2.3s3.8.8 5 2.3"/>',
                'attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M9 15l2 2 4-4"/>',
                'payroll' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h3.5a1.75 1.75 0 0 1 0 3.5h-2a1.75 1.75 0 0 0 0 3.5H15"/>',
                'vehicle' => '<path d="M3 16V8l2-4h9l5 6v6"/><path d="M3 16h18v3h-2l-1.5-2H7L5.5 19H3z"/><circle cx="7.5" cy="16" r="1.5"/><circle cx="16.5" cy="16" r="1.5"/>',
                'reports' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
                'company' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h4a1 1 0 0 1 1 1v11"/><path d="M2 21h20"/>',
                'branch' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8.5 6h7M7 8.3L10.5 16M17 8.3L13.5 16"/>',
                'warehouse' => '<path d="M3 21V9l9-6 9 6v12"/><path d="M3 21h18M9 21v-6h6v6"/>',
                'fiscal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
                'bonus' => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8a3 3 0 1 0-3-3"/>',
                'users' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>',
                'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6z"/><path d="M9.5 12l2 2 3.5-4"/>',
                'backup' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v5h10V4"/><circle cx="12" cy="14.5" r="3"/>',
            ];
            $icons = $p;
        }
        $body = $icons[$name] ?? $icons['dashboard'];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="19" height="19" aria-hidden="true">' . $body . '</svg>';
    }
}

$nav = [
    '__section:_overview' => ['Dashboard', 'dashboard'],
    'dashboard' => ['', __('dashboard'), 'dashboard'],
    '__section:sales' => [__('sales') . ' & ' . __('distribution'), 'sales'],
    'sales/invoices' => ['sales', __('Sales Invoices'), 'invoice'],
    'sales/orders' => ['sales', __('Sales Orders'), 'order'],
    'sales/returns' => ['sales', __('Sales Returns'), 'return'],
    'salesmen' => ['salesmen', __('salesmen'), 'salesman'],
    'customers' => ['customers', __('customers'), 'customer'],
    'claims' => ['claims', __('Claims'), 'claim'],
    '__section:purchases' => [__('purchases'), 'po'],
    'purchase/orders' => ['purchase', __('Purchase Orders'), 'po'],
    'purchase/invoices' => ['purchase', __('Purchase Invoices / GRN'), 'grn'],
    'purchase/raw' => ['purchase', __('Raw Material Purchases'), 'raw'],
    'suppliers' => ['suppliers', __('suppliers'), 'supplier'],
    '__section:inventory' => [__('inventory'), 'stock'],
    'products' => ['products', __('Products'), 'product'],
    'inventory/raw' => ['inventory.view', __('Raw Material Stock'), 'raw'],
    'inventory/finished' => ['inventory.view', __('Finished Product Stock'), 'stock'],
    'stock/movements' => ['inventory', __('Stock Ledger'), 'ledger'],
    'stock/adjustments' => ['inventory', __('Stock Adjustments'), 'adjust'],
    'stock/transfers' => ['inventory', __('Stock Transfers'), 'transfer'],
    'stock/opening' => ['inventory', __('Opening Stock'), 'opening'],
    'stock/counts' => ['inventory', __('Physical Stock Count'), 'count'],
    'stock/consumption' => ['inventory.view', __('Factory Consumption'), 'production'],
    '__section:production' => [__('production'), 'production'],
    'production/orders' => ['production', __('Production Orders'), 'production'],
    'production/boms' => ['production', __('Bills of Materials'), 'bom'],
    '__section:finance' => [__('cash_bank') . ' & ' . __('accounting'), 'bank'],
    'cashbank/accounts' => ['cashbank', __('Cash & Bank Accounts'), 'bank'],
    'cashbank/receipts' => ['cashbank', __('Receipts (Customer)'), 'receipt'],
    'cashbank/payments' => ['cashbank', __('Payments (Supplier/Owner)'), 'payment'],
    'cashbank/transfers' => ['cashbank', __('Cash ↔ Bank Transfers'), 'swap'],
    'cashbank/cheques' => ['cashbank', __('Cheque Register'), 'cheque'],
    'cashbank/reconcile' => ['bank.reconcile', __('Bank Reconciliation'), 'reconcile'],
    'accounting/accounts' => ['accounts.view', __('Chart of Accounts'), 'accounts'],
    'accounting/journal' => ['journal.view', __('Journal Entries'), 'journal'],
    'accounting/ledger' => ['journal.view', __('General Ledger'), 'ledger'],
    'accounting/trial-balance' => ['accounts.view', __('Trial Balance'), 'balance'],
    'accounting/pnl' => ['accounts.view', __('Profit & Loss'), 'pnl'],
    'accounting/balance-sheet' => ['accounts.view', __('Balance Sheet'), 'ledger'],
    'accounting/cash-flow' => ['accounts.view', __('Cash Flow'), 'cashflow'],
    'accounting/closing' => ['fiscal.close', __('Fiscal Year Closing'), 'closing'],
    '__section:ops' => [__('expenses') . ' · ' . __('payroll') . ' · ' . __('vehicles'), 'expense'],
    'expenses' => ['expenses.view', __('Expenses'), 'expense'],
    'expenses/categories' => ['expenses.view', __('Expense Categories'), 'tag'],
    'maintenance' => ['maintenance.view', __('Factory Maintenance & Repairs'), 'wrench'],
    'maintenance/assets' => ['maintenance.view', __('Maintenance Assets'), 'toolbox'],
    'employees' => ['employees.view', __('Employees'), 'employee'],
    'attendance' => ['attendance.mark', __('Attendance'), 'attendance'],
    'payroll' => ['payroll.generate', __('Payroll Run'), 'payroll'],
    'vehicles' => ['vehicles.view', __('Vehicles'), 'vehicle'],
    '__section:more' => [__('reports') . ' & ' . __('settings'), 'reports'],
    'reports' => ['reports.view', __('Reports Hub'), 'reports'],
    'reports/simple' => ['reports.view', __('Simple Reports'), 'reports'],
    'reports/production' => ['reports.view', __('Production Report'), 'production'],
    'reports/raw-daily' => ['reports.view', __('Raw Material Daily'), 'raw'],
    'reports/fg-daily' => ['reports.view', __('Finished Product Daily'), 'stock'],
    'settings/company' => ['settings.view', __('Company Profile & Logo'), 'company'],
    'settings/branches' => ['settings.branches', __('Branches / Factories'), 'branch'],
    'settings/warehouses' => ['settings.warehouses', __('Warehouses'), 'warehouse'],
    'settings/fiscal' => ['settings.fiscal', __('Financial Year & Periods'), 'fiscal'],
    'settings/opening' => ['settings.fiscal', __('Opening Balances'), 'opening'],
    'settings/units' => ['settings.view', __('Units & Packaging'), 'swap'],
    'settings/bonus-schemes' => ['bonus.manage', __('Bonus Schemes & Rules'), 'bonus'],
    'users' => ['users.view', __('users'), 'users'],
    'audit' => ['audit.view', __('Audit Log'), 'shield'],
    'settings/backup' => ['backup.run', __('Backup & Restore'), 'backup'],
];
$active = $_GET['r'] ?? 'dashboard';
$navSection = null;
foreach ($nav as $r => $item) {
    if (str_starts_with($r, '__section:')) { $navSection = $item[0]; continue; }
    if ($active === $r || str_starts_with($active, $r . '/')) break;
}
$cssVersion = @filemtime(dirname(__DIR__) . '/assets/css/app.css') ?: time();
?><!DOCTYPE html>
<html lang="<?= e(is_rtl() ? 'ur' : (current_lang() === 'roman' ? 'ur-Latn' : 'en')) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0e5a34">
<title><?= e(isset($title) ? __($title) : cfg('app.name')) ?> - <?= e(cfg('app.company')) ?></title>
<link rel="icon" href="<?= asset('assets/img/ctc-logo.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>?v=<?= $cssVersion ?>">
<script>window.ERP_NOTIF = { endpoint: <?= json_encode(url('notifications/unread')) ?>, mark: <?= json_encode(url('notifications/mark')) ?>, csrf: <?= json_encode(csrf_token()) ?>, markAll: <?= json_encode(__('Mark all read')) ?>, empty: <?= json_encode(__('No notifications yet.')) ?> };</script>
<script src="<?= asset('assets/js/erp.js') ?>"></script>
</head>
<body>
<div class="app">
  <aside class="sidebar no-print" id="sidebar" aria-label="<?= __('Main navigation') ?>">
    <div class="brand">
      <img src="<?= asset('assets/img/ctc-logo.svg') ?>" alt="CTC Logo">
      <span><small><?= __('Chishti Tobacco Co.') ?></small><b><?= __('JUSTI NUMBER ONE') ?><br><?= __('ERP') ?></b></span>
    </div>
    <nav class="nav">
<?php foreach ($nav as $route => $item): ?>
    <?php if (str_starts_with($route, '__section:')): ?>
      <div class="nav-title"><?= e($item[0]) ?></div>
    <?php else: list($navPerm, $label, $icon) = $item;
      $group = explode('.', $navPerm)[0];
      if (!Auth::can($navPerm) && !Auth::can($group . '.view') && !Auth::can($navPerm . '.view')) continue;
      $isActive = $active === $route || str_starts_with($active, $route . '/'); ?>
      <a href="<?= e(url($route)) ?>" class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <span class="ic"><?= erp_icon($icon) ?></span><?= e($label) ?></a>
    <?php endif; ?>
<?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <span class="role-chip"><?= e(strtoupper(Auth::roleCodes()[0] ?? 'user')) ?></span>
      <span>JUSTI ERP · v2.0</span>
    </div>
  </aside>

  <div class="main">
    <header class="topbar no-print">
      <button class="hamburger" id="hamburger" aria-label="<?= __('Toggle menu') ?>">☰</button>
      <div>
        <div class="crumb"><?= e($navSection ?? __('dashboard')) ?></div>
        <h1 class="page-title"><?= e(isset($title) ? __($title) : __('dashboard')) ?></h1>
      </div>
      <span class="spacer"></span>
      <span class="pill-toggle" role="group" aria-label="<?= __('Language') ?>">
        <a href="<?= e(url($active, ['lang' => 'en'])) ?>" class="<?= current_lang() === 'en' ? 'on' : '' ?>" title="<?= __('English') ?>">EN</a>
        <a href="<?= e(url($active, ['lang' => 'roman'])) ?>" class="<?= current_lang() === 'roman' ? 'on' : '' ?>" title="<?= __('Roman Urdu') ?>">Roman</a>
        <a href="<?= e(url($active, ['lang' => 'ur'])) ?>" class="<?= current_lang() === 'ur' ? 'on' : '' ?>" title="اردو"><?= __('اردو') ?></a>
      </span>
      <div class="user-chip" title="<?= e($currentUser['full_name'] ?? '') ?>">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr(trim($currentUser['full_name'] ?? 'U'), 0, 2))) ?></span>
        <span><span class="u-name"><?= e($currentUser['full_name'] ?? '') ?></span><br><span class="u-role"><?= e(Auth::roleCodes()[0] ?? '') ?></span></span>
      </div>
      <div class="notif-wrap" id="notifWrap" data-sound="<?= (int)$notifPrefs['sound'] ?>" data-desktop="<?= (int)$notifPrefs['desktop'] ?>">
        <button type="button" class="notif-bell" id="notifBell" aria-label="<?= __('Notifications') ?>" aria-expanded="false" aria-controls="notifPanel">
          <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="notif-badge" id="notifBadge"<?= $notifUnread > 0 ? '' : ' hidden' ?>><?= (int)$notifUnread ?></span>
        </button>
        <div class="notif-panel" id="notifPanel" role="dialog" aria-label="<?= __('Notifications') ?>" hidden>
          <div class="notif-panel-head">
            <strong><?= e(__('Notifications')) ?></strong>
            <button type="button" class="notif-mini" id="notifMarkAll" data-action="all-read"><?= e(__('Mark all read')) ?></button>
          </div>
          <div class="notif-list" id="notifList"><div class="notif-empty"><?= e(__('Loading…')) ?></div></div>
          <a class="notif-panel-foot" href="<?= e(url('notifications')) ?>"><?= e(__('View all notifications')) ?> →</a>
        </div>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= e(url('auth/logout')) ?>" title="<?= e(__('logout')) ?>"><?= e(__('logout')) ?></a>
    </header>

    <main class="content">
      <?php if (($GLOBALS['AREA'] ?? null) === 'admin'): ?><div class="alert alert-error" style="background:#fde8e8;border-color:#f5b5b5;color:#7a1c14"><strong><?= __('ADMIN CONSOLE') ?></strong><?= __(' — Owner/Admin only · every payment, bonus, discount and restricted transaction needs your approval.') ?></div><?php endif; ?>
      <?php if (($GLOBALS['AREA'] ?? null) === 'management'): ?><div class="alert alert-info"><strong><?= __('MANAGEMENT CONSOLE') ?></strong><?= __(' — Manager/Accountant access · approvals are handled by Owner/Admin in the Admin Console.') ?></div><?php endif; ?>
      <?php foreach (flash_get() as $f): ?>
        <div class="alert alert-<?= e($f['type'] === 'error' ? 'error' : $f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php require $viewFile; ?>
    </main>
  </div>
</div>
<div class="sidebar-overlay no-print" id="sidebarOverlay" aria-hidden="true" style="display:none;position:fixed;inset:0;background:rgba(10,20,13,.55);z-index:55"></div>
<script>
(function () {
  var sb = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var burger = document.getElementById('hamburger');
  function close() { if (sb) sb.classList.remove('open'); if (overlay) overlay.style.display = 'none'; }
  if (burger) burger.addEventListener('click', function () {
    var open = sb.classList.toggle('open');
    if (overlay) overlay.style.display = open ? 'block' : 'none';
  });
  if (overlay) overlay.addEventListener('click', close);
})();
</script>
</body>
</html>
