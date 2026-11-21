<?php $title = __('Dashboard'); ?>

<!-- ═══════════════ TODAY'S LIVE FACTORY SUMMARY ═══════════════ -->
<div class="card mb-4" style="border:2px solid #0e5a34;border-radius:12px;padding:16px;background:linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);box-shadow:0 4px 12px rgba(14,90,52,0.08);margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;border-bottom:1px solid #bbf7d0;padding-bottom:10px">
    <div>
      <h2 style="margin:0;font-size:18px;font-weight:800;color:#0e5a34;display:flex;align-items:center;gap:8px">
        <span>🏭</span> <?= __('Today\'s Live Factory Summary') ?>
      </h2>
      <span class="muted" style="font-size:12px">📅 <?= e(date_display($today)) ?> · <?= __('Live snapshot of production, raw material consumption, sales & cashflow') ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a class="btn btn-primary btn-sm" href="<?= e(url('production/run')) ?>">+ <?= __('New Production') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('cashbank/daily-position')) ?>">💰 <?= __('Daily Cash') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('inventory/raw-usage')) ?>">📋 <?= __('RM Usage') ?></a>
    </div>
  </div>

  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin:0;gap:12px">
    <!-- RM Used Today -->
    <a href="<?= e(url('inventory/raw-usage', ['date'=>$today])) ?>" class="stat-chip" style="text-decoration:none;border-color:#38bdf8;background:#f0f9ff;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#0369a1">📦 <?= __('RM Used Today') ?></div>
      <div class="sc-val" style="color:#0369a1;font-size:20px"><?= number_format($rmUsedToday, 2) ?> <span style="font-size:12px;font-weight:400">KG</span></div>
      <div class="sc-sub"><?= __('Factory consumption') ?> →</div>
    </a>

    <!-- FG Produced Today -->
    <a href="<?= e(url('production/run')) ?>" class="stat-chip" style="text-decoration:none;border-color:#4ade80;background:#f0fdf4;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#15803d">🎯 <?= __('FG Produced Today') ?></div>
      <div class="sc-val" style="color:#15803d;font-size:20px"><?= number_format($fgProducedToday, 0) ?> <span style="font-size:12px;font-weight:400"><?= __('Packets') ?></span></div>
      <div class="sc-sub"><?= number_format($fgProducedToday / max(1, (float)UnitConverter::packetsPerBundle()), 1) ?> <?= __('Bundles Produced') ?> →</div>
    </a>

    <!-- Sales Today -->
    <a href="<?= e(url('sales/invoices')) ?>" class="stat-chip" style="text-decoration:none;border-color:#fbbf24;background:#fffbeb;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#b45309">📋 <?= __('Sales Today') ?></div>
      <div class="sc-val" style="color:#b45309;font-size:20px"><?= money($salesToday) ?></div>
      <div class="sc-sub"><?= (int)($salesCountToday ?? 0) ?> <?= __('posted invoice(s)') ?> →</div>
    </a>

    <!-- Collections Today -->
    <a href="<?= e(url('reports/collections')) ?>" class="stat-chip" style="text-decoration:none;border-color:#a7f3d0;background:#ecfdf5;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#047857">📥 <?= __('Collections Today') ?></div>
      <div class="sc-val" style="color:#047857;font-size:20px"><?= money($collectionsToday) ?></div>
      <div class="sc-sub"><?= __('Recovered from market') ?> →</div>
    </a>

    <!-- Expenses & Salaries Today -->
    <a href="<?= e(url('expenses')) ?>" class="stat-chip" style="text-decoration:none;border-color:#fca5a5;background:#fef2f2;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#b91c1c">💸 <?= __('Expenses & Salaries Today') ?></div>
      <div class="sc-val" style="color:#b91c1c;font-size:20px"><?= money($todayExpensesAndSalaries ?? 0) ?></div>
      <div class="sc-sub"><?= __('Disbursed cash & bank') ?> →</div>
    </a>

    <!-- Daily Cash Position -->
    <a href="<?= e(url('cashbank/daily-position')) ?>" class="stat-chip" style="text-decoration:none;border-color:<?= $netCashMovement >= 0 ? '#86efac' : '#fdba74' ?>;background:<?= $netCashMovement >= 0 ? '#f0fdf4' : '#fff7ed' ?>;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:<?= $netCashMovement >= 0 ? '#166534' : '#c2410c' ?>">🏦 <?= __('Net Cash Movement') ?></div>
      <div class="sc-val" style="color:<?= $netCashMovement >= 0 ? '#166534' : '#c2410c' ?>;font-size:20px"><?= ($netCashMovement >= 0 ? '+' : '') . money($netCashMovement) ?></div>
      <div class="sc-sub"><?= __('In') ?>: <?= money($todayIn) ?> | <?= __('Out') ?>: <?= money($todayOut) ?> →</div>
    </a>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg><?= __('Sales (Month)') ?></div><div class="sc-val"><?= money($salesMonth) ?></div><div class="sc-sub"><?= $invoicesMonth ?> <?= __('invoice(s)') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5v8l-9 5-9-5"/><path d="M3 8l9 5 9-5"/></svg><?= __('Purchases (Month)') ?></div><div class="sc-val"><?= money($purchasesMonth) ?></div><div class="sc-sub"><?= __('Purchase Invoices') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 15h8M8 11h8"/></svg><?= __('Cash Balance') ?></div><div class="sc-val"><?= money($cashTotal) ?></div><div class="sc-sub"><?= count(array_filter($cashBank, fn($a) => $a['type'] === 'cash')) ?> <?= __('cash account(s)') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 10V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3M5 10v11M19 10v11M9 10h6"/></svg><?= __('Bank Balance') ?></div><div class="sc-val"><?= money($bankTotal) ?></div><div class="sc-sub"><?= money($cashTotal) ?> <?= __('cash') ?> + <?= money($bankTotal) ?> <?= __('bank') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h20M8 6l-6 6 6 6M10 12h14"/></svg><?= __('Cash In Today') ?></div><div class="sc-val" style="color:#1d6530"><?= money($todayIn) ?></div><div class="sc-sub"><?= __('Money received today') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12H2M16 6l6 6-6 6M10 12h14"/></svg><?= __('Cash Out Today') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($todayOut) ?></div><div class="sc-sub"><?= __('Money paid today') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg><?= __('Net Cash Movement') ?></div><div class="sc-val" style="color:<?= $netCashMovement >= 0 ? '#1d6530' : '#8f1d13' ?>"><?= $netCashMovement >= 0 ? '+' : '' ?><?= money(abs($netCashMovement)) ?></div><div class="sc-sub"><?= $netCashMovement >= 0 ? __('Net inflow today') : __('Net outflow today') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5v8l-9 5-9-5"/></svg><?= __('Stock Value') ?></div><div class="sc-val"><?= money($stockValue) ?></div><div class="sc-sub"><a href="<?= e(url('reports/company-stock')) ?>" style="text-decoration:none;color:inherit"><?= __('Company stock breakdown →') ?></a></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= __('Salesman Payable') ?></div><div class="sc-val"><?= money($salesmanOutstanding) ?></div><div class="sc-sub"><?= __('Goods payable by salesmen') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M8 7h6a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h7"/></svg><?= __('Expenses (Month)') ?></div><div class="sc-val"><?= money($expensesMonth) ?></div><div class="sc-sub"><?= __('Posted expenses') ?></div></div>
  <?php if (isset($pendingApprovals) && (is_owner_or_admin() || in_array('manager', Auth::roleCodes(), true))): ?><a class="stat-chip<?= $pendingApprovals > 0 ? ' warn' : '' ?>" href="<?= e(url(is_owner_or_admin() ? 'admin/approvals' : 'management/approvals')) ?>" style="text-decoration:none"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 12l2 2 4-4"/></svg><?= __('Pending Payments') ?></div><div class="sc-val"><?= (int)$pendingApprovals ?></div><div class="sc-sub"><?= $pendingApprovals > 0 ? __('Waiting Owner/Admin') : __('All caught up') ?></div></a><?php endif; ?>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg><?= __('Production') ?></div><div class="sc-val"><?= qty($producedMonth) ?></div><div class="sc-sub"><?= __('Packets this month') ?></div></div>
