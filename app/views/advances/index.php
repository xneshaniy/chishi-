<?php $title = __('Advances'); ?>
<div class="simple-hero no-print" style="padding:14px 18px">
  <div class="greet"><span style="font-size:22px;font-weight:800"><?= __('Advances') ?></span><br><small><?= __('Advance payments for every person in the ERP (salesmen, labour, employees)') ?></small></div>
</div>

<div class="stat-strip" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:12px">
  <div class="stat-chip"><div class="sc-lbl"><?= __('People') ?></div><div class="sc-val"><?= (int)$totalPeople ?></div><div class="sc-sub"><?= __('shown') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Requested') ?></div><div class="sc-val"><?= money($totalRequested) ?></div><div class="sc-sub"><?= __('all requests') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Approved') ?></div><div class="sc-val"><?= money($totalApproved) ?></div><div class="sc-sub"><?= __('authorized') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Paid') ?></div><div class="sc-val"><?= money($totalGiven) ?></div><div class="sc-sub"><?= __('disbursed') ?></div></div>
  <div class="stat-chip"><div class="sc-lbl"><?= __('Recovered') ?></div><div class="sc-val"><?= money($totalRecovered) ?></div><div class="sc-sub"><?= __('adjusted against salary') ?></div></div>
  <div class="stat-chip" style="border-color:#f2b8b5"><div class="sc-lbl"><?= __('Outstanding') ?></div><div class="sc-val" style="color:#8f1d13"><?= money($totalOutstanding) ?></div><div class="sc-sub"><?= __('still to recover') ?></div></div>
</div>

<form method="get" class="toolbar" style="margin-bottom:12px">
  <input type="hidden" name="r" value="advances">
  <input name="q" placeholder="<?= e(__('Search by name / ID / phone…')) ?>" value="<?= e($q) ?>" style="min-width:220px">
  <select class="f" name="kind" style="min-width:150px">
    <option value="all" <?= $kind === 'all' ? 'selected' : '' ?>><?= __('All types') ?></option>
    <option value="salesman" <?= $kind === 'salesman' ? 'selected' : '' ?>><?= __('Salesman') ?></option>
    <option value="labour" <?= $kind === 'labour' ? 'selected' : '' ?>><?= __('Labour') ?></option>
    <option value="employee" <?= $kind === 'employee' ? 'selected' : '' ?>><?= __('Employee') ?></option>
  </select>
  <select class="f" name="designation" style="min-width:160px">
    <option value=""><?= __('All designations') ?></option>
    <?php foreach ($designations as $d): ?><option value="<?= e($d) ?>" <?= $designation === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-primary" type="submit"><?= __('Filter') ?></button>
  <a class="btn btn-outline" href="<?= e(url('advances')) ?>"><?= __('Reset') ?></a>
</form>

<div class="emp-grid">
  <?php foreach ($rows as $r): ?>
    <a class="emp-card" href="<?= e(url($r['person_type'] === 'employee' ? 'employees/' . $r['id'] . '?tab=advances' : 'salesmen/' . $r['id'] . '/advances')) ?>">
      <?php if (!empty($r['photo_path'])): ?><img class="emp-photo" src="<?= e(photo_data_uri($r['photo_path'])) ?>" alt="<?= e($r['name']) ?>"><?php else: ?><div class="emp-avatar"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></div><?php endif; ?>
      <div class="emp-name"><?= e($r['name']) ?></div>
      <div class="emp-code"><?= e($r['code']) ?></div>
      <div class="emp-role"><span class="badge <?= $r['kind'] === 'salesman' ? 'blue' : ($r['kind'] === 'labour' ? 'amber' : 'green') ?>"><?= e(strtoupper(PeopleDirectory::kindLabel($r['kind']))) ?></span> <?= e($r['designation']) ?></div>
      <div class="emp-role"><?= __('Requested:') ?> <?= money($r['total_requested']) ?> · <?= __('Paid:') ?> <?= money($r['total_given']) ?> · <?= __('Recovered:') ?> <?= money($r['total_recovered']) ?></div>
      <div class="emp-role" style="color:<?= $r['outstanding'] > 0 ? '#8f1d13' : '#1d6530' ?>"><strong><?= __('Outstanding:') ?> <?= money($r['outstanding']) ?></strong></div>
      <div class="emp-role"><span style="font-size:11px;color:var(--brand);font-weight:700"><?= __('View advance history →') ?></span></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel" style="grid-column:1/-1"><p class="muted"><?= __('No matching person found.') ?></p></div><?php endif; ?>
</div>
