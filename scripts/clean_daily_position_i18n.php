<?php
$file = __DIR__ . '/../app/views/cashbank/daily_position.php';
$content = file_get_contents($file);

$target = <<<'PHP'
<?php
$title = __('Daily Cash Position (آج کا کیش پوزیشن)');
?>
<div class="simple-hero no-print" style="padding:14px 20px 0;margin-bottom:0">
  <div class="greet">
    <span style="font-size:22px;font-weight:800">💰 <?= __('Daily Cash Position (آج کا کیش پوزیشن)') ?></span><br>
    <small><?= __('روزانہ کا کیش فلو — کتنا آیا، کتنا گیا، اور ہر کھاتے میں کتنا باقی ہے') ?></small>
  </div>
  <div class="no-print" style="display:flex;gap:8px;align-items:center">
    <a class="btn btn-outline" href="<?= e(url('cashbank/accounts')) ?>">🏦 <?= __('All Accounts') ?></a>
    <button class="btn btn-primary no-print" onclick="window.print()">🖨 <?= __('Print Sheet') ?></button>
  </div>
</div>

<!-- ═══════════ DATE TOOLBAR ═══════════ -->
<form method="get" class="toolbar no-print" style="margin-top:12px">
  <input type="hidden" name="r" value="cashbank/daily-position">
  <label class="muted"><?= __('تاریخ منتخب کریں') ?>:</label>
  <input type="date" name="date" value="<?= e($date) ?>" class="form-control" onchange="this.form.submit()">
  <button class="btn btn-primary" type="submit"><?= __('دیکھیں') ?> →</button>
  <a class="btn btn-outline" href="<?= e(url('cashbank/daily-position')) ?>"><?= __('آج') ?></a>
</form>

<!-- ═══════════ BIG 4 SUMMARY CHIPS ═══════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));margin:14px 0 18px">
  <div class="stat-chip" style="border-color:#bbf7d0;background:#f0fdf4">
    <div class="sc-lbl" style="color:#166534">📥 <?= __('کل آیا (Cash In Today)') ?></div>
    <div class="sc-val" style="color:#166534;font-size:22px">+ <?= money($totalIn) ?></div>
    <div class="sc-sub"><?= __('وصولیاں، کلیکشنز، سیلز') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#fecaca;background:#fef2f2">
    <div class="sc-lbl" style="color:#991b1b">📤 <?= __('کل گیا (Cash Out Today)') ?></div>
    <div class="sc-val" style="color:#991b1b;font-size:22px">- <?= money($totalOut) ?></div>
    <div class="sc-sub"><?= __('اخراجات، تنخواہیں، ادائیگیاں') ?></div>
  </div>
  <div class="stat-chip" style="border-color:<?= $netMovement >= 0 ? '#bbf7d0' : '#fed7aa' ?>;background:<?= $netMovement >= 0 ? '#f0fdf4' : '#fffbeb' ?>">
    <div class="sc-lbl" style="color:<?= $netMovement >= 0 ? '#166534' : '#92400e' ?>">⚖️ <?= __('نیٹ فرق (Net Movement)') ?></div>
    <div class="sc-val" style="color:<?= $netMovement >= 0 ? '#166534' : '#92400e' ?>;font-size:22px">
      <?= ($netMovement >= 0 ? '+' : '') . money($netMovement) ?>
    </div>
    <div class="sc-sub"><?= $netMovement >= 0 ? __('آج رقم میں اضافہ ہوا') : __('آج رقم میں کمی ہوئی') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#bae6fd;background:#f0f9ff">
    <div class="sc-lbl" style="color:#0369a1">🏦 <?= __('کل موجودہ رقم (Total Balance)') ?></div>
    <div class="sc-val" style="color:#0369a1;font-size:22px"><?= money($totalClosing) ?></div>
    <div class="sc-sub"><?= count($accounts) ?> <?= __('کیش و بینک اکاؤنٹس') ?></div>
  </div>
</div>

