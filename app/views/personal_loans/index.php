<?php
$title = dasti_label();
$from = $from ?? inp('from');
$to = $to ?? inp('to');
$dateType = $dateType ?? inp('date_type', 'loan_date');
$accounts = $accounts ?? db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
?>

<style>
dialog.native-modal::backdrop {
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
}
</style>

<!-- ═══════════════════ HEADER ═══════════════════ -->
<div class="simple-hero no-print" style="padding:14px 20px;margin-bottom:14px">
  <div class="greet">
    <span style="font-size:24px;font-weight:800"><?= e(dasti_label()) ?></span><br>
    <small><?= __('قرض / دستی ریکارڈ — لوگوں کو دی گئی رقم، واپسی کی متوقع تاریخ، بینک و کیش وصولی، اور بقایا رقم کا مکمل حساب') ?></small>
  </div>
</div>

<!-- ═══════════════════ FILTER BAR ═══════════════════ -->
<form method="get" class="toolbar no-print" style="margin-bottom:14px;flex-wrap:wrap;gap:8px">
  <input type="hidden" name="r" value="personal-loans">
  
  <label class="muted" style="font-size:12px"><?= __('Status:') ?></label>
  <select name="filter" onchange="this.form.submit()">
    <?php foreach ([
      'all' => __('All Personal Loans (Dasti)'),
      'pending' => __('Pending / Active'),
      'partial' => __('Partially Paid'),
      'overdue' => __('Overdue'),
      'paid' => __('Fully Paid'),
      'cancelled' => __('Cancelled')
    ] as $k => $l): ?>
      <option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>

  <label class="muted" style="font-size:12px"><?= __('Date Type:') ?></label>
  <select name="date_type">
    <option value="loan_date" <?= $dateType === 'loan_date' ? 'selected' : '' ?>><?= __('Loan Date') ?></option>
    <option value="expected_date" <?= $dateType === 'expected_date' ? 'selected' : '' ?>><?= __('Return Date') ?></option>
  </select>

  <label class="muted" style="font-size:12px"><?= __('From:') ?></label>
  <input type="date" name="from" value="<?= e($from) ?>" style="max-width:145px">

  <label class="muted" style="font-size:12px"><?= __('To:') ?></label>
  <input type="date" name="to" value="<?= e($to) ?>" style="max-width:145px">

  <input name="q" placeholder="<?= e(__('Search person name / phone / loan #…')) ?>" value="<?= e($q) ?>" style="min-width:210px">

  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('personal-loans')) ?>"><?= __('Reset') ?></a>
  
  <span style="flex:1"></span>
  
  <?php if (Auth::can('personal_loans.manage')): ?>
    <a class="btn btn-primary" href="<?= e(url('personal-loans/new')) ?>">+ <?= __('Give New Loan / Dasti (+ نیا قرض / دستی)') ?></a>
  <?php endif; ?>
  <button class="btn btn-outline no-print" type="button" onclick="window.print()">🖨 <?= __('Print') ?></button>
</form>

