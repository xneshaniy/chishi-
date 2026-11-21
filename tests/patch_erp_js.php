<?php
/** Patch erp.js to add raw/packaging unit (KG/TON/MAN) row support. */
$f = 'assets/js/erp.js';
$s = file_get_contents($f);

// 1. buildOptions carries unit+kind attributes
$old1 = "data-unit=\"' + (p.unit_short || '') + '\">'";
$new1 = "data-unit=\"' + (p.unit_short || '') + '\" data-kind=\"' + (p.kind || '') + '\">'";
$n1 = substr_count($s, $old1);
$s = str_replace($old1, $new1, $s);
echo "buildOptions kind attr: $n1\n";

// 2. Add unitSelectHtml + syncUnitSelect after buildOptions block
$anchor = "  // Inject options into server-rendered rows that have none";
$inject = "  // Per-row unit select for raw materials & packaging (KG/TON/MAN); FG products are locked to their unit.
  function unitSelectHtml(k) {
    return '<td><select class=\"f item-unit\" style=\"width:74px\" name=\"' + (cfg.field || 'items') + '[unit][' + k + ']\">'
      + '<option value=\"KG\">KG</option><option value=\"TON\">TON</option><option value=\"MAN\">MAN</option>'
      + '</select></td>';
  }
  function syncUnitSelect(tr) {
    if (!cfg.unit) return;
    var sel = tr.querySelector('[name*=\"[product_id]\"]');
    var unitSel = tr.querySelector('.item-unit');
    if (!sel || !unitSel) return;
    var kind = sel.selectedOptions[0] ? sel.selectedOptions[0].getAttribute('data-kind') : '';
    if (kind === 'raw' || kind === 'packaging') {
      var prev = unitSel.value;
      unitSel.innerHTML = '<option value=\"KG\">KG</option><option value=\"TON\">TON</option><option value=\"MAN\">MAN</option>';
      unitSel.value = (prev === 'KG' || prev === 'TON' || prev === 'MAN') ? prev : 'KG';
      unitSel.disabled = false;
    } else {
      var unit = sel.selectedOptions[0] ? (sel.selectedOptions[0].getAttribute('data-unit') || '') : '';
      unitSel.innerHTML = '<option value=\"' + unit + '\">' + unit + '</option>';
      unitSel.value = unit || 'KG';
      unitSel.disabled = true;
    }
  }
" . $anchor;
$n2 = substr_count($s, $anchor);
$s = str_replace($anchor, $inject, $s);
echo "inject sync fn: $n2\n";

// 3. new rows include unit cell when cfg.unit
$old3 = "      '<td><input class=\"f\" style=\"width:80px\" type=\"number\" step=\"0.001\" min=\"0\" name=\"' + name + '[qty][' + k + ']\" value=\"1\" required></td>' +";
$new3 = "      '<td><input class=\"f\" style=\"width:80px\" type=\"number\" step=\"0.001\" min=\"0\" name=\"' + name + '[qty][' + k + ']\" value=\"1\" required></td>' +\n      (cfg.unit ? unitSelectHtml(k) : '') +";
$n3 = substr_count($s, $old3);
$s = str_replace($old3, $new3, $s);
echo "new rows unit cell: $n3\n";

// 4. sync unit select on product change + initial rows (listen + init + call after options injection)
$old4 = "  // Inject options into server-rendered rows that have none\n  tbody.querySelectorAll('select[name*=\"[product_id]\"]').forEach(function (sel) {\n    if (sel.options.length <= 1) sel.innerHTML = buildOptions();\n  });";
$new4 = "  // Inject options into server-rendered rows that have none\n  tbody.querySelectorAll('select[name*=\"[product_id]\"]').forEach(function (sel) {\n    if (sel.options.length <= 1) sel.innerHTML = buildOptions();\n    syncUnitSelect(sel.closest('tr'));\n    sel.addEventListener('change', function () { syncUnitSelect(this.closest('tr')); });\n  });";
$n4 = substr_count($s, $old4);
$s = str_replace($old4, $new4, $s);
echo "sync on change: $n4\n";

// 5. newly added rows: sync too
$old5 = "    tbody.appendChild(tr);\n    recalc();";
$new5 = "    tbody.appendChild(tr);\n    syncUnitSelect(tr);\n    tr.querySelector('[name*=\"[product_id]\"]').addEventListener('change', function () { syncUnitSelect(this.closest('tr')); });\n    recalc();";
$n5 = substr_count($s, $old5);
$s = str_replace($old5, $new5, $s);
echo "sync new rows: $n5\n";

file_put_contents($f, $s);
