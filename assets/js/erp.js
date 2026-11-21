/* JUSTI ERP â€” line item grids for documents (sales/purchase/BOM)
   Rows use an explicit numeric key (items[qty][0], items[qty][1] â€¦) so optional
   checkboxes (bonus goods) stay aligned with their quantities. */
'use strict';

function erpLineGrid(cfg) {
  var tbody = document.querySelector(cfg.tbody);
  if (!tbody) return;
  var products = cfg.products || [];
  var rowKey = tbody.querySelectorAll('tr').length; // existing server rows take 0..n-1

      function buildOptions() {
    var html = '<option value="">— select product —</option>';
    var esc2 = function (s) { var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; };
    products.forEach(function (p) {
      var stockTxt = (p.stock !== undefined && p.stock !== null) ? ' · Stock: ' + p.stock : '';
      html += '<option value="' + p.id + '" data-price="' + p.sale_price + '" data-cost="' + p.purchase_price + '" data-tax="' + p.tax_rate + '" data-unit="' + (p.unit_short || '') + '" data-kind="' + (p.kind || '') + '" data-stock="' + (p.stock || 0) + '">'
        + esc2(p.sku) + ' — ' + esc2(p.name) + esc2(stockTxt) + '</option>';
    });
    return html;
  }
  // Per-row unit select for raw materials & packaging (KG/TON/MAN); FG products are locked to their unit.
  function unitSelectHtml(k) {
    return '<td><select class="f item-unit" style="width:74px" name="' + (cfg.field || 'items') + '[unit][' + k + ']">'
      + '<option value="KG">KG</option><option value="TON">TON</option><option value="MAN">MAN</option>'
      + '</select></td>';
  }
  function syncUnitSelect(tr) {
    if (!cfg.unit) return;
    var sel = tr.querySelector('[name*="[product_id]"]');
    var unitSel = tr.querySelector('.item-unit');
    if (!sel || !unitSel) return;
    var kind = sel.selectedOptions[0] ? sel.selectedOptions[0].getAttribute('data-kind') : '';
    if (kind === 'raw' || kind === 'packaging') {
      var prev = unitSel.value;
      unitSel.innerHTML = '<option value="KG">KG</option><option value="TON">TON</option><option value="MAN">MAN</option>';
      unitSel.value = (prev === 'KG' || prev === 'TON' || prev === 'MAN') ? prev : 'KG';
      unitSel.disabled = false;
    } else {
      var unit = sel.selectedOptions[0] ? (sel.selectedOptions[0].getAttribute('data-unit') || '') : '';
      unitSel.innerHTML = '<option value="' + unit + '">' + unit + '</option>';
      unitSel.value = unit || 'KG';
      unitSel.disabled = true;
    }
  }
  // Inject options into server-rendered rows that have none
  tbody.querySelectorAll('select[name*="[product_id]"]').forEach(function (sel) {
    if (sel.options.length <= 1) sel.innerHTML = buildOptions();
    syncUnitSelect(sel.closest('tr'));
    sel.addEventListener('change', function () { syncUnitSelect(this.closest('tr')); });
  });

  function recalc() {
    var subtotal = 0, tax = 0;
    tbody.querySelectorAll('tr').forEach(function (tr) {
      var qty = parseFloat(tr.querySelector('[name*="[qty]"]')?.value || 0);
      var price = parseFloat(tr.querySelector('[name*="[price]"]')?.value || tr.querySelector('[name*="[cost]"]')?.value || 0);
      var disc = parseFloat(tr.querySelector('[name*="[discount]"]')?.value || 0);
      var taxRate = parseFloat(tr.querySelector('[name*="[tax_rate]"]')?.value || 0);
      var bonus = tr.querySelector('[name*="[is_bonus]"]');
      var isBonus = bonus && bonus.checked;
      var net = Math.max(0, qty * price - disc);
      var lineTax = isBonus ? 0 : net * taxRate / 100;
      var lineTotal = net + lineTax;
      var cell = tr.querySelector('.line-total');
      if (cell) cell.textContent = lineTotal.toFixed(2);
      if (!isBonus) { subtotal += net; tax += lineTax; }
    });
    if (cfg.subtotalEl) document.querySelector(cfg.subtotalEl).textContent = subtotal.toFixed(2);
    if (cfg.taxEl) document.querySelector(cfg.taxEl).textContent = tax.toFixed(2);
    var discTotal = cfg.discountEl ? parseFloat(document.querySelector(cfg.discountEl)?.value || 0) : 0;
    if (cfg.totalEl) document.querySelector(cfg.totalEl).textContent = (subtotal - discTotal + tax).toFixed(2);
  }

  tbody.addEventListener('input', function (e) {
    var tr = e.target.closest('tr');
    if (e.target.matches('select[name*="[product_id]"]')) {
      var opt = e.target.selectedOptions[0];
      if (opt && opt.value) {
        var priceInput = tr.querySelector('[name*="[price]"]') || tr.querySelector('[name*="[cost]"]');
        var taxInput = tr.querySelector('[name*="[tax_rate]"]');
        if (priceInput) priceInput.value = cfg.priceFrom === 'cost' ? opt.dataset.cost : opt.dataset.price;
        if (taxInput) taxInput.value = opt.dataset.tax;
      }
    }
    recalc();
  });

  var addBtn = document.querySelector(cfg.addBtn);
  if (addBtn) addBtn.addEventListener('click', function () {
    var k = rowKey++;
    var name = cfg.field;
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><select class="f" name="' + name + '[product_id][' + k + ']" required>' + buildOptions() + '</select></td>' +
      '<td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="' + name + '[qty][' + k + ']" value="1" required></td>' +
      (cfg.unit ? unitSelectHtml(k) : '') +
      '<td><input class="f" style="width:100px" type="number" step="0.01" min="0" name="' + name + '[' + (cfg.priceFrom === 'cost' ? 'cost' : 'price') + '][' + k + ']" value="0.00" required></td>' +
      '<td><input class="f" style="width:90px" type="number" step="0.01" min="0" name="' + name + '[discount][' + k + ']" value="0.00"></td>' +
      '<td><input class="f" style="width:70px" type="number" step="0.01" min="0" max="100" name="' + name + '[tax_rate][' + k + ']" value="0.00"></td>' +
      (cfg.allowBonus ? '<td style="text-align:center"><input type="checkbox" name="' + name + '[is_bonus][' + k + ']" value="1" title="Bonus/free goods"></td>' : '') +
      '<td class="line-total num">0.00</td>' +
      '<td><button type="button" class="btn btn-danger btn-sm" data-remove>âœ•</button></td>';
    tbody.appendChild(tr);
    syncUnitSelect(tr);
    tr.querySelector('[name*="[product_id]"]').addEventListener('change', function () { syncUnitSelect(this.closest('tr')); });
    recalc();
  });

  tbody.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-remove]');
    if (btn && tbody.querySelectorAll('tr').length > 1) { btn.closest('tr').remove(); recalc(); }
  });

  if (cfg.discountEl) document.querySelector(cfg.discountEl)?.addEventListener('input', recalc);
  recalc();
}

