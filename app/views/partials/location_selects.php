<?php
/**
 * Pakistan province / city / manual-area selector.
 * Expects (optional): $province_id, $city_id, $area_text (legacy display), $area_id.
 * Emits: province_id, city_id, area (free text; server derives/validates area_id).
 */
$provinces = location_provinces();
$cities = location_cities();
$areas = location_areas();
$province_json = json_encode($provinces);
$cities_json  = json_encode($cities);
$areas_json   = json_encode($areas);
$selProvince = (int)($province_id ?? 0);
$selCity     = (int)($city_id ?? 0);
$selAreaText = $area_text ?? '';
?>
<div class="span-2">
  <label class="f"><?= __('Province') ?></label>
  <select class="f" name="province_id" id="locProvince">
    <option value=""><?= __('— select province —') ?></option>
    <?php foreach ($provinces as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $selProvince === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
  </select>
</div>
<div>
  <label class="f"><?= __('City / District') ?></label>
  <select class="f" name="city_id" id="locCity"><option value=""><?= __('— select province first —') ?></option></select>
</div>
<div>
  <label class="f"><?= __('Area / Locality') ?> <span class="muted" style="font-weight:400"><?= __('(manual)') ?></span></label>
  <input class="f" name="area" id="locArea" list="areaList" value="<?= e($selAreaText) ?>" placeholder="<?= e(__('Type area, village, market…')) ?>" autocomplete="off">
  <datalist id="areaList"></datalist>
</div>
<script>
(function(){
  var provinces = <?= $province_json ?>;
  var cities = <?= $cities_json ?>;
  var areas = <?= $areas_json ?>;
  var selP = document.getElementById('locProvince');
  var selC = document.getElementById('locCity');
  var inpA = document.getElementById('locArea');
  var list = document.getElementById('areaList');
  if (!selP || !selC) return;

  function fillCities(pId, selectedCity) {
    var opts = '<option value=""><?= e(__('— select city —')) ?></option>';
    var list = cities[pId] || [];
    list.forEach(function(c){ opts += '<option value="' + c.id + '"' + (String(c.id) === String(selectedCity) ? ' selected' : '') + '>' + c.name + '</option>'; });
    selC.innerHTML = opts;
  }
  function fillAreas(cId) {
    var opts = '';
    var list = areas[cId] || [];
    list.forEach(function(a){ opts += '<option value="' + a.name.replace(/"/g,'&quot;') + '"></option>'; });
    if (list) document.getElementById('areaList').innerHTML = opts;
  }
  selP.addEventListener('change', function(){ fillCities(this.value); fillAreas(0); });
  selC.addEventListener('change', function(){ fillAreas(this.value); });
  // Preselect saved values on page load
  <?php if ($selProvince): ?>
  fillCities(<?= $selProvince ?>, <?= $selCity ?: '0' ?>);
  <?php endif; ?>
  <?php if ($selCity): ?>
  fillAreas(<?= $selCity ?>);
  <?php endif; ?>
})();
</script>