</div>

<div class="panel" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <h2 style="margin:0"><?= __('Cash & Bank Accounts') ?></h2>
    <a class="btn btn-outline btn-sm" href="<?= e(url('cashbank/accounts')) ?>"><?= __('Manage accounts →') ?></a>
  </div>
  <div class="table-wrap" style="margin-top:8px"><table class="tbl"><thead><tr><th><?= __('Account') ?></th><th><?= __('Type') ?></th><th class="num"><?= __('Book Balance') ?></th></tr></thead><tbody><?php foreach ($cashBank as $a): ?><tr><td><?= e($a['name']) ?></td><td><span class="badge <?= $a['type'] === 'cash' ? 'green' : 'blue' ?>"><?= strtoupper(e($a['type'])) ?></span></td><td class="num"><strong><?= money($a['balance']) ?></strong></td></tr><?php endforeach; ?><?php if (!count($cashBank)): ?><tr><td colspan="3" class="muted"><?= __('No cash/bank accounts yet.') ?></td></tr><?php endif; ?></tbody></table></div>
</div>

<div class="section-head" style="margin-top:20px"><h3><span class="tick"></span><?= __('Salesmen') ?></h3><a class="btn btn-outline btn-sm" style="margin:0" href="<?= e(url('salesmen')) ?>"><?= __('Open Salesmen →') ?></a></div>
<div class="stat-strip">
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg><?= __('Active Salesmen') ?></div><div class="sc-val"><?= (int)$smCount ?></div><div class="sc-sub"><?= __('registered; click card to open') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5v8l-9 5-9-5"/></svg><?= __('Stock With Salesmen') ?></div><div class="sc-val"><?= money($smStockValue) ?></div><div class="sc-sub"><?= __('value of goods on the vans') ?></div></div>
  <?php if ($salesmen): $top = $salesmen[0]; ?>
    <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/></svg><?= __('Top Seller (Month)') ?></div><div class="sc-val" style="font-size:16px"><?= e($top['name']) ?></div><div class="sc-sub"><?= money($top['sales']) ?> · <?= (int)$top['invoices'] ?> <?= __('invoices') ?></div></div>
  <?php else: ?>
    <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/></svg><?= __('Top Seller (Month)') ?></div><div class="sc-val" style="font-size:16px">—</div><div class="sc-sub"><?= __('no sales this month yet') ?></div></div>
  <?php endif; ?>
  <div class="stat-chip"><div class="sc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/></svg><?= __('Sales (Month)') ?></div><div class="sc-val"><?= money($salesMonth) ?></div><div class="sc-sub"><?= __('from customers') ?></div></div>
