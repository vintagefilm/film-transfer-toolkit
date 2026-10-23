/* FTT clip selector (ported from FW Clip Cart 0.4.0). Reads the playhead via FTT_PLAYERS adapters
   (HTML5 video, YouTube, Vimeo, FluentPlayer/Vidstack, FV Player). data-mode="cart" adds clips to the FluentCart cart;
   data-mode="request" (no FluentCart or no clip product) sends the list as a clip request instead. */
(function () {
  'use strict';
  var C = window.FTT_CLIP || {};
  var TP = C.tips || { edit: 'Edit this clip name', remove: 'Remove this clip', rowname: 'Clip name' };
  function money(c) { return '$' + (c / 100).toFixed(2); }
  function estimate(dur) {
    // Same rule as FTT_Clip_Rate_Card::billable_seconds(): millisecond first, then the card's rounding mode.
    var k = C.card || {}; var ms = Math.round(dur * 1000) / 1000;
    var w = k.rounding === 'down' ? Math.floor(ms) : (k.rounding === 'nearest' ? Math.round(ms) : Math.ceil(ms));
    var b = Math.max(+k.min_seconds || 0, w);
    return Math.max(+k.min_charge_cents || 0, b * (+k.per_second_cents || 0));
  }
  // 1.5.9: header mini-cart refresh. FluentCart renders the header cart on the server, so after our REST add (or on a page
  // served from a page cache) it can still show $0.00. Update the amount and badges from the live /minicart numbers and
  // tell FluentCart the cart changed.
  function applyMini(m) {
    if (!m || typeof m !== 'object') return;
    document.querySelectorAll('[data-fluent-cart-cart-total-price]').forEach(function (n) { if (m.subtotal) { n.textContent = m.subtotal; n.setAttribute('aria-label', m.subtotal); } });
    document.querySelectorAll('[data-cart-badge-count]').forEach(function (n) {
      var v = n.getAttribute('data-cart-count-mode') === 'total_quantity' ? m.qty : m.count; n.textContent = v > 0 ? String(v) : '';
    });
    document.querySelectorAll('.fct-cart-badge-count, [data-fluent-cart-cart-total-item]').forEach(function (n) { n.textContent = String(m.count || 0); });
    if (window.fluentcart_drawer_vars) { window.fluentcart_drawer_vars.cart_item_count = m.count; window.fluentcart_drawer_vars.cart_total_quantity = m.qty; }
  }
  function refreshMini(m) {
    if (m) applyMini(m);
    try { window.dispatchEvent(new Event('fluentCartNotifyCartDrawerItemChanged')); } catch (e) {}
    try { if (window.fluentCartCart && typeof window.fluentCartCart.getCart === 'function') window.fluentCartCart.getCart().then(function () { if (m) applyMini(m); }, function () {}); } catch (e) {}
  }
  function syncMiniOnLoad() {
    if (!C.rest || !document.querySelector('[data-fluent-cart-cart-total-price], [data-cart-badge-count]')) return;
    if (!/(^|;\s*)fct_cart_hash=[^;]+/.test(document.cookie)) return;
    fetch(C.rest + '/minicart', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; }).then(function (m) { if (m && m.count) applyMini(m); }, function () {});
  }
  var TC = window.FTTCore.timecode;
  function findPlayer(el) {
    return window.FTT_PLAYERS.find(el, el.getAttribute('data-player') || 'auto', el.getAttribute('data-player-selector') || '');
  }
  function init(el) {
    var fps = TC.norm(el.getAttribute('data-fps') || '29.97df');   // rate key, e.g. '29.97df'
    var R = TC.info(fps), OFF = parseInt(el.getAttribute('data-offset') || '0', 10) || 0;
    // In/Out are stored as integer frame numbers on the preview timeline.
    var st = { in: null, out: null, list: [], seq: 1 };
    function tc(f) { return TC.framesToTC(f + OFF, fps); }
    function secOf(f) { return TC.framesToSeconds(f, fps); }
    function seekTo(p, f) { p.currentTime = TC.framesToSeconds(f + 0.5, fps); } // mid-frame so the player lands on frame f
    var clipId = el.getAttribute('data-clip'), title = el.getAttribute('data-title') || clipId;
    var DEMO = el.getAttribute('data-demo') === '1';
    function listText() {
      var lines = [title + ' (' + clipId + '), timecode ' + R.label + ' fps'];
      st.list.forEach(function (c) { lines.push(clipId + '-' + pad(c.seq) + '  ' + c.name + '  IN ' + tc(c.in) + '  OUT ' + tc(c.out) + '  ' + secOf(c.out - c.in).toFixed(3) + 's  est. ' + money(estimate(secOf(c.out - c.in)))); });
      lines.push(location.href.split('#')[0]);
      return lines.join('\n');
    }
    function fallbackCopy(t) { var ta = document.createElement('textarea'); ta.value = t; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(ta); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function defName(seq) { return title + ' ' + pad(seq); }
    function mk(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
    var $ = function (s) { return el.querySelector(s); };
    var msg = function (t, bad) { var m = $('.fwcc-msg'); m.textContent = t; m.className = 'fwcc-msg' + (bad ? ' bad' : ''); };
    // ---- 1.5.5: sign-in before add to cart ----
    var I18 = C.i18n || {}, LSK = 'ftt_clip_pending';
    C.loggedIn = +C.loggedIn === 1; // 1.5.8: wp_localize_script sends "0" (a truthy string)
    function msgLinks(t, links, bad) {
      msg(t, bad); var m = $('.fwcc-msg');
      links.forEach(function (l) {
        if (!l[1]) return;
        var a = document.createElement('a'); a.href = l[1]; a.textContent = l[0]; a.className = 'button fwcc-auth ' + (l[2] || ''); a.style.marginLeft = '8px';
        m.appendChild(document.createTextNode(' ')); m.appendChild(a);
      });
    }
    function toLogin(b) {
      if (!st.list.length) return msg('Clip list is empty.', true);
      b.disabled = true; msg('Saving your selection...');
      var clips = st.list.map(function (c) {
        return { product_id: +el.getAttribute('data-product'), clip_id: clipId, seq: c.seq, name: c.name, in: secOf(c.in), out: secOf(c.out), fps: fps, client_price_cents: estimate(secOf(c.out - c.in)) };
      });
      fetch(C.rest + '/pending', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
        body: JSON.stringify({ clips: clips, return: location.href })
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (r) {
          b.disabled = false;
          if (!r.ok) return msg((r.j && r.j.message) || 'Could not save your selection.', true);
          try { localStorage.setItem(LSK, JSON.stringify({ token: r.j.token, exp: r.j.expires * 1000 })); } catch (e) {}
          el.setAttribute('data-pending', r.j.token);
          msgLinks(I18.login_needed || 'Please sign in or create an account to add these clips to your cart.',
            [[I18.signin || 'Sign in', r.j.login_url, 'fwcc-signin'], [I18.register || 'Create account', r.j.register_url, 'fwcc-register']]);
        })
        .catch(function (e) { b.disabled = false; msg(String(e), true); });
    }
    // Backup resume: signed in now, and this browser still holds an unclaimed token (e.g. a form that ignored redirect_to).
    (function resumeFromStorage() {
      var p; try { p = JSON.parse(localStorage.getItem(LSK) || 'null'); } catch (e) { p = null; }
      if (!p || !p.token) return;
      if (p.exp && p.exp < Date.now()) { try { localStorage.removeItem(LSK); } catch (e) {} return; }
      if (!C.loggedIn || window.__fttClipResuming) return;
      window.__fttClipResuming = true;
      try { localStorage.removeItem(LSK); } catch (e) {}
      fetch(C.rest + '/resume', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify({ token: p.token }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (r) {
          if (!r.ok || !r.j.added || !r.j.added.length) return; // already claimed on the server, or expired.
          if (r.j.after_login === 'cart' && r.j.cart_url) { location.href = r.j.cart_url; return; }
          msgLinks((I18.resumed || '%d clip(s) added to your cart.').replace('%d', r.j.added.length), [[I18.viewcart || 'View cart', r.j.cart_url, 'fwcc-viewcart']]);
          document.dispatchEvent(new CustomEvent('fwcc:added'));
        }).catch(function () {});
    })();
    // Server-side resume already happened: forget the local copy.
    if (C.loggedIn && document.querySelector('.ftt-clip-resume-notice')) { try { localStorage.removeItem(LSK); } catch (e) {} }
    var player = function () { return findPlayer(el); };
    function now() { var p = player(); return p ? TC.secondsToFrames(Number(p.currentTime) || 0, fps) : null; }
    function dur() { return (st.in !== null && st.out !== null && st.out > st.in) ? secOf(st.out - st.in) : 0; }
    var blank = R.drop ? '--:--:--;--' : '--:--:--:--';
    // 1.5.9: price each listed clip on the server (/quote) so the list shows exactly what the cart will charge.
    function quoteRow(row) {
      if (!C.rest || !C.nonce || el.getAttribute('data-mode') === 'request' || DEMO) return;
      fetch(C.rest + '/quote', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
        body: JSON.stringify({ product_id: +el.getAttribute('data-product'), clip_id: clipId, seq: row.seq, name: row.name, in: secOf(row.in), out: secOf(row.out), fps: fps }) })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (j && j.clip && j.clip.price_cents != null && st.list.indexOf(row) > -1) { row.price = +j.clip.price_cents; draw(); } }, function () {});
    }
    function draw() {
      el.querySelector('[data-tc=in]').textContent = st.in === null ? blank : tc(st.in);
      el.querySelector('[data-tc=out]').textContent = st.out === null ? blank : tc(st.out);
      el.querySelectorAll('.fwcc-jump').forEach(function (j) { j.setAttribute('aria-disabled', st[j.getAttribute('data-which')] === null ? 'true' : 'false'); });
      $('.fwcc-dur').textContent = dur().toFixed(3) + 's';
      $('.fwcc-est').textContent = dur() ? money(estimate(dur())) : (I18.from || 'from %s').replace('%s', money(estimate(0)));
      $('.fwcc-nextlabel').textContent = clipId + '-' + pad(st.seq);
      var nm = $('.fwcc-name'); nm.placeholder = defName(st.seq); if (!nm.dataset.touched) nm.value = defName(st.seq);
      var tb = $('.fwcc-list tbody'); while (tb.firstChild) tb.removeChild(tb.firstChild); var tot = 0;
      st.list.forEach(function (c, i) {
        var e = c.price != null ? c.price : estimate(secOf(c.out - c.in)); tot += e;
        var tr = document.createElement('tr');
        tr.appendChild(mk('td', 'mono', clipId + '-' + pad(c.seq)));
        var tdn = mk('td'), inp = document.createElement('input');
        inp.type = 'text'; inp.maxLength = 120; inp.setAttribute('data-nm', i); inp.title = TP.rowname; inp.setAttribute('aria-label', TP.rowname); inp.value = c.name;
        tdn.appendChild(inp); tr.appendChild(tdn);
        tr.appendChild(mk('td', 'mono', tc(c.in)));
        tr.appendChild(mk('td', 'mono', tc(c.out)));
        tr.appendChild(mk('td', 'mono', secOf(c.out - c.in).toFixed(3) + 's'));
        tr.appendChild(mk('td', '', money(e)));
        var act = mk('td', 'fwcc-actions');
        [['data-ed', TP.edit, 'Edit'], ['data-rm', TP.remove, 'Remove']].forEach(function (x, j) {
          var bt = mk('button', 'button', x[2]); bt.type = 'button'; bt.setAttribute(x[0], i); bt.title = x[1]; bt.setAttribute('aria-label', x[1]);
          if (j) act.appendChild(document.createTextNode(' ')); act.appendChild(bt);
        });
        tr.appendChild(act); tb.appendChild(tr);
      });
      $('.fwcc-total').textContent = (!st.list.length && st.added) ? (I18.in_cart || '%s added to cart').replace('%s', money(st.added)) : money(tot);
    }
    el.addEventListener('click', function (ev) {
      var b = ev.target.closest('button'); if (!b) return;
      if (b.classList.contains('fwcc-set')) {
        var t = now(); if (t === null) return msg('No player found on this page.', true);
        var which = b.getAttribute('data-which');
        st[which] = t; st.last = which;
        if (which === 'out') { var po = player(); if (po) { po.pause && po.pause(); seekTo(po, t); } } // hold the Out frame on screen
        msg(''); draw(); return;
      }
      if (b.classList.contains('fwcc-jump')) { // 1.5.3: seek to the current In/Out point and hold there
        var jw = b.getAttribute('data-which'), jp = player();
        if (st[jw] === null) return msg(jw === 'in' ? 'Set In first.' : 'Set Out first.', true);
        if (!jp) return msg('No player found on this page.', true);
        jp.pause && jp.pause(); seekTo(jp, st[jw]); st.last = jw;
        msg((jw === 'in' ? 'At In ' : 'At Out ') + tc(st[jw]) + '.'); return;
      }
      if (b.parentNode && b.parentNode.classList && b.parentNode.classList.contains('fwcc-nudge')) {
        var w = b.parentNode.getAttribute('data-which'), d = b.getAttribute('data-d');
        if (st[w] === null) return; st.last = w;
        // 1 frame = den/num s (1001/30000 s at 29.97); 1 s = the nearest whole number of frames (30 at 29.97)
        var step = d.slice(-1) === 'f' ? 1 : Math.round(R.fps); st[w] = Math.max(0, st[w] + (d[0] === '-' ? -step : step));
        var p = player(); if (p) { if (w === 'out' && p.pause) p.pause(); seekTo(p, st[w]); } // Out trim: seek and stay paused
        draw(); return;
      }
      if (b.classList.contains('fwcc-preview')) {
        var pl = player(); if (!pl || !dur()) return msg('Set In and Out first.', true);
        var stopAt = secOf(st.out);
        seekTo(pl, st.in); pl.play && pl.play();
        // poll as well as listen: some players (FV) only fire time events ~4x a second
        var iv = null, h = function () { if (pl.currentTime >= stopAt) { pl.pause(); clearInterval(iv); seekTo(pl, st.out); /* rest on the Out frame, like Set Out */ pl.removeEventListener('time-update', h); pl.removeEventListener('timeupdate', h); } };
        iv = setInterval(h, 30); setTimeout(function () { clearInterval(iv); }, (secOf(st.out - st.in) + 10) * 1000);
        pl.addEventListener('time-update', h); pl.addEventListener('timeupdate', h); return;
      }
      if (b.classList.contains('fwcc-addlist')) {
        if (!dur()) return msg('Set In and Out first (Out after In).', true);
        if (dur() > C.card.max_seconds) return msg('Clip too long (max ' + C.card.max_seconds + 's).', true);
        var nmv = ($('.fwcc-name').value || '').trim() || defName(st.seq);
        var row = { in: st.in, out: st.out, seq: st.seq, name: nmv }; st.list.push(row); st.seq++;
        quoteRow(row); st.in = st.out = null; st.last = null;
        delete $('.fwcc-name').dataset.touched; msg('Added to clip list.'); draw(); return;
      }
      if (b.classList.contains('fwcc-clear')) { st.in = st.out = null; st.last = null; msg(''); draw(); return; }
      if (b.hasAttribute('data-ed')) { var inp = el.querySelector('[data-nm="' + b.getAttribute('data-ed') + '"]'); if (inp) { inp.focus(); inp.select(); } return; }
      if (b.hasAttribute('data-rm')) { st.list.splice(+b.getAttribute('data-rm'), 1); draw(); return; }
      if (b.classList.contains('fwcc-copy')) {
        if (!st.list.length) return msg('Clip list is empty.', true);
        var txt = listText();
        var done2 = function () { msg('Clip list copied. Paste it into an email or a note.'); };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(done2, function () { fallbackCopy(txt); done2(); });
        else { fallbackCopy(txt); done2(); }
        return;
      }
      if (b.classList.contains('fwcc-addcart') && el.getAttribute('data-mode') === 'request') {
        if (!st.list.length) return msg('Clip list is empty.', true);
        var form = $('.fwcc-request'); form.hidden = false; var first = form.querySelector('input'); if (first) first.focus();
        msg(''); return;
      }
      if (b.classList.contains('fwcc-send')) {
        if (!st.list.length) return msg('Clip list is empty.', true);
        if (DEMO) return msg('Preview only: the Clip selector module is switched off on this site, so nothing is sent.', true);
        var nm2 = $('.fwcc-rq-name'), em = $('.fwcc-rq-email');
        if (!(nm2.value || '').trim()) { nm2.focus(); return msg('Please enter your name.', true); }
        if (!/^[^\s@]+@[^\s@]+$/.test((em.value || '').trim())) { em.focus(); return msg('Please enter a valid email address.', true); }
        b.disabled = true; msg('Sending...');
        // A fresh nonce from /token, so pages served from a page cache still work (same pattern as the estimate builder).
        fetch(C.rest + '/token', { credentials: 'omit', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (tk) { return fetch(C.rest + '/request', {
          method: 'POST', credentials: 'omit',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            nonce: tk && tk.nonce, name: nm2.value.trim(), email: em.value.trim(), note: ($('.fwcc-rq-note').value || '').trim(), website: ($('.fwcc-rq-hp').value || ''),
            clip_id: clipId, title: title, fps: fps, page_url: location.href.split('#')[0], page_id: +(el.getAttribute('data-page') || 0),
            clips: st.list.map(function (c) { return { seq: c.seq, name: c.name, in: secOf(c.in), out: secOf(c.out) }; })
          })
        }); }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (r) {
            b.disabled = false;
            if (!r.ok) return msg((r.j && r.j.message) || 'Could not send the request.', true);
            st.list = []; draw(); $('.fwcc-request').hidden = true;
            msg((r.j && r.j.message) || 'Thank you. Your clip request was sent.');
            document.dispatchEvent(new CustomEvent('ftt:clip-request', { detail: r.j }));
          })
          .catch(function (e) { b.disabled = false; msg(String(e), true); });
        return;
      }
      if (b.classList.contains('fwcc-addcart')) {
        if (!st.list.length) return msg('Clip list is empty.', true);
        if (DEMO) return msg('Preview only: the Clip selector module is switched off on this site, so nothing is added to a cart.', true);
        // 1.5.5: "Require sign-in to add to cart": save the selection server-side and offer sign-in / registration.
        if (el.getAttribute('data-require-login') === '1' && !C.loggedIn) return toLogin(b);
        b.disabled = true; msg('Adding...');
        var queue = st.list.slice(), done = 0, fail = [], needLogin = false, auth = null, mini = null;
        var prompt = el.getAttribute('data-require-login') === 'prompt' && !C.loggedIn; // 1.5.8: add, then offer sign-in
        (function next() {
          var c = queue.shift();
          if (!c) {
            b.disabled = false;
            if (needLogin && !done) return toLogin(b);
            if (prompt && done && auth && (auth.login_url || auth.register_url)) {
              if (!fail.length) { st.list = []; draw(); }
              msgLinks((I18.added_prompt || '%d clip(s) added to your cart. Please sign in or create an account to check out.').replace('%d', done) + (fail.length ? ' ' + fail.length + ' rejected: ' + fail.join('; ') : ''),
                [[I18.signin || 'Sign in', auth.login_url, 'fwcc-signin'], [I18.register || 'Create account', auth.register_url, 'fwcc-register'], [I18.viewcart || 'View cart', C.cartUrl, 'fwcc-viewcart']], !!fail.length);
            }
            else if (!fail.length) { st.list = []; draw(); msg(done + ' clip(s) added to cart.'); }
            else msg(done + ' added; ' + fail.length + ' rejected: ' + fail.join('; '), true);
            if (done) refreshMini(mini); draw();
            document.dispatchEvent(new CustomEvent('fwcc:added'));
            return;
          }
          fetch(C.rest + '/add', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
            body: JSON.stringify({ product_id: +el.getAttribute('data-product'), clip_id: clipId, seq: c.seq, name: c.name, in: secOf(c.in), out: secOf(c.out), fps: fps, client_price_cents: estimate(secOf(c.out - c.in)), login_optout: el.getAttribute('data-login-optout') || '', return: location.href })
          }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (r) { if (r.ok) { done++; if (r.j && r.j.clip && r.j.clip.price_cents != null) st.added = (st.added || 0) + (+r.j.clip.price_cents); if (r.j && r.j.mini_cart) mini = r.j.mini_cart; if (r.j && r.j.auth && r.j.auth.login_url) auth = r.j.auth; } else { if (r.j && r.j.code === 'fwcc_login_required') { needLogin = true; queue = []; } else fail.push(r.j.message || 'error'); } next(); })
            .catch(function (e) { fail.push(String(e)); next(); });
        })();
      }
    });
    el.addEventListener('input', function (ev) {
      if (ev.target.classList.contains('fwcc-name')) ev.target.dataset.touched = '1';
      if (ev.target.hasAttribute('data-nm')) st.list[+ev.target.getAttribute('data-nm')].name = ev.target.value;
    });
    // ---- Keyboard shortcuts (J/K/L, I/O, arrows) ----
    var hover = false, rate = 1, revTimer = null;
    function hookHover(node) { if (!node) return; node.addEventListener('mouseenter', function () { hover = true; }); node.addEventListener('mouseleave', function () { hover = false; }); }
    hookHover(el);
    (function hookPlayer(n) { var pp = player(); if (pp) hookHover(pp); else if (n < 40) setTimeout(function () { hookPlayer(n + 1); }, 250); })(0);
    function active() {
      var a = document.activeElement, p = player();
      return hover || (a && (el.contains(a) || (p && p.contains(a))));
    }
    function stopRev() { if (revTimer) { clearInterval(revTimer); revTimer = null; } }
    function clickBtn(sel) { var x = el.querySelector(sel); if (x) x.click(); }
    function stepHead(df) { var p = player(); if (!p) return; stopRev(); p.pause && p.pause(); var f = TC.secondsToFrames(Number(p.currentTime) || 0, fps); seekTo(p, Math.max(0, f + df)); }
    document.addEventListener('keydown', function (ev) {
      if (ev.defaultPrevented || ev.altKey || ev.metaKey) return;
      var t = ev.target, tag = t && t.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) return; // never while typing
      if (!active()) return;
      var k = ev.key, p = player(), handled = true;
      if (!ev.ctrlKey && (k === 'l' || k === 'L')) {
        stopRev(); if (!p) return;
        if (p.paused) { rate = 1; } else { rate = rate >= 4 ? 4 : rate * 2; }
        try { p.playbackRate = rate; } catch (e) {}
        if (Math.abs((Number(p.playbackRate) || 1) - rate) > 0.01) rate = Number(p.playbackRate) || 1; // player refused this rate
        p.play && p.play(); st.last = null;
      } else if (!ev.ctrlKey && (k === 'k' || k === 'K')) {
        stopRev(); if (p) { p.pause && p.pause(); try { p.playbackRate = 1; } catch (e) {} rate = 1; }
      } else if (!ev.ctrlKey && (k === 'j' || k === 'J')) {
        // YouTube cannot play backwards: emulate by stepping back 0.1 s every 100 ms.
        if (!p) return; p.pause && p.pause(); stopRev(); st.last = null;
        revTimer = setInterval(function () { var c = Number(p.currentTime) || 0; if (c <= 0) return stopRev(); p.currentTime = Math.max(0, c - 0.1); }, 100);
      } else if (!ev.ctrlKey && ev.shiftKey && (k === 'i' || k === 'I')) { stopRev(); clickBtn('.fwcc-jump[data-which=in]');
      } else if (!ev.ctrlKey && ev.shiftKey && (k === 'o' || k === 'O')) { stopRev(); clickBtn('.fwcc-jump[data-which=out]');
      } else if (!ev.ctrlKey && (k === 'i' || k === 'I')) { stopRev(); clickBtn('.fwcc-set[data-which=in]');
      } else if (!ev.ctrlKey && (k === 'o' || k === 'O')) { stopRev(); clickBtn('.fwcc-set[data-which=out]');
      } else if (k === 'ArrowLeft' || k === 'ArrowRight') {
        stopRev();
        var fwd = k === 'ArrowRight', d = (fwd ? '+' : '-') + (ev.ctrlKey ? '1s' : '1f');
        if (st.last && st[st.last] !== null) clickBtn('.fwcc-nudge[data-which=' + st.last + '] button[data-d="' + d + '"]');
        else stepHead((fwd ? 1 : -1) * (ev.ctrlKey ? Math.round(R.fps) : 1));
      } else handled = false;
      if (handled) { ev.preventDefault(); ev.stopPropagation(); }
    }, true);

    if (/[?&](?:fwcc|ftt_clip)_debug=1\b/.test(location.search)) {
      var dbg = document.createElement('pre'); dbg.className = 'fwcc-debug'; el.insertBefore(dbg, el.firstChild);
      setInterval(function () {
        var p = player(); if (!p) { dbg.textContent = 'debug: no player'; return; }
        var t = Number(p.currentTime) || 0, f = TC.secondsToFrames(t, fps);
        dbg.textContent = 'DEBUG  rate ' + R.label + ' (' + R.num + '/' + R.den + ')   raw currentTime ' + t.toFixed(6) + ' s   frame ' + f +
          '   preview TC ' + TC.framesToTC(f, fps) + (OFF ? '   master TC ' + TC.framesToTC(f + OFF, fps) : '') +
          '   duration ' + (Number(p.duration) || 0).toFixed(3) + ' s   player ' + (p.kind || 'fluent');
      }, 100);
    }
    draw();
  }
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.fwcc').forEach(init); syncMiniOnLoad(); });
})();
