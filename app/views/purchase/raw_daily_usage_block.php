<?php
// This file is appended to raw.php — daily-usage tab block
?>
<?php if ($activeTab === 'daily-usage'): ?>
<?php
// safe defaults if controller didn't pass these (guard)
$usageDate       = $usageDate       ?? date('Y-m-d');
$usageFrom       = $usageFrom       ?? date('Y-m-01');
$usageTo         = $usageTo         ?? date('Y-m-d');
$usageMat        = $usageMat        ?? 0;
$dailyEntries    = $dailyEntries    ?? [];
$dailySummary    = $dailySummary    ?? [];
$usageProducts   = $usageProducts   ?? [];
$usageWarehouses = $usageWarehouses ?? [];
$defaultUsageWh  = $defaultUsageWh  ?? 0;
$todayTotalKg    = $todayTotalKg    ?? 0.0;
$todayEntryCount = $todayEntryCount ?? 0;
$monthTotalKg    = $monthTotalKg    ?? 0.0;
?>

<div class="page-body" style="max-width:1100px;margin:0 auto;padding:16px">

  <!-- ══════════════════ SUMMARY CHIPS ══════════════════ -->
  <div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin-bottom:18px">
    <div class="stat-chip" style="border-color:#a5f3fc">
      <div class="sc-lbl">📅 <?= e($usageDate) ?> <?= __('کا یوز') ?></div>
      <div class="sc-val" style="color:#0e7490"><?= number_format($todayTotalKg, 2) ?> <span class="muted" style="font-size:12px">KG</span></div>
      <div class="sc-sub"><?= $todayEntryCount ?> <?= __('entries') ?></div>
    </div>
    <div class="stat-chip" style="border-color:#bbf7d0">
      <div class="sc-lbl">📆 <?= __('اس مہینے کا کل یوز') ?></div>
      <div class="sc-val" style="color:#166534"><?= number_format($monthTotalKg, 2) ?> <span class="muted" style="font-size:12px">KG</span></div>
      <div class="sc-sub"><?= date('F Y') ?></div>
    </div>
    <div class="stat-chip" style="border-color:#fde68a">
      <div class="sc-lbl">🏭 <?= __('مواد کی تعداد') ?></div>
      <div class="sc-val" style="color:#92400e"><?= count($usageProducts) ?></div>
      <div class="sc-sub"><?= __('Raw / Packaging') ?></div>
    </div>
  </div>

  <!-- ══════════════════ ADD ENTRY FORM ══════════════════ -->
  <?php if (Auth::can('rm.usage')): ?>
  <div class="card mb-4" style="border:1px solid #bae6fd;border-radius:10px;padding:18px;background:#f0f9ff">
    <h3 style="margin:0 0 14px;font-size:16px;font-weight:700">➕ <?= __('Add Daily Usage') ?></h3>
    <form method="post" action="<?= e(url('purchase/raw')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_daily_usage">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;align-items:end">
        <div>
          <label class="form-label"><?= __('Date') ?> <span style="color:red">*</span></label>
          <input type="date" name="usage_date" value="<?= e($usageDate) ?>" class="form-control" required>
        </div>
        <div>
          <label class="form-label"><?= __('Raw Material') ?> <span style="color:red">*</span></label>
          <select name="product_id" class="form-control" required>
            <option value="">— <?= __('منتخب کریں') ?> —</option>
            <?php foreach ($usageProducts as $up): ?>
            <option value="<?= (int)$up['id'] ?>"><?= e($up['name']) ?> <small>(<?= e($up['sku']) ?>)</small></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label"><?= __('مقدار KG') ?> <span style="color:red">*</span></label>
          <input type="number" name="qty_kg" min="0.001" step="0.001" placeholder="0.000" class="form-control" required>
        </div>
        <?php if (!empty($usageWarehouses)): ?>
        <div>
          <label class="form-label"><?= __('Warehouse') ?></label>
          <select name="warehouse_id" class="form-control">
            <?php foreach ($usageWarehouses as $wh): ?>
            <option value="<?= (int)$wh['id'] ?>" <?= (int)$wh['id'] === (int)$defaultUsageWh ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label class="form-label"><?= __('Note') ?></label>
          <input type="text" name="note" placeholder="<?= e(__('پروڈکشن کا نوٹ…')) ?>" class="form-control">
        </div>
        <div style="display:flex;align-items:flex-end">
          <button type="submit" class="btn btn-primary" style="width:100%">💾 <?= __('محفوظ کریں') ?></button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- ══════════════════ TODAY'S ENTRIES TABLE ══════════════════ -->
  <div class="card mb-4" style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
      <h3 style="margin:0;font-size:15px;font-weight:700">
        📋 <?= __('تاریخ کی یوز انٹریز') ?>
        <span style="font-weight:400;font-size:13px;color:#64748b">(<?= e($usageDate) ?>)</span>
      </h3>
      <!-- Date navigation -->
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="r" value="purchase/raw">
        <input type="hidden" name="tab" value="daily-usage">
        <input type="date" name="usage_date" value="<?= e($usageDate) ?>" class="form-control" style="width:150px">
        <button type="submit" class="btn btn-outline btn-sm"><?= __('دیکھیں') ?> →</button>
      </form>
    </div>
    <?php if (empty($dailyEntries)): ?>
      <div style="padding:28px;text-align:center;color:#94a3b8">
        <?= __('اس تاریخ کی کوئی انٹری نہیں۔') ?>
      </div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table class="data-table" style="margin:0">
        <thead>
          <tr>
            <th>#</th>
            <th><?= __('را مٹیریل') ?></th>
            <th><?= __('SKU') ?></th>
            <th style="text-align:right"><?= __('Quantity (KG)') ?></th>
            <th><?= __('گودام') ?></th>
            <th><?= __('نوٹ') ?></th>
            <th><?= __('ریکارڈ کرنے والا') ?></th>
            <th><?= __('اسٹیٹس') ?></th>
            <?php if (is_owner_or_admin()): ?><th><?= __('کینسل') ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dailyEntries as $de): ?>
          <?php $cancelled = $de['status'] !== 'posted'; ?>
          <tr style="<?= $cancelled ? 'opacity:.5;text-decoration:line-through' : '' ?>">
            <td><?= (int)$de['id'] ?></td>
            <td><strong><?= e($de['product_name']) ?></strong></td>
            <td><code><?= e($de['sku']) ?></code></td>
            <td style="text-align:right;font-weight:700;color:<?= $cancelled ? '#94a3b8' : '#0e7490' ?>">
              <?= number_format((float)$de['qty_kg'], 3) ?> KG
            </td>
            <td><?= e($de['warehouse_name'] ?? '—') ?></td>
            <td style="color:#64748b"><?= e($de['note'] ?? '—') ?></td>
            <td><?= e($de['recorded_by_username'] ?? '—') ?></td>
            <td>
              <?php if ($cancelled): ?>
                <span style="color:#ef4444;font-size:11px">❌ <?= __('کینسل') ?><br><small><?= e($de['cancel_reason'] ?? '') ?></small></span>
              <?php else: ?>
                <span style="color:#22c55e;font-weight:700">✅ <?= __('پوسٹڈ') ?></span>
              <?php endif; ?>
            </td>
            <?php if (is_owner_or_admin()): ?>
            <td>
              <?php if (!$cancelled): ?>
              <button type="button" class="btn btn-danger btn-sm"
                onclick="document.getElementById('cancel-du-<?= (int)$de['id'] ?>').showModal()">🗑 <?= __('کینسل') ?></button>
              <dialog id="cancel-du-<?= (int)$de['id'] ?>" style="border-radius:10px;border:1px solid #fca5a5;padding:20px;min-width:320px">
                <form method="post" action="<?= e(url('purchase/raw')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="cancel_daily_usage">
                  <input type="hidden" name="usage_id" value="<?= (int)$de['id'] ?>">
                  <p style="margin:0 0 10px;font-weight:700">⚠️ <?= __('کیا آپ واقعی کینسل کرنا چاہتے ہیں؟') ?></p>
                  <p style="margin:0 0 10px;font-size:13px;color:#64748b"><strong><?= e($de['product_name']) ?></strong> — <?= number_format((float)$de['qty_kg'],3) ?> KG</p>
                  <label class="form-label"><?= __('وجہ (ضروری) / Reason') ?></label>
                  <input type="text" name="reason" class="form-control mb-2" required placeholder="<?= e(__('وجہ لکھیں…')) ?>">
                  <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn btn-outline" onclick="this.closest('dialog').close()"><?= __('واپس') ?></button>
                    <button type="submit" class="btn btn-danger">🗑 <?= __('ہاں، کینسل کریں') ?></button>
                  </div>
                </form>
              </dialog>
              <?php else: ?>—<?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════ DATE-RANGE SUMMARY REPORT ══════════════════ -->
  <div class="card" style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
      <h3 style="margin:0;font-size:15px;font-weight:700">📊 <?= __('Date-Range Usage Report') ?></h3>
      <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="r" value="purchase/raw">
        <input type="hidden" name="tab" value="daily-usage">
        <label class="muted" style="font-size:12px"><?= __('سے') ?></label>
        <input type="date" name="usage_from" value="<?= e($usageFrom) ?>" class="form-control" style="width:145px">
        <label class="muted" style="font-size:12px"><?= __('تک') ?></label>
        <input type="date" name="usage_to"   value="<?= e($usageTo) ?>"   class="form-control" style="width:145px">
        <select name="usage_mat" class="form-control" style="width:180px">
          <option value="">— <?= __('سب مواد') ?> —</option>
          <?php foreach ($usageProducts as $up): ?>
          <option value="<?= (int)$up['id'] ?>" <?= (int)$usageMat===(int)$up['id'] ? 'selected' : '' ?>><?= e($up['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="usage_date" value="<?= e($usageDate) ?>">
        <button type="submit" class="btn btn-primary btn-sm"><?= __('رپورٹ') ?> 📊</button>
        <a class="btn btn-outline btn-sm" href="<?= e(url('purchase/raw', ['tab'=>'daily-usage'])) ?>"><?= __('ریسیٹ') ?></a>
      </form>
    </div>
    <?php if (empty($dailySummary)): ?>
      <div style="padding:28px;text-align:center;color:#94a3b8"><?= __('اس رینج میں کوئی ڈیٹا نہیں۔') ?></div>
    <?php else: ?>
    <?php
      // Aggregate totals for summary chips
      $rptTotalKg = 0.0; $rptMatSet = [];
      foreach ($dailySummary as $ds) { $rptTotalKg += (float)$ds['total_kg']; $rptMatSet[$ds['sku']] = true; }
    ?>
    <div style="display:flex;gap:12px;padding:12px 16px;background:#fafafa;border-bottom:1px solid #e2e8f0;flex-wrap:wrap">
      <div class="stat-chip" style="margin:0;padding:8px 14px;min-height:auto">
        <span class="muted" style="font-size:12px"><?= __('کل KG یوز') ?></span>
        <strong style="display:block;font-size:18px;color:#0e7490"><?= number_format($rptTotalKg,2) ?> KG</strong>
      </div>
      <div class="stat-chip" style="margin:0;padding:8px 14px;min-height:auto">
        <span class="muted" style="font-size:12px"><?= __('مواد کی اقسام') ?></span>
        <strong style="display:block;font-size:18px;color:#7c3aed"><?= count($rptMatSet) ?></strong>
      </div>
      <div class="stat-chip" style="margin:0;padding:8px 14px;min-height:auto">
        <span class="muted" style="font-size:12px"><?= __('دن') ?></span>
        <strong style="display:block;font-size:18px;color:#b45309"><?= count(array_unique(array_column($dailySummary,'usage_date'))) ?></strong>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table class="data-table" style="margin:0">
        <thead>
          <tr>
            <th><?= __('تاریخ') ?></th>
            <th><?= __('را مٹیریل') ?></th>
            <th><?= __('SKU') ?></th>
            <th style="text-align:right"><?= __('کل KG یوز') ?></th>
            <th style="text-align:right"><?= __('انٹریز') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $prevDate = ''; ?>
          <?php foreach ($dailySummary as $ds): ?>
          <tr>
            <td>
              <?php if ($ds['usage_date'] !== $prevDate): ?>
                <strong><?= e($ds['usage_date']) ?></strong>
                <?php $prevDate = $ds['usage_date']; ?>
              <?php else: ?>
                <span style="color:transparent">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($ds['product_name']) ?></td>
            <td><code><?= e($ds['sku']) ?></code></td>
            <td style="text-align:right;font-weight:700;color:#0e7490"><?= number_format((float)$ds['total_kg'],3) ?> KG</td>
            <td style="text-align:right;color:#64748b"><?= (int)$ds['entries'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f1f5f9;font-weight:700">
            <td colspan="3"><?= __('کل مجموعہ') ?></td>
            <td style="text-align:right;color:#0e7490"><?= number_format($rptTotalKg,3) ?> KG</td>
            <td style="text-align:right"><?= array_sum(array_column($dailySummary,'entries')) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /page-body -->
<?php endif; // daily-usage tab ?>