/* ============ In-website confirm modal + toast (replaces native confirm/alert) ============ */
(function () {
  var modal = document.getElementById('erpConfirmModal');
  var ui = window.ERP_UI || {};
  var wrap = document.getElementById('erpToastWrap');

  /* ---- Toast notifications (in-website, no browser popups) ---- */
  window.erpNotify = function (msg, type) {
    type = type === 'success' || type === 'error' ? type : 'info';
    if (!wrap) { return; }
    var el = document.createElement('div');
    el.className = 'erp-toast ' + type;
    el.textContent = msg;
    wrap.appendChild(el);
    window.setTimeout(function () {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      window.setTimeout(function () { el.remove(); }, 260);
    }, 4200);
  };

  if (!modal) return;

  var titleEl = document.getElementById('erpConfirmTitle');
  var msgEl = document.getElementById('erpConfirmMsg');
  var subEl = document.getElementById('erpConfirmSub');
  var docEl = document.getElementById('erpConfirmDoc');
  var errEl = document.getElementById('erpConfirmError');
  var iconEl = document.getElementById('erpConfirmIcon');
  var okBtn = document.getElementById('erpConfirmOk');
  var cancelBtn = document.getElementById('erpConfirmCancel');

  var resolver = null;
  var requireNoteSel = null;
  var busy = false;
  var lastFocus = null;
  var okDefaultHtml = '';

  function renderDoc(rows) {
    if (!rows || !rows.length) { docEl.hidden = true; docEl.innerHTML = ''; return; }
    docEl.innerHTML = rows.map(function (r) {
      return '<div class="doc-row-m"><dt></dt><dd></dd></div>';
    }).join('');
    var nodes = docEl.querySelectorAll('.doc-row-m');
    rows.forEach(function (r, i) {
      nodes[i].querySelector('dt').textContent = r.label;
      nodes[i].querySelector('dd').textContent = r.value;
    });
    docEl.hidden = false;
  }

  function escClose(e) { if (e.key === 'Escape' && !busy) close(false); }

  function open(opts) {
    return new Promise(function (resolve) {
      resolver = resolve;
      busy = false;
      requireNoteSel = opts.requireNote || null;
      lastFocus = document.activeElement;
      titleEl.textContent = opts.title || ui.pleaseConfirm || 'Please Confirm';
      msgEl.textContent = opts.message || '';
      if (opts.sub) { subEl.textContent = opts.sub; subEl.hidden = false; } else { subEl.hidden = true; }
      renderDoc(opts.doc);
      errEl.hidden = true;
      iconEl.className = 'erp-modal-icon' + (opts.variant === 'danger' ? ' danger' : opts.variant === 'approve' ? ' approve' : '');
      okBtn.className = 'btn ' + (opts.variant === 'danger' ? 'btn-danger' : 'btn-primary');
      okBtn.disabled = false;
      cancelBtn.disabled = false;
      okDefaultHtml = '<span class="erp-ok-label"></span>';
      okBtn.innerHTML = '<span class="erp-ok-label"></span>';
      okBtn.querySelector('.erp-ok-label').textContent = opts.okLabel || ui.confirm || 'Confirm';
      cancelBtn.textContent = ui.cancel || 'Cancel';
      modal.hidden = false;
      // force reflow so the transition runs
      void modal.offsetWidth;
      modal.classList.add('open');
      document.addEventListener('keydown', escClose);
      window.setTimeout(function () { okBtn.focus(); }, 60);
    });
  }

  function close(result) {
    modal.classList.remove('open');
    document.removeEventListener('keydown', escClose);
    var done = function () {
      modal.hidden = true;
      if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
      var r = resolver; resolver = null;
      if (r) r(result);
    };
    window.setTimeout(done, 160);
  }

  function setBusy() {
    busy = true;
    okBtn.disabled = true;
    cancelBtn.disabled = true;
    okBtn.innerHTML = '<span class="erp-spinner" aria-hidden="true"></span> ' + (ui.processing || 'Processing…');
  }

  okBtn.addEventListener('click', function () {
    if (busy) return; // double-click / duplicate approval guard
    if (requireNoteSel) {
      var noteEl = document.querySelector(requireNoteSel);
      if (!noteEl || !noteEl.value.trim()) {
        errEl.textContent = ui.noteRequired || 'A rejection reason is required.';
        errEl.hidden = false;
        if (noteEl && noteEl.focus) noteEl.focus();
        return;
      }
    }
    errEl.hidden = true;
    setBusy();
    close(true);
  });
  cancelBtn.addEventListener('click', function () { if (!busy) close(false); });
  modal.addEventListener('click', function (e) { if (e.target === modal && !busy) close(false); });

  /** Programmatic confirm, e.g. erpConfirm({title, message, ...}).then(ok => …) */
  window.erpConfirm = open;

  /* Track the last clicked submit button (covers browsers where e.submitter is unreliable) */
  var lastSubmitClick = null;
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('button[type="submit"], input[type="submit"], button:not([type]), input[type="image"]') : null;
    if (btn) lastSubmitClick = btn;
  }, true);

  /* Forms/buttons opting in via data-confirm (+ optional -title/-ok/-variant/-sub/-doc/-require-note).
     Attributes on the clicked submit button take precedence over those on the form. */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var submitter = e.submitter || null;
    if (!submitter && lastSubmitClick && lastSubmitClick.form === form) submitter = lastSubmitClick;
    if (!submitter && document.activeElement && document.activeElement.form === form && /^(submit|image)$/.test(document.activeElement.type || '')) submitter = document.activeElement;
    var src = (submitter && submitter.hasAttribute('data-confirm')) ? submitter
            : (form.hasAttribute('data-confirm') ? form : null);
    if (!src) {
      /* Implicit submission (e.g. Enter in a text field) activates the first submit button */
      src = form.querySelector('button[data-confirm], input[data-confirm]');
    }
    if (!src) return;
    if (form.dataset.erpConfirmed === '1') { form.dataset.erpConfirmed = ''; return; } // already confirmed → let it submit
    e.preventDefault();
    var doc = null;
    try { doc = src.dataset.confirmDoc ? JSON.parse(src.dataset.confirmDoc) : null; } catch (err) { doc = null; }
    open({
      title: src.dataset.confirmTitle || ui.pleaseConfirm || 'Please Confirm',
      message: src.getAttribute('data-confirm'),
      sub: src.dataset.confirmSub || null,
      okLabel: src.dataset.confirmOk || ui.confirm || 'Confirm',
      variant: src.dataset.confirmVariant || 'warning',
      doc: doc,
      requireNote: src.dataset.confirmRequireNote || null
    }).then(function (ok) {
      if (!ok) return; // Cancel → nothing changes
      form.dataset.erpConfirmed = '1';
      var go = (submitter && submitter.form === form) ? submitter : (src !== form ? src : null);
      if (go && go.click) { go.click(); } else { form.submit(); }
    });
  });
})();


