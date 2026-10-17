/* Film Transfer Toolkit clip selector (ported from FW Clip Cart 0.4.0). Reads the playhead via FTT_PLAYERS adapters (FluentPlayer/Vidstack or FV Player). */
(function () {
  'use strict';
  var C = window.FTT_CLIP || {};
  var TP = C.tips || { edit: 'Edit this clip name', remove: 'Remove this clip', rowname: 'Clip name' };
  function money(c) { return '$' + (c / 100).toFixed(2); }
  function estimate(dur) {
    var k = C.card; var b = Math.max(k.min_seconds, Math.ceil(Math.round(dur * 1000) / 1000));
    return Math.max(k.min_charge_cents, b * k.per_second_cents);
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
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function defName(seq) { return title + ' ' + pad(seq); }
    function mk(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
    var $ = function (s) { return el.querySelector(s); };
    var msg = function (t, bad) { var m = $('.fwcc-msg'); m.textContent = t; m.className = 'fwcc-msg' + (bad ? ' bad' : ''); };
    var player = function () { return findPlayer(el); };
    function now() { var p = player(); return p ? TC.secondsToFrames(Number(p.currentTime) || 0, fps) : null; }
    function dur() { return (st.in !== null && st.out !== null && st.out > st.in) ? secOf(st.out - st.in) : 0; }
    var blank = R.drop ? '--:--:--;--' : '--:--:--:--';
    function draw() {
      el.querySelector('[data-tc=in]').textContent = st.in === null ? blank : tc(st.in);
      el.querySelector('[data-tc=out]').textContent = st.out === null ? blank : tc(st.out);
      $('.fwcc-dur').textContent = dur().toFixed(3) + 's';
      $('.fwcc-est').textContent = dur() ? money(estimate(dur())) : '$0.00';
      $('.fwcc-nextlabel').textContent = clipId + '-' + pad(st.seq);
      var nm = $('.fwcc-name'); nm.placeholder = defName(st.seq); if (!nm.dataset.touched) nm.value = defName(st.seq);
      var tb = $('.fwcc-list tbody'); while (tb.firstChild) tb.removeChild(tb.firstChild); var tot = 0;
      st.list.forEach(function (c, i) {
        var e = estimate(secOf(c.out - c.in)); tot += e;
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
      $('.fwcc-total').textContent = money(tot);
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
        st.list.push({ in: st.in, out: st.out, seq: st.seq, name: nmv }); st.seq++; st.in = st.out = null; st.last = null;
        delete $('.fwcc-name').dataset.touched; msg('Added to clip list.'); draw(); return;
      }
      if (b.classList.contains('fwcc-clear')) { st.in = st.out = null; st.last = null; msg(''); draw(); return; }
      if (b.hasAttribute('data-ed')) { var inp = el.querySelector('[data-nm="' + b.getAttribute('data-ed') + '"]'); if (inp) { inp.focus(); inp.select(); } return; }
      if (b.hasAttribute('data-rm')) { st.list.splice(+b.getAttribute('data-rm'), 1); draw(); return; }
      if (b.classList.contains('fwcc-addcart')) {
        if (!st.list.length) return msg('Clip list is empty.', true);
        b.disabled = true; msg('Adding...');
        var queue = st.list.slice(), done = 0, fail = [];
        (function next() {
          var c = queue.shift();
          if (!c) {
            b.disabled = false;
            if (!fail.length) { st.list = []; draw(); msg(done + ' clip(s) added to cart.'); }
            else msg(done + ' added; ' + fail.length + ' rejected: ' + fail.join('; '), true);
            document.dispatchEvent(new CustomEvent('fwcc:added'));
            return;
          }
          fetch(C.rest + '/add', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
            body: JSON.stringify({ product_id: +el.getAttribute('data-product'), clip_id: clipId, seq: c.seq, name: c.name, in: secOf(c.in), out: secOf(c.out), fps: fps, client_price_cents: estimate(secOf(c.out - c.in)) })
          }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (r) { if (r.ok) done++; else fail.push(r.j.message || 'error'); next(); })
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
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.fwcc').forEach(init); });
})();
