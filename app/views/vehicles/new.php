<?php $title = __('New Vehicle'); ?>
<form method="post" enctype="multipart/form-data" class="panel"><?= csrf_field() ?>
<h2><?= __('New Vehicle') ?></h2>
<div class="form-grid">
  <div><label class="f"><?= __('Vehicle Name') ?></label><input class="f" name="name" placeholder="<?= e(__('e.g. Factory Pickup #3')) ?>"></div>
  <div><label class="f"><?= __('Registration No *') ?></label><input class="f" name="reg_no" required placeholder="<?= e(__('e.g. LEB-1234')) ?>"></div>
  <div><label class="f"><?= __('Vehicle Type') ?></label><input class="f" name="vehicle_type" placeholder="<?= e(__('e.g. Pickup / Truck / Shehzore / Motorcycle')) ?>"></div>
  <div><label class="f"><?= __('Make') ?></label><input class="f" name="make" placeholder="Toyota / Suzuki / Hino"></div>
  <div><label class="f"><?= __('Model') ?></label><input class="f" name="model" placeholder="Dyna / Mazda / Shehzore"></div>
  <div><label class="f"><?= __('Year') ?></label><input class="f" name="year" type="number" min="1980" max="2100"></div>
  <div><label class="f"><?= __('Purchase Date') ?></label><input class="f" type="date" name="purchase_date"></div>
  <div><label class="f"><?= __('Driver Name') ?></label><input class="f" name="driver_name"></div>
  <div><label class="f"><?= __('Status') ?></label><select class="f" name="status"><option value="active"><?= __('Active') ?></option><option value="inactive"><?= __('Inactive / Sold') ?></option><option value="retired"><?= __('Retired') ?></option></select></div>
  <div><label class="f"><?= __('Vehicle Photo') ?></label><input class="f" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
  <div style="grid-column:span 2"><label class="f"><?= __('Notes') ?></label><textarea class="f" name="notes" rows="2" placeholder="<?= e(__('Optional notes…')) ?>"></textarea></div>
</div>
<div class="btn-group mt"><button class="btn btn-primary" type="submit"><?= __('Add Vehicle') ?></button><a class="btn btn-outline" href="<?= e(url('vehicles')) ?>"><?= __('Cancel') ?></a></div>
</form>
