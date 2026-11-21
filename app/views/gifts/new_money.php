<?php $title = __('New Money Gift'); ?>
<div class="alert alert-info"><?= __('Money gifts are recorded as an expense (Gifts category) and posted by the Owner/Admin exactly once on approval — no cash/bank moves until approved.') ?></div>
<form method="post" class="panel simple-form print-area"><?= csrf_field() ?>
  <h2><?= __('Money Gift') ?></h2>
  <div class="form-grid">
    <div><label class="f"><?= __('Recipient name *') ?></label><input class="f" name="recipient_name" required placeholder="<?= e(__('Full name of the person')) ?>"></div>
    <div><label class="f"><?= __('Recipient type') ?></label><select class="f" name="recipient_type"><option value="employee"><?= __('Employee') ?></option><option value="relative"><?= __('Relative') ?></option><option value="customer"><?= __('Customer') ?></option><option value="guest" selected><?= __('Guest') ?></option><option value="other"><?= __('Other') ?></option></select></div>
    <div><label class="f"><?= __('Gift date *') ?></label><input class="f" type="date" name="gift_date" required value="<?= e(today()) ?>"></div>
    <div><label class="f"><?= __('Amount (PKR) *') ?></label><input class="f" type="number" step="0.01" min="0.01" name="amount" required placeholder="<?= e(__('e.g. 5000')) ?>"></div>
    <div><label class="f"><?= __('Payment method') ?></label><select class="f" name="payment_method" id="payMethod"><option value="bank"><?= __('Bank') ?></option><option value="cash"><?= __('Cash') ?></option></select></div>
    <div id="accountWrapper"><label class="f"><?= __('Bank account') ?></label><select class="f" name="account_id"><option value=""><?= __('— select —') ?></option><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= strtoupper(e($a['type'])) ?>)</option><?php endforeach; ?></select></div>
    <div class="span-2"><label class="f"><?= __('Reason / occasion') ?></label><input class="f" name="occasion" placeholder="<?= e(__('e.g. Eid, marriage, personal favor')) ?>"></div>
    <div class="span-2"><label class="f"><?= __('Description') ?></label><textarea class="f" name="description" rows="2" placeholder="<?= e(__('Why is this gift being given?')) ?>"></textarea></div>
    <div class="span-2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2"></textarea></div>
  </div>
  <div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Record Gift (pending approval)') ?></button><a class="btn btn-outline" href="<?= e(url('gifts')) ?>"><?= __('Cancel') ?></a></div>
</form>
<script>
const t = document.getElementById('payMethod'), w = document.getElementById('accountWrapper');
t.addEventListener('change', () => { w.style.display = t.value === 'cash' ? 'none' : ''; });
</script>
