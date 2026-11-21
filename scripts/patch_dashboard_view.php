<?php
$file = __DIR__ . '/../app/views/dashboard/index.php';
$content = file_get_contents($file);

$topWidget = <<<'PHP'
<?php $title = __('Dashboard'); ?>

<!-- ═══════════════ TODAY'S LIVE FACTORY SUMMARY ═══════════════ -->
<div class="card mb-4" style="border:2px solid #0e5a34;border-radius:12px;padding:16px;background:linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);box-shadow:0 4px 12px rgba(14,90,52,0.08);margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;border-bottom:1px solid #bbf7d0;padding-bottom:10px">
    <div>
      <h2 style="margin:0;font-size:18px;font-weight:800;color:#0e5a34;display:flex;align-items:center;gap:8px">
        <span>🏭</span> <?= __('آج کی فیکٹری سمری — ایک نظر میں (Today\'s Live Factory Summary)') ?>
      </h2>
      <span class="muted" style="font-size:12px">📅 <?= e(date_display($today)) ?> · <?= __('پروڈکشن، را مٹیریل، سیل، کیش فلو اور ملازمین کی لائیو پوزیشن') ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a class="btn btn-primary btn-sm" href="<?= e(url('production/run')) ?>">+ <?= __('نئی پروڈکشن') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('cashbank/daily-position')) ?>">💰 <?= __('آج کا کیش') ?></a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('inventory/raw-usage')) ?>">📋 <?= __('را مٹیریل یوز') ?></a>
    </div>
  </div>

  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin:0;gap:12px">
    <!-- RM Used Today -->
    <a href="<?= e(url('inventory/raw-usage', ['date'=>$today])) ?>" class="stat-chip" style="text-decoration:none;border-color:#38bdf8;background:#f0f9ff;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#0369a1">📦 <?= __('را مٹیریل یوز (آج)') ?></div>
      <div class="sc-val" style="color:#0369a1;font-size:20px"><?= number_format($rmUsedToday, 2) ?> <span style="font-size:12px;font-weight:400">KG</span></div>
      <div class="sc-sub"><?= __('ڈیلی یوز اور پروڈکشن سے') ?> →</div>
    </a>

    <!-- FG Produced Today -->
    <a href="<?= e(url('production/run')) ?>" class="stat-chip" style="text-decoration:none;border-color:#4ade80;background:#f0fdf4;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#15803d">🎯 <?= __('تیار مال پروڈکشن (آج)') ?></div>
      <div class="sc-val" style="color:#15803d;font-size:20px"><?= number_format($fgProducedToday, 0) ?> <span style="font-size:12px;font-weight:400"><?= __('Packets') ?></span></div>
      <div class="sc-sub"><?= number_format($fgProducedToday / max(1, (float)UnitConverter::packetsPerBundle()), 1) ?> <?= __('Bundles تیار ہوئے') ?> →</div>
    </a>

    <!-- Sales Today -->
    <a href="<?= e(url('sales/invoices')) ?>" class="stat-chip" style="text-decoration:none;border-color:#fbbf24;background:#fffbeb;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#b45309">📋 <?= __('آج کی سیل (Sales Today)') ?></div>
      <div class="sc-val" style="color:#b45309;font-size:20px"><?= money($salesToday) ?></div>
      <div class="sc-sub"><?= (int)($salesCountToday ?? 0) ?> <?= __('انوائسز پوسٹ ہوئیں') ?> →</div>
    </a>

    <!-- Collections Today -->
    <a href="<?= e(url('reports/collections')) ?>" class="stat-chip" style="text-decoration:none;border-color:#a7f3d0;background:#ecfdf5;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#047857">📥 <?= __('وصولیاں / کلیکشن (آج)') ?></div>
      <div class="sc-val" style="color:#047857;font-size:20px"><?= money($collectionsToday) ?></div>
      <div class="sc-sub"><?= __('سیلز مین و گاہکوں سے وصولی') ?> →</div>
    </a>

    <!-- Expenses & Salaries Today -->
    <a href="<?= e(url('expenses')) ?>" class="stat-chip" style="text-decoration:none;border-color:#fca5a5;background:#fef2f2;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:#b91c1c">💸 <?= __('اخراجات و تنخواہ (آج)') ?></div>
      <div class="sc-val" style="color:#b91c1c;font-size:20px"><?= money($todayExpensesAndSalaries ?? 0) ?></div>
      <div class="sc-sub"><?= __('کیش و بینک سے ادائیگی') ?> →</div>
    </a>

    <!-- Daily Cash Position -->
    <a href="<?= e(url('cashbank/daily-position')) ?>" class="stat-chip" style="text-decoration:none;border-color:<?= $netCashMovement >= 0 ? '#86efac' : '#fdba74' ?>;background:<?= $netCashMovement >= 0 ? '#f0fdf4' : '#fff7ed' ?>;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="sc-lbl" style="color:<?= $netCashMovement >= 0 ? '#166534' : '#c2410c' ?>">🏦 <?= __('کیش پوزیشن (آج کا نیٹ)') ?></div>
      <div class="sc-val" style="color:<?= $netCashMovement >= 0 ? '#166534' : '#c2410c' ?>;font-size:20px"><?= ($netCashMovement >= 0 ? '+' : '') . money($netCashMovement) ?></div>
      <div class="sc-sub">آیا: <?= money($todayIn) ?> | گیا: <?= money($todayOut) ?> →</div>
    </a>
  </div>
</div>
PHP;

$content = str_replace("<?php \$title = __('Dashboard'); ?>", $topWidget, $content);
file_put_contents($file, $content);
echo "Dashboard view updated with Live Factory Summary\n";