<!-- ═══════════════════ STAT STRIP ═══════════════════ -->
<div class="stat-strip" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:16px">
  <div class="stat-chip" style="border-color:#f2b8b5;background:#fff5f5">
    <div class="sc-lbl" style="color:#991b1b"><?= __('Total Remaining / Total Outstanding') ?></div>
    <div class="sc-val" style="color:#b91c1c;font-size:24px;font-weight:800"><?= money($summary['outstanding']) ?></div>
    <div class="sc-sub"><?= (int)$summary['people_outstanding'] ?> <?= __('people have remaining balance') ?></div>
  </div>

  <div class="stat-chip" style="border-color:#bfdbfe;background:#eff6ff">
    <div class="sc-lbl" style="color:#1e40af"><?= __('Total Loan Given') ?></div>
    <div class="sc-val" style="color:#1d4ed8;font-size:24px;font-weight:800"><?= money($summary['original']) ?></div>
    <div class="sc-sub"><?= (int)$summary['count'] ?> <?= __('total loans recorded') ?></div>
  </div>

  <div class="stat-chip" style="border-color:#bbf7d0;background:#f0fdf4">
    <div class="sc-lbl" style="color:#166534"><?= __('Total Repaid / Total Repaid') ?></div>
    <div class="sc-val" style="color:#15803d;font-size:24px;font-weight:800"><?= money($summary['repaid']) ?></div>
    <div class="sc-sub"><?= __('credited into cash/bank accounts') ?></div>
  </div>

  <div class="stat-chip" style="border-color:<?= $summary['overdue'] > 0 ? '#fca5a5' : '#e2e8f0' ?>">
    <div class="sc-lbl" style="color:<?= $summary['overdue'] > 0 ? '#b91c1c' : 'inherit' ?>"><?= __('Overdue Loans') ?></div>
    <div class="sc-val" style="color:<?= $summary['overdue'] > 0 ? '#b91c1c' : 'inherit' ?>;font-size:24px;font-weight:800"><?= (int)$summary['overdue'] ?></div>
    <div class="sc-sub"><?= __('passed return date') ?></div>
  </div>
</div>