/* ============ Notifications: bell badge + dropdown + desktop popup + chime ============ */
(function () {
  function start() {
  var wrap = document.getElementById('notifWrap');
  if (!wrap || !window.ERP_NOTIF) return;
  var bell = document.getElementById('notifBell');
  var panel = document.getElementById('notifPanel');
  var badge = document.getElementById('notifBadge');
  var list = document.getElementById('notifList');
  var markAll = document.getElementById('notifMarkAll');
  var soundOn = wrap.dataset.sound === '1';
  var desktopOn = wrap.dataset.desktop === '1';
  var unread = badge && !badge.hidden ? (parseInt(badge.textContent || '0', 10) || 0) : 0;
  // Known IDs persisted per tab across page navigations so page loads / tab switches never re-trigger the chime.
  var KNOWN_KEY = 'erp:notif:known';
  var knownIds = {};
  try {
    var stored = window.sessionStorage && window.sessionStorage.getItem(KNOWN_KEY);
    if (stored) { var arr = JSON.parse(stored); if (Array.isArray(arr)) arr.forEach(function (id) { knownIds[id] = 1; }); }
  } catch (e) { knownIds = {}; }
  // First-load baseline: notifications already unread when this page rendered are known — they never beep.
  try {
    var initial = (window.ERP_NOTIF && Array.isArray(window.ERP_NOTIF.initialUnreadIds)) ? window.ERP_NOTIF.initialUnreadIds : [];
    initial.forEach(function (id) { knownIds[id] = 1; });
    if (window.sessionStorage) window.sessionStorage.setItem(KNOWN_KEY, JSON.stringify(Object.keys(knownIds).map(Number)));
  } catch (e) {}

  // Resume audio after first user gesture (browser autoplay policy)
  var audioCtx = null;
  function ctx() {
    if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {} }
    if (audioCtx && audioCtx.state === 'suspended') { try { audioCtx.resume(); } catch (e) {} }
    return audioCtx;
  }
  document.addEventListener('pointerdown', function () { ctx(); }, { once: true });

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function beep() {
    if (!soundOn) return;
    var c = ctx(); if (!c) return;
    try {
      [880, 1174].forEach(function (f, i) {
        var o = c.createOscillator(), g = c.createGain();
        o.type = 'sine'; o.frequency.value = f;
        o.connect(g); g.connect(c.destination);
        var t = c.currentTime + i * 0.18;
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.22, t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.16);
        o.start(t); o.stop(t + 0.17);
      });
    } catch (e) {}
  }

  function renderList(items) {
    if (!list) return;
    if (!items.length) { list.innerHTML = '<div class="notif-empty">' + esc(window.ERP_NOTIF.empty) + '</div>'; return; }
    list.innerHTML = items.map(function (it) {
      return '<a class="notif-item' + (it.is_read ? '' : ' unread') + '" href="' + esc(it.link || '#') + '" data-id="' + it.id + '">'
        + '<span class="n-type">' + esc(it.type) + '</span>'
        + '<div class="n-title">' + esc(it.title) + '</div>'
        + (it.body ? '<div class="n-body">' + esc(it.body) + '</div>' : '')
        + '<div class="n-when">' + esc(it.created_at) + '</div>'
        + '</a>';
    }).join('');
  }

  function updateBadge(count) {
    unread = count;
    if (!badge) return;
    if (unread > 0) { badge.textContent = unread; badge.removeAttribute('hidden'); } else { badge.setAttribute('hidden', 'hidden'); }
  }

  function saveKnown() {
    try { if (window.sessionStorage) window.sessionStorage.setItem(KNOWN_KEY, JSON.stringify(Object.keys(knownIds).map(Number))); } catch (e) {}
  }

  async function poll() {
    try {
      var res = await fetch(window.ERP_NOTIF.endpoint, { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      var items = data.items || [];
      var fresh = items.filter(function (it) { return !knownIds[it.id]; });
      items.forEach(function (it) { knownIds[it.id] = 1; });
      saveKnown();
      updateBadge(data.unread || 0);
      if (fresh.length && !document.hidden) {
        beep();
        if (desktopOn && 'Notification' in window) {
          if (Notification.permission === 'granted') {
            fresh.forEach(function (it) {
              try {
                var n = new Notification(it.title, { body: it.body || '', tag: 'erp-' + it.id });
                n.onclick = function () { window.focus(); if (it.link) { window.location.href = it.link; } n.close(); };
              } catch (e) {}
            });
          } else if (Notification.permission === 'default') {
            try { Notification.requestPermission(); } catch (e) {}
          }
        }
      }
      if (panel && !panel.hidden) renderList(items);
    } catch (e) { /* offline â€” ignore */ }
  }

  if (bell) bell.addEventListener('click', function () {
    if (!panel) return;
    panel.hidden = !panel.hidden;
    bell.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) poll();
  });
  if (panel) document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) { panel.hidden = true; if (bell) bell.setAttribute('aria-expanded', 'false'); }
  });
  if (markAll) markAll.addEventListener('click', async function () {
    try {
      await fetch(window.ERP_NOTIF.mark, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=all-read&csrf=' + encodeURIComponent(window.ERP_NOTIF.csrf) });
      updateBadge(0);
      if (list) list.querySelectorAll('.notif-item').forEach(function (el) { el.classList.remove('unread'); });
    } catch (e) {}
  });
  if (list) list.addEventListener('click', function (e) {
    var item = e.target.closest('.notif-item'); if (!item) return;
    var id = parseInt(item.dataset.id || '0', 10); if (!id) return;
    item.classList.remove('unread');
    updateBadge(Math.max(0, unread - 1));
    fetch(window.ERP_NOTIF.mark, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + id + '&action=read&csrf=' + encodeURIComponent(window.ERP_NOTIF.csrf) }).catch(function () {});
  });

  setInterval(poll, 15000);
  setTimeout(poll, 400);
  } // end start()
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();


