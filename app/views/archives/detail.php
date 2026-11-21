<?php $title = 'Archives — ' . $label; ?>
<div class="btn-group no-print" style="margin-bottom:10px"><a class="btn btn-outline btn-sm" href="<?= e(url('admin/archives')) ?>">&larr; <?= __('Back to Archives') ?></a></div>
<div class="simple-hero" style="padding:14px 20px;margin-bottom:12px">
  <div class="greet"><span style="font-size:22px;font-weight:800"><?= e($label) ?></span><br><small><?= __('Archived records — stored here, hidden from daily use. Restore to reactivate (all history preserved), or delete after every incoming reference is 0.') ?></small></div>
</div>
<?php if (!$rows): ?><p class="muted"><?= __('No archived records.') ?></p><?php else: ?>
<div class="table-wrap"><table class="tbl">
  <thead><tr><th>#</th><th><?= __('Identity') ?></th><th><?= __('Extra') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i => $r):
    $title = (string)($r['name'] ?? $r['code'] ?? $r['reg_no'] ?? $r['sku'] ?? ('#' . $r['id']));
    $sub = (string)(($r['sku'] ?? '') . ($r['category'] ?? '') . ($r['kind'] ?? '') . ($r['driver_name'] ?? '') . ($r['gl_account_id'] ?? ''));
  ?><tr>
      <td class="mono"><?= (int)$r['id'] ?></td>
      <td><strong><?= e($title) ?></strong><?php if ($sub): ?><br><span class="muted"><?= e($sub) ?></span><?php endif; ?></td>
      <td><?= e((string)($r['kind'] ?? $r['driver_name'] ?? $r['type'] ?? $r['category'] ?? $r['category_id'] ?? '')) ?></td>
      <td class="btn-group" style="gap:6px">
        <form method="post" action="<?= e(url('admin/archives/' . $table . '/restore')) ?>" style="display:inline-flex;gap:4px;align-items:center">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="table" value="<?= e($table) ?>"><input name="reason" placeholder="<?= e(__('reason')) ?>" class="f" style="width:160px;padding:4px 6px;font-size:12px" required><button class="btn btn-primary btn-sm" type="submit" data-confirm="<?= e(__('Restore this record?')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Restore')) ?>" data-confirm-variant="approve"><?= __('Restore') ?></button>
        </form>
        <form method="post" action="<?= e(url('admin/archives/' . $table . '/delete')) ?>" style="display:inline-flex;gap:4px;align-items:center">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="table" value="<?= e($table) ?>"><input name="reason" placeholder="<?= e(__('reason')) ?>" class="f" style="width:160px;padding:4px 6px;font-size:12px" required><button class="btn btn-danger btn-sm" type="submit" data-confirm="<?= e(__('Delete permanently? Only allowed when nothing references this record.')) ?>" data-confirm-title="<?= e(__('Please Confirm')) ?>" data-confirm-ok="<?= e(__('Delete')) ?>" data-confirm-variant="danger"><?= __('Delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
