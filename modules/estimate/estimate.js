/* [ftt_estimate] builder. Preview prices come from FTTCore.pricing.quote(); the server re-prices on submit.
 * DOM is built with createElement/textContent only. */
(function () {
  'use strict';
  var Core = window.FTTCore, CFG = window.FTT_EST || {}, T = CFG.table || {};
  var P = Core.pricing, cur = T.currency || 'USD';
  function money(c) { return P.formatMoney(c, cur); }
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    if (attrs) { Object.keys(attrs).forEach(function (k) { if (attrs[k] !== null && attrs[k] !== undefined) { n.setAttribute(k, attrs[k]); } }); }
    if (text != null) { n.textContent = text; }
    return n;
  }
  function clear(n) { while (n.firstChild) { n.removeChild(n.firstChild); } }
  function by(list, k, v) { return (list || []).filter(function (x) { return x[k] === v; }); }
  function servicesFor(fmt) { return (T.services || []).filter(function (s) { return (T.rates || []).some(function (r) { return r.format === fmt && r.service === s.id; }); }); }
  function addonsFor(fmt) { return (T.addons || []).filter(function (a) { return a.unit !== 'order' && (!a.formats || !a.formats.length || a.formats.indexOf(fmt) !== -1); }); }
  function unitTxt(a) { return a.unit === 'foot' ? '/ft' : a.unit === 'reel' ? '/reel' : ''; }
  var uid = 0;

  function init(root) {
    var q = function (k) { return root.querySelector('[data-e="' + k + '"]'); };
    var linesBox = q('lines'), orderBox = q('order'), summary = q('summary'), form = q('form'), msg = q('msg');
    var state = { lines: [], order: [] };
    function newLine(fmt) {
      fmt = fmt || ((T.formats || [])[0] || {}).id;
      var s = servicesFor(fmt)[0], r = by(T.reels, 'format', fmt);
      return { format: fmt, service: s ? s.id : '', size: r.length ? r[r.length > 1 ? 1 : 0].size : '', reels: 1, feet: '', addons: [] };
    }
    function request() {
      return { lines: state.lines.map(function (l) {
        var o = { format: l.format, service: l.service, reels: l.reels === '' ? '' : Number(l.reels), addons: l.addons.slice() };
        if (l.size) { o.size = l.size; }
        if (l.feet !== '' && l.feet != null) { o.feet = Number(l.feet); }
        return o;
      }), order_addons: state.order.slice() };
    }
    function select(opts, val, label, onchange) {
      var id = 'ftt-e' + (++uid), wrap = el('label', { 'class': 'ftt-field', 'for': id }), s = el('select', { id: id });
      wrap.appendChild(el('span', null, label));
      opts.forEach(function (o) { var op = el('option', { value: o[0] }, o[1]); if (String(o[0]) === String(val)) { op.selected = true; } s.appendChild(op); });
      s.addEventListener('change', function () { onchange(s.value); });
      wrap.appendChild(s); return wrap;
    }
    function input(type, val, label, attrs, onchange) {
      var id = 'ftt-e' + (++uid), wrap = el('label', { 'class': 'ftt-field', 'for': id }), a = attrs || {};
      a.id = id; a.type = type; var i = el('input', a); i.value = val;
      wrap.appendChild(el('span', null, label));
      i.addEventListener('input', function () { onchange(i.value); });
      wrap.appendChild(i); return wrap;
    }
    function drawLines(errors) {
      clear(linesBox);
      state.lines.forEach(function (l, idx) {
        var row = el('div', { 'class': 'ftt-line', 'data-line': idx });
        var head = el('div', { 'class': 'ftt-line__head' });
        head.appendChild(el('strong', null, 'Group ' + (idx + 1)));
        var price = el('span', { 'class': 'ftt-line__price', 'data-line-price': idx });
        head.appendChild(price);
        if (state.lines.length > 1) {
          var rm = el('button', { type: 'button', 'class': 'ftt-btn ftt-btn--link', 'aria-label': 'Remove group ' + (idx + 1) }, 'Remove');
          rm.addEventListener('click', function () { state.lines.splice(idx, 1); drawLines(); update(); });
          head.appendChild(rm);
        }
        row.appendChild(head);
        var g = el('div', { 'class': 'ftt-grid' });
        g.appendChild(select((T.formats || []).map(function (f) { return [f.id, f.label]; }), l.format, 'Film format', function (v) {
          var n = newLine(v); n.reels = l.reels; n.feet = l.feet; n.addons = l.addons.filter(function (a) { return addonsFor(v).some(function (x) { return x.id === a; }); });
          if (servicesFor(v).some(function (s) { return s.id === l.service; })) { n.service = l.service; }
          state.lines[idx] = n; drawLines(); update();
        }));
        g.appendChild(select(servicesFor(l.format).map(function (s) { return [s.id, s.label]; }), l.service, 'Service', function (v) { l.service = v; update(); }));
        g.appendChild(select(by(T.reels, 'format', l.format).map(function (r) { return [r.size, r.label + ' (' + r.feet + ' ft)']; }).concat([['', 'Not sure / I know the footage']]), l.size, 'Reel size', function (v) { l.size = v; update(); }));
        g.appendChild(input('number', l.reels, 'Number of reels', { min: 1, max: 999, step: 1, inputmode: 'numeric' }, function (v) { l.reels = v; update(); }));
        g.appendChild(input('number', l.feet, 'Total feet (if known)', { min: 0, step: 'any', inputmode: 'decimal', placeholder: 'optional' }, function (v) { l.feet = v; update(); }));
        row.appendChild(g);
        var adds = addonsFor(l.format);
        if (adds.length) {
          var fs = el('div', { 'class': 'ftt-addons' });
          adds.forEach(function (a) {
            var lab = el('label', { 'class': 'ftt-check' }), cb = el('input', { type: 'checkbox', value: a.id });
            cb.checked = l.addons.indexOf(a.id) !== -1;
            cb.addEventListener('change', function () { var i = l.addons.indexOf(a.id); if (cb.checked && i === -1) { l.addons.push(a.id); } if (!cb.checked && i !== -1) { l.addons.splice(i, 1); } update(); });
            lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + a.label + ' (' + P.formatMills(a.price, cur) + unitTxt(a) + ')'));
            fs.appendChild(lab);
          });
          row.appendChild(fs);
        }
        row.appendChild(el('p', { 'class': 'ftt-line__err', 'data-line-err': idx, role: 'alert' }));
        linesBox.appendChild(row);
      });
      if (errors) { showErrors(errors); }
    }
    function drawOrder() {
      clear(orderBox);
      var oa = (T.addons || []).filter(function (a) { return a.unit === 'order'; });
      q('order-wrap').hidden = !oa.length;
      oa.forEach(function (a) {
        var lab = el('label', { 'class': 'ftt-check' }), cb = el('input', { type: 'checkbox', value: a.id });
        cb.addEventListener('change', function () { var i = state.order.indexOf(a.id); if (cb.checked && i === -1) { state.order.push(a.id); } if (!cb.checked && i !== -1) { state.order.splice(i, 1); } update(); });
        lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + a.label + ' (' + P.formatMills(a.price, cur) + ')'));
        orderBox.appendChild(lab);
      });
    }
    function showErrors(errors) {
      Array.prototype.forEach.call(root.querySelectorAll('[data-line-err]'), function (n) { n.textContent = ''; });
      (errors || []).forEach(function (e) { var n = root.querySelector('[data-line-err="' + e.line + '"]'); if (n) { n.textContent = e.message; } });
    }
    var last = null;
    function update() {
      var res = P.quote(T, request()); last = res;
      /* Share the reel list with other toolkit widgets on the page (storage calculator). */
      window.FTTEstimateLines = request().lines;
      try { document.dispatchEvent(new CustomEvent('ftt:estimate-lines', { detail: window.FTTEstimateLines })); } catch (e) { /* old browsers: event not shared */ }
      showErrors(res.errors);
      Array.prototype.forEach.call(root.querySelectorAll('[data-line-price]'), function (n) { n.textContent = ''; });
      res.lines.forEach(function (l) {
        var n = root.querySelector('[data-line-price="' + l.index + '"]');
        if (n) { n.textContent = Math.round(l.billable_feet).toLocaleString() + ' ft billed \u00b7 ~' + Core.formatTime(l.seconds) + ' \u00b7 ' + money(l.line_cents); }
      });
      clear(summary);
      var t = res.totals, tb = el('table', { 'class': 'ftt-table ftt-est__totals' }), body = el('tbody');
      function row(label, val, cls) { var tr = el('tr', cls ? { 'class': cls } : null); tr.appendChild(el('th', { scope: 'row' }, label)); tr.appendChild(el('td', null, val)); body.appendChild(tr); }
      row('Reels and add-ons (' + Math.round(t.billable_feet).toLocaleString() + ' ft billed, about ' + Core.formatTime(t.seconds) + ')', money(t.lines_cents));
      if (t.discount_cents) { row('Volume discount ' + t.discount_pct + '%', '\u2212' + money(t.discount_cents)); }
      t.order_addons.forEach(function (a) { row(a.label, money(a.cents)); });
      if (t.setup_cents) { row('Setup fee', money(t.setup_cents)); }
      if (t.minimum_adjust_cents) { row('Order minimum (' + money(t.minimum_cents) + ') adjustment', money(t.minimum_adjust_cents)); }
      row('Estimated total', money(t.total_cents), 'ftt-est__grand');
      tb.appendChild(body); summary.appendChild(tb);
      if (T.status !== 'final') { summary.appendChild(el('p', { 'class': 'ftt-note' }, (T.label || '') + ' Estimate only.')); }
      q('send').disabled = !res.ok;
    }
    q('add').addEventListener('click', function () { var prev = state.lines[state.lines.length - 1]; state.lines.push(newLine(prev && prev.format)); drawLines(); update(); });

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var f = form.elements, btn = q('send');
      if (!last || !last.ok) { msg.textContent = 'Please fix the estimate lines first.'; return; }
      if (!f.name.value.trim()) { msg.textContent = 'Please enter your name.'; f.name.focus(); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) { msg.textContent = 'Please enter a valid email address.'; f.email.focus(); return; }
      if (!f.consent.checked) { msg.textContent = 'Please tick the box to confirm you understand this is an estimate.'; return; }
      btn.disabled = true; msg.textContent = 'Sending\u2026';
      var payload = { request: request(), client_total_cents: last.totals.total_cents, consent: 1, website: f.website.value,
        contact: { name: f.name.value, email: f.email.value, phone: f.phone.value, notes: f.notes.value } };
      fetch(CFG.rest + '/token', { credentials: 'omit', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (tk) {
        return fetch(CFG.rest + '/request', { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', 'X-FTT-Nonce': tk.nonce }, body: JSON.stringify(payload) });
      }).then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); }).then(function (res) {
        btn.disabled = false;
        if (res.status === 201 && res.j.ok) {
          form.reset();
          msg.textContent = 'Thank you. Your request ' + res.j.reference + ' was received. Estimated total ' + res.j.total + (res.j.repriced ? ' (updated by our server)' : '') + '. We will reply by email.';
          form.setAttribute('data-sent', res.j.reference);
        } else {
          msg.textContent = (res.j && res.j.message) || 'Something went wrong. Please try again.';
          if (res.j && res.j.data && res.j.data.errors) { showErrors(res.j.data.errors); }
        }
      }).catch(function () { btn.disabled = false; msg.textContent = 'Could not reach the server. Please try again.'; });
    });

    state.lines.push(newLine());
    drawLines(); drawOrder(); update();
  }
  function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-ftt-estimate]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