<!-- ═══════════ ACCOUNT-WISE BREAKDOWN TABLE ═══════════ -->
<div class="card mb-4" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
    <h3 style="margin:0;font-size:16px;font-weight:700">
      🏦 <?= __('کھاتہ وار کیش پوزیشن (Account-wise Breakdown)') ?>
      <span style="font-weight:400;font-size:13px;color:#64748b">(<?= e(date_display($date)) ?>)</span>
    </h3>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table" style="margin:0">
      <thead>
        <tr>
          <th><?= __('کھاتے کا نام') ?></th>
          <th><?= __('قسم') ?></th>
          <th style="text-align:right"><?= __('صبح کا بیلنس (Opening)') ?></th>
          <th style="text-align:right;color:#166534"><?= __('آج آیا (+ In)') ?></th>
          <th style="text-align:right;color:#991b1b"><?= __('آج گیا (- Out)') ?></th>
          <th style="text-align:right;font-weight:800"><?= __('شام کا بیلنس (Closing)') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc): ?>
        <tr>
          <td>
            <strong><?= e($acc['name']) ?></strong>
            <?php if (!empty($acc['account_no'])): ?>
              <span class="muted" style="font-size:11px">(<?= e($acc['account_no']) ?>)</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $acc['type'] === 'cash' ? 'green' : 'blue' ?>" style="text-transform:uppercase;font-size:11px">
              <?= e($acc['type']) ?>
            </span>
          </td>
          <td style="text-align:right"><?= money($acc['day_opening']) ?></td>
          <td style="text-align:right;color:#166534;font-weight:700">
            <?= $acc['day_in'] > 0 ? '+ ' . money($acc['day_in']) : '—' ?>
          </td>
          <td style="text-align:right;color:#991b1b;font-weight:700">
            <?= $acc['day_out'] > 0 ? '- ' . money($acc['day_out']) : '—' ?>
          </td>
          <td style="text-align:right;font-weight:800;font-size:15px;color:<?= $acc['day_closing'] >= 0 ? '#0369a1' : '#b91c1c' ?>">
            <?= money($acc['day_closing']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:800;font-size:15px">
          <td colspan="2"><?= __('کل مجموعہ (Total)') ?></td>
          <td style="text-align:right"><?= money($totalOpening) ?></td>
          <td style="text-align:right;color:#166534">+ <?= money($totalIn) ?></td>
          <td style="text-align:right;color:#991b1b">- <?= money($totalOut) ?></td>
          <td style="text-align:right;color:#0369a1;font-size:16px"><?= money($totalClosing) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ═══════════ TODAY'S TRANSACTIONS LIST ═══════════ -->
<div class="card" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
    <h3 style="margin:0;font-size:16px;font-weight:700">
      📋 <?= __('آج کے تمام لین دین (Today\'s Transactions)') ?>
      <span style="font-weight:400;font-size:13px;color:#64748b">(<?= count($transactions) ?> <?= __('ریکارڈز') ?>)</span>
    </h3>
  </div>
  <?php if (empty($transactions)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8">
      <?= __('اس تاریخ کا کوئی کیش یا بینک لین دین نہیں ملا۔') ?>
    </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table class="data-table" style="margin:0">
      <thead>
        <tr>
          <th><?= __('واؤچر #') ?></th>
          <th><?= __('کھاتہ') ?></th>
          <th><?= __('تفصیل / میمو') ?></th>
          <th style="text-align:right;color:#166534"><?= __('آیا (+ In)') ?></th>
          <th style="text-align:right;color:#991b1b"><?= __('گیا (- Out)') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td>
            <code><?= e($t['entry_no']) ?></code>
            <span class="badge gray" style="font-size:10px"><?= e($t['voucher_type']) ?></span>
          </td>
          <td>
            <strong><?= e($t['account_name']) ?></strong>
          </td>
          <td style="color:#334155">
            <?= e($t['line_memo'] ?: ($t['entry_memo'] ?: '—')) ?>
          </td>
          <td style="text-align:right;font-weight:700;color:#166534">
            <?= (float)$t['debit'] > 0 ? '+ ' . money((float)$t['debit']) : '—' ?>
          </td>
          <td style="text-align:right;font-weight:700;color:#991b1b">
            <?= (float)$t['credit'] > 0 ? '- ' . money((float)$t['credit']) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:800">
          <td colspan="3"><?= __('کل') ?></td>
          <td style="text-align:right;color:#166534">+ <?= money($totalIn) ?></td>
          <td style="text-align:right;color:#991b1b">- <?= money($totalOut) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
PHP;

$replacement = <<<'PHP'
<?php
$title = __('Daily Cash Position');
?>
<div class="simple-hero no-print" style="padding:14px 20px 0;margin-bottom:0">
  <div class="greet">
    <span style="font-size:22px;font-weight:800">💰 <?= __('Daily Cash Position') ?></span><br>
    <small><?= __('Daily cash flow — opening balance, cash received, cash disbursed, and closing balance per account') ?></small>
  </div>
  <div class="no-print" style="display:flex;gap:8px;align-items:center">
    <a class="btn btn-outline" href="<?= e(url('cashbank/accounts')) ?>">🏦 <?= __('All Accounts') ?></a>
    <button class="btn btn-primary no-print" onclick="window.print()">🖨 <?= __('Print Sheet') ?></button>
  </div>
</div>

<!-- ═══════════ DATE TOOLBAR ═══════════ -->
<form method="get" class="toolbar no-print" style="margin-top:12px">
  <input type="hidden" name="r" value="cashbank/daily-position">
  <label class="muted"><?= __('Select Date') ?>:</label>
  <input type="date" name="date" value="<?= e($date) ?>" class="form-control" onchange="this.form.submit()">
  <button class="btn btn-primary" type="submit"><?= __('View') ?> →</button>
  <a class="btn btn-outline" href="<?= e(url('cashbank/daily-position')) ?>"><?= __('Today') ?></a>
</form>

<!-- ═══════════ BIG 4 SUMMARY CHIPS ═══════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));margin:14px 0 18px">
  <div class="stat-chip" style="border-color:#bbf7d0;background:#f0fdf4">
    <div class="sc-lbl" style="color:#166534">📥 <?= __('Cash In Today') ?></div>
    <div class="sc-val" style="color:#166534;font-size:22px">+ <?= money($totalIn) ?></div>
    <div class="sc-sub"><?= __('Collections, sales & receipts') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#fecaca;background:#fef2f2">
    <div class="sc-lbl" style="color:#991b1b">📤 <?= __('Cash Out Today') ?></div>
    <div class="sc-val" style="color:#991b1b;font-size:22px">- <?= money($totalOut) ?></div>
    <div class="sc-sub"><?= __('Expenses, salaries & payments') ?></div>
  </div>
  <div class="stat-chip" style="border-color:<?= $netMovement >= 0 ? '#bbf7d0' : '#fed7aa' ?>;background:<?= $netMovement >= 0 ? '#f0fdf4' : '#fffbeb' ?>">
    <div class="sc-lbl" style="color:<?= $netMovement >= 0 ? '#166534' : '#92400e' ?>">⚖️ <?= __('Net Cash Movement') ?></div>
    <div class="sc-val" style="color:<?= $netMovement >= 0 ? '#166534' : '#92400e' ?>;font-size:22px">
      <?= ($netMovement >= 0 ? '+' : '') . money($netMovement) ?>
    </div>
    <div class="sc-sub"><?= $netMovement >= 0 ? __('Net inflow today') : __('Net outflow today') ?></div>
  </div>
  <div class="stat-chip" style="border-color:#bae6fd;background:#f0f9ff">
    <div class="sc-lbl" style="color:#0369a1">🏦 <?= __('Total Book Balance') ?></div>
    <div class="sc-val" style="color:#0369a1;font-size:22px"><?= money($totalClosing) ?></div>
    <div class="sc-sub"><?= count($accounts) ?> <?= __('cash & bank accounts') ?></div>
  </div>