<!-- ═══════════════════ TABLE ═══════════════════ -->
<div class="panel print-area">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <h2 style="margin:0"><?= __('Loans / Personal Loans Register') ?></h2>
    <span class="badge blue"><?= count($loans) ?> <?= __('Records') ?></span>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th><?= __('Loan #') ?></th>
          <th><?= __('Person Name') ?></th>
          <th><?= __('Phone') ?></th>
          <th><?= __('Loan Date') ?></th>
          <th><?= __('Expected Return Date') ?></th>
          <th><?= __('Account') ?></th>
          <th class="num"><?= __('Given Amount') ?></th>
          <th class="num"><?= __('Repaid Amount') ?></th>
          <th class="num"><?= __('Remaining Balance') ?></th>
          <th><?= __('Status') ?></th>
          <th class="no-print" style="min-width:180px;text-align:right"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loans as $l):
          $badge = ['pending' => 'amber', 'partial' => 'blue', 'paid' => 'green', 'overdue' => 'red', 'cancelled' => 'gray'][$l['status']] ?? 'gray';
          $lbl = ['pending' => __('Pending / Active'), 'partial' => __('Partially Paid'), 'paid' => __('Fully Paid'), 'overdue' => __('OVERDUE'), 'cancelled' => __('Cancelled')][$l['status']] ?? $l['status'];
          $isPast = !empty($l['expected_date']) && strtotime($l['expected_date']) < strtotime(date('Y-m-d')) && $l['remaining_amount'] > 0.004;
          $dlgRepayId = 'dlg-repay-' . (int)$l['id'];
        ?>
        <tr style="<?= $isPast ? 'background:#fff5f5' : '' ?>">
          <td class="mono">
            <a href="<?= e(url('personal-loans/' . (int)$l['id'])) ?>" style="font-weight:700;text-decoration:none">
              <?= e($l['loan_no']) ?>
            </a>
          </td>
          <td>
            <strong><?= e($l['person_name']) ?></strong>
            <?php if (!empty($l['description'])): ?>
              <br><span class="muted" style="font-size:11px"><?= e($l['description']) ?></span>
            <?php endif; ?>
          </td>
          <td class="mono"><?= e($l['phone'] ?: '—') ?></td>
          <td><?= e(date_display($l['loan_date'])) ?></td>
          <td style="<?= $isPast ? 'color:#b91c1c;font-weight:700' : '' ?>">
            <?= $l['expected_date'] ? e(date_display($l['expected_date'])) : '—' ?>
            <?php if ($isPast): ?>
              <br><small style="color:#b91c1c"><?= __('(Overdue)') ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($l['account_name'])): ?>
              <span class="badge gray" style="font-size:11px"><?= e($l['account_name']) ?></span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="num" style="font-weight:700"><?= money($l['original_amount']) ?></td>
          <td class="num" style="color:#15803d;font-weight:700"><?= money($l['total_repaid']) ?></td>
          <td class="num" style="font-size:15px;font-weight:800;color:<?= $l['remaining_amount'] > 0 ? '#b91c1c' : '#15803d' ?>">
            <?= money($l['remaining_amount']) ?>
          </td>
          <td>
            <span class="badge <?= $badge ?>" style="font-weight:700">
              <?= e(strtoupper($lbl)) ?>
            </span>
          </td>
          <td class="no-print" style="text-align:right">
            <div style="display:inline-flex;gap:6px;align-items:center">
              <?php if (Auth::can('personal_loans.manage') && $l['remaining_amount'] > 0.004 && $l['status'] !== 'cancelled'): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('<?= e($dlgRepayId) ?>').showModal()" title="<?= e(__('Record repayment from borrower')) ?>">
                  + <?= __('Repay') ?>
                </button>
              <?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('personal-loans/' . (int)$l['id'])) ?>">
                <?= __('View') ?> →
              </a>
            </div>

            <!-- Native Dialog for Quick Repayment -->
            <?php if (Auth::can('personal_loans.manage') && $l['remaining_amount'] > 0.004 && $l['status'] !== 'cancelled'): ?>
            <dialog id="<?= e($dlgRepayId) ?>" class="native-modal" style="border:none;border-radius:12px;padding:22px;max-width:480px;width:92%;box-shadow:0 12px 36px rgba(0,0,0,0.25);background:#fff;text-align:left">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid var(--line);padding-bottom:10px">
                <h3 style="margin:0;font-size:17px"><?= __('Record Return / Repayment') ?></h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= e($dlgRepayId) ?>').close()" style="padding:2px 8px">✕</button>
              </div>
              
              <div style="background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px">
                <div><strong><?= __('Person:') ?></strong> <?= e($l['person_name']) ?> (<?= e($l['loan_no']) ?>)</div>
                <div><strong><?= __('Original Loan:') ?></strong> <?= money($l['original_amount']) ?></div>
                <div style="color:#b91c1c;font-size:15px;font-weight:800;margin-top:4px">
                  <?= __('Remaining Balance:') ?> <?= money($l['remaining_amount']) ?>
                </div>
              </div>

              <form method="post" action="<?= e(url('personal-loans/' . (int)$l['id'] . '/repay')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid" style="grid-template-columns:1fr;gap:12px">
                  <div>
                    <label class="f"><?= __('Repayment Date *') ?></label>
                    <input class="f" type="date" name="repayment_date" required value="<?= e(today()) ?>">
                  </div>
                  <div>
                    <label class="f"><?= __('Amount Received *') ?></label>
                    <input class="f num" type="number" step="0.01" min="0.01" max="<?= e((string)$l['remaining_amount']) ?>" name="amount" required value="<?= e((string)$l['remaining_amount']) ?>" style="font-size:16px;font-weight:700;color:#15803d">
                    <small class="muted"><?= __('جیسے 10 ہزار میں سے 5 ہزار واپس کیے تو 5 ہزار ڈالیں') ?></small>
                  </div>
                  <div>
                    <label class="f"><?= __('Deposit To Account *') ?></label>
                    <select class="f" name="account_id" required>
                      <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <small class="muted"><?= __('اس اکاؤنٹ میں رقم جمع ہو کر بیلنس بڑھ جائے گا') ?></small>
                  </div>
                  <div>
                    <label class="f"><?= __('Payment Note') ?></label>
                    <input class="f" name="note" placeholder="<?= e(__('e.g. Cash received / online bank transfer')) ?>">
                  </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                  <button type="button" class="btn btn-outline" onclick="document.getElementById('<?= e($dlgRepayId) ?>').close()"><?= __('Cancel') ?></button>
                  <button type="submit" class="btn btn-primary"><?= __('Save Repayment') ?> →</button>
                </div>
              </form>
            </dialog>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?>
        <tr><td colspan="11" class="muted" style="text-align:center;padding:36px"><?= __('No loans match the search or filter.') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
