<?php $title = __('Units & Packaging Conversions'); ?>
<form method="post"><?= csrf_field() ?>
<div class="two-col">
  <div class="panel">
    <h2><?= __('Raw Material Weight Units') ?></h2>
    <p class="help"><?= __('Raw materials are stored in KG. Opening stock, purchases (TON/KG/MAN) and factory consumption are all converted to KG with these factors. Historical transactions are never rewritten when you change them.') ?></p>
    <div class="form-grid">
      <div><label class="f"><?= __('1 TON = (KG)') ?></label><input class="f" type="number" step="0.001" min="0.001" name="rm_unit_ton" value="<?= e(setting('rm_unit_ton', '1000')) ?>" required></div>
      <div><label class="f"><?= __('1 MAN (Maund) = (KG)') ?></label><input class="f" type="number" step="0.001" min="0.001" name="rm_unit_man" value="<?= e(setting('rm_unit_man', '40')) ?>" required></div>
      <div><label class="f"><?= __('KG (base unit)') ?></label><input class="f" type="number" step="0.001" value="1" readonly></div>
    </div>
    <p class="help"><?= __('Example: 2 TON + 100 KG =') ?><strong><?= number_format(2 * (float)setting('rm_unit_ton', '1000') + 100, 3) ?> kg</strong></p>
  </div>
  <div class="panel">
    <h2><?= __('Packaging Conversions (FG)') ?></h2>
    <p class="help"><?= __('Finished goods are stored in the product unit. Packets and Bundles are exact conversions — when configured, the closing stock is shown in both. Defaults apply to every FG product without its own override (Products → Edit → Packaging).') ?></p>
    <p class="help"><?= __('Conversion used by default: 10 Packets = 1 Bundle. 2 Bundles = 20 Packets. The base unit (Packet) is configured first; Bundle is configured from that ratio.') ?></p>
    <div class="form-grid">
      <div><label class="f"><?= __('Pieces per Packet (1 = packet is the base unit)') ?></label><input class="f" type="number" step="0.001" min="0" name="packaging_pack_pieces" value="<?= e(setting('packaging_pack_pieces', '1')) ?>" required></div>
      <div><label class="f"><?= __('Packets per Bundle') ?></label><input class="f" type="number" step="0.001" min="0" name="packaging_bundle_packs" value="<?= e(setting('packaging_bundle_packs', '10')) ?>"></div>
      <div><label class="f"><?= __('Bundles per Packet (0 = not used)') ?></label><input class="f" type="number" step="0.001" min="0" name="packaging_packet_bundles" value="<?= e(setting('packaging_packet_bundles', '0')) ?>"></div>
    </div>
    <div id="pkgCalc" class="help" style="margin-top:6px;font-weight:700"></div>
    <script>
    (function(){
      var perPkt = document.querySelector('input[name="packaging_pack_pieces"]');
      var perBundle = document.querySelector('input[name="packaging_bundle_packs"]');
      var out = document.getElementById('pkgCalc');
      function calc(){
        var p = parseFloat(perPkt.value) || 0, b = parseFloat(perBundle.value) || 0;
        if (p > 0 && b > 0) {
          out.textContent = b.toLocaleString() + ' Packets = 1 Bundle  ·  1 Bundle = ' + b.toLocaleString() + ' Packets  ·  2 Bundles = ' + (2 * b).toLocaleString() + ' Packets';
        } else {
          out.textContent = 'Set both values to see the conversion hint.';
        }
      }
      [perPkt, perBundle].forEach(function(el){ el.addEventListener('input', calc); });
      calc();
    })();
    </script>
  </div>
</div>
<div class="panel">
  <h2><?= __('Factory Consumption Approval') ?></h2>
  <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="rm_consumption_approval" value="1" <?= setting('rm_consumption_approval', '0') === '1' ? 'checked' : '' ?>><?= __('Require Owner/Admin approval for factory consumption requests (recommended OFF — daily issues post directly)') ?></label>
  <p class="help"><?= __('When OFF, workers with production.release post consumption directly. When ON, every consumption request waits in Admin Console (type RM_CONSUMPTION) before stock is deducted. Cancellation always stays with journal.reverse.') ?></p>
  <button class="btn btn-primary" type="submit"><?= __('Save Conversions') ?></button>
</div>
</form>