/* Profile photo preview-before-save (employee / labour / salesman forms).
   Usage: <input id= photoInput ...> plus <img id= photoPreview style=display:none>
   plus a fallback avatar <div id= photoAvatar>. */
function initPhotoPreview(inputId, imgId, avatarId) {
  var inp = document.getElementById(inputId || 'photoInput');
  var img = document.getElementById(imgId || 'photoPreview');
  var avatar = document.getElementById(avatarId || 'photoAvatar');
  if (!inp) return;
  inp.addEventListener('change', function () {
    var f = inp.files && inp.files[0];
    if (!f) return;
    var okExt = /\.(jpe?g|png|webp)$/i.test(f.name || '');
    if (!/^image\/(jpeg|png|webp)$/.test(f.type || '') && !okExt) {
      window.erpNotify ? window.erpNotify('Only JPG, JPEG, PNG or WebP images are allowed.', 'error') : null;
      inp.value = '';
      return;
    }
    if (f.size > 2 * 1024 * 1024) {
      window.erpNotify ? window.erpNotify('Photo must be smaller than 2 MB.', 'error') : null;
      inp.value = '';
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      if (img) { img.src = e.target.result; img.style.display = 'block'; }
      if (avatar) avatar.style.display = 'none';
    };
    reader.readAsDataURL(f);
  });
}