</div>

<!-- ═══════════ ACCOUNT-WISE BREAKDOWN TABLE ═══════════ -->
<div class="card mb-4" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
    <h3 style="margin:0;font-size:16px;font-weight:700">
      🏦 <?= __('Account-wise Cash Position') ?>
      <span style="font-weight:400;font-size:13px;color:#64748b">(<?= e(date_display($date)) ?>)</span>
    </h3>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table" style="margin:0">
      <thead>
        <tr>
          <th><?= __('Account Name') ?></th>
          <th><?= __('Type') ?></th>
          <th style="text-align:right"><?= __('Opening Balance') ?></th>
          <th style="text-align:right;color:#166534"><?= __('In Today (+)') ?></th>
          <th style="text-align:right;color:#991b1b"><?= __('Out Today (-)') ?></th>
          <th style="text-align:right;font-weight:800"><?= __('Closing Balance') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc): ?>
        <tr>
          <td>
            <strong><?= e($acc['name']) ?></strong>
            <?php if (!empty($acc['account_no'])): ?>
              <span class="muted" style="font-size:11px">(<?= e($acc['account_no']) ?>)</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $acc['type'] === 'cash' ? 'green' : 'blue' ?>" style="text-transform:uppercase;font-size:11px">
              <?= e($acc['type']) ?>
            </span>
          </td>
          <td style="text-align:right"><?= money($acc['day_opening']) ?></td>
          <td style="text-align:right;color:#166534;font-weight:700">
            <?= $acc['day_in'] > 0 ? '+ ' . money($acc['day_in']) : '—' ?>
          </td>
          <td style="text-align:right;color:#991b1b;font-weight:700">
            <?= $acc['day_out'] > 0 ? '- ' . money($acc['day_out']) : '—' ?>
          </td>
          <td style="text-align:right;font-weight:800;font-size:15px;color:<?= $acc['day_closing'] >= 0 ? '#0369a1' : '#b91c1c' ?>">
            <?= money($acc['day_closing']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:800;font-size:15px">
          <td colspan="2"><?= __('Total') ?></td>
          <td style="text-align:right"><?= money($totalOpening) ?></td>
          <td style="text-align:right;color:#166534">+ <?= money($totalIn) ?></td>
          <td style="text-align:right;color:#991b1b">- <?= money($totalOut) ?></td>
          <td style="text-align:right;color:#0369a1;font-size:16px"><?= money($totalClosing) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ═══════════ TODAY'S TRANSACTIONS LIST ═══════════ -->
<div class="card" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
    <h3 style="margin:0;font-size:16px;font-weight:700">
      📋 <?= __('Today\'s Transactions') ?>
      <span style="font-weight:400;font-size:13px;color:#64748b">(<?= count($transactions) ?> <?= __('records') ?>)</span>
    </h3>
  </div>
  <?php if (empty($transactions)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8">
      <?= __('No cash or bank transactions recorded on this date.') ?>
    </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table class="data-table" style="margin:0">
      <thead>
        <tr>
          <th><?= __('Voucher #') ?></th>
          <th><?= __('Account') ?></th>
          <th><?= __('Memo / Details') ?></th>
          <th style="text-align:right;color:#166534"><?= __('In (+)') ?></th>
          <th style="text-align:right;color:#991b1b"><?= __('Out (-)') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td>
            <code><?= e($t['entry_no']) ?></code>
            <span class="badge gray" style="font-size:10px"><?= e($t['voucher_type']) ?></span>
          </td>
          <td>
            <strong><?= e($t['account_name']) ?></strong>
          </td>
          <td style="color:#334155">
            <?= e($t['line_memo'] ?: ($t['entry_memo'] ?: '—')) ?>
          </td>
          <td style="text-align:right;font-weight:700;color:#166534">
            <?= (float)$t['debit'] > 0 ? '+ ' . money((float)$t['debit']) : '—' ?>
          </td>
          <td style="text-align:right;font-weight:700;color:#991b1b">
            <?= (float)$t['credit'] > 0 ? '- ' . money((float)$t['credit']) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:800">
          <td colspan="3"><?= __('Total') ?></td>
          <td style="text-align:right;color:#166534">+ <?= money($totalIn) ?></td>
          <td style="text-align:right;color:#991b1b">- <?= money($totalOut) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
PHP;

file_put_contents($file, $replacement);
echo "daily_position.php cleaned up with pure i18n keys\n";
