<?php $title = __('New Claim'); ?>
<form method="post" class="panel"><?= csrf_field() ?>
<h2><?= __('New Customer Claim') ?></h2>
<div class="form-grid">
  <div style="grid-column:span 2"><label class="f"><?= __('Customer *') ?></label><select class="f" name="customer_id" required><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Related Invoice (optional)') ?></label><select class="f" name="sales_invoice_id"><option value="">—</option><?php foreach ($invoices as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_no']) ?></option><?php endforeach; ?></select></div>
  <div><label class="f"><?= __('Date') ?></label><input class="f" type="date" name="date" value="<?= e(today()) ?>"></div>
  <div><label class="f"><?= __('Claim Amount (Rs)') ?></label><input class="f" type="number" step="0.01" min="0" name="amount" value="0.00"></div>
</div>
<label class="f mt"><?= __('Description *') ?></label><textarea class="f" name="description" rows="3" required placeholder="<?= e(__('e.g. 2 cartons crushed in transit; short-dated sticks in carton #4521')) ?>"></textarea>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('File Claim') ?></button><a class="btn btn-outline" href="<?= e(url('claims')) ?>"><?= __('Cancel') ?></a></div>
</form>