</div>

<div class="section-head" style="margin-top:20px"><h3><span class="tick"></span><?= __('Needs your attention') ?></h3></div>
<div class="attn-panel">
  <div class="attn-item<?= $rmLow + $fgLow > 0 ? ' red' : '' ?>">
    <span class="attn-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
    <span><span class="attn-t"><?= __('Low Stock') ?></span><br><span class="attn-s"><?= __('Items at/below reorder level:') ?><?= (int)($rmLow + $fgLow) ?></span><br><a class="btn btn-outline btn-sm" style="margin-top:8px" href="<?= e(url('reports/hub')) ?>"><?= __('View') ?></a></span>
  </div>
  <div class="attn-item">
    <span class="attn-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
    <span><span class="attn-t"><?= __('Overdue Payments') ?></span><br><span class="attn-s"><?= count($overdue) . ' ' . __('invoice(s) past due') ?></span><br><a class="btn btn-outline btn-sm" style="margin-top:8px" href="<?= e(url('customers')) ?>"><?= __('Chase customers') ?></a></span>
  </div>
  <?php $canSeeApprovals = is_owner_or_admin() || in_array('manager', Auth::roleCodes(), true); $apprRoute = is_owner_or_admin() ? 'admin/approvals' : 'management/approvals'; ?>
  <?php if ($canSeeApprovals): ?>
  <div class="attn-item">
    <span class="attn-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 12l2 2 4-4"/></svg></span>
    <span><span class="attn-t"><?= __('Pending Approvals') ?></span><br><span class="attn-s"><?= isset($pendingApprovals) ? (int)$pendingApprovals . ' ' . __('document(s) waiting') : __('Waiting documents') ?></span><br><a class="btn btn-outline btn-sm" style="margin-top:8px" href="<?= e(url($apprRoute)) ?>"><?= __('Review') ?></a></span>
  </div>
  <?php endif; ?>
</div>

<div class="hero-card dark" style="margin-top:18px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;padding:16px 18px">
  <div style="min-width:140px"><div class="h-label"><?= __('This Month') ?></div></div>
  <div style="flex:1;display:flex;gap:26px;flex-wrap:wrap">
    <div><div class="h-label" style="font-size:10px"><?= __('Sales') ?></div><div class="h-value" style="font-size:18px"><?= money($salesMonth) ?></div></div>
    <div><div class="h-label" style="font-size:10px"><?= __('Profit') ?></div><div class="h-value" style="font-size:18px"><?= money($pl['net_profit']) ?></div></div>
    <div><div class="h-label" style="font-size:10px"><?= __('Produced') ?></div><div class="h-value" style="font-size:18px"><?= qty($producedMonth) ?></div></div>
    <div><div class="h-label" style="font-size:10px"><?= __('Expenses') ?></div><div class="h-value" style="font-size:18px"><?= money($expensesMonth) ?></div></div>
  </div>
</div>
