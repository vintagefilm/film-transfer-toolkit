/* FTT clip-selector player adapters (from fw-clip-cart). Every adapter exposes the same small, HTMLMediaElement-like surface that the widget uses:
 *   currentTime (get/set seek), paused, duration, playbackRate (get/set, clamped to what the player allows),
 *   play(), pause(), addEventListener/removeEventListener('timeupdate'|'time-update'|mouse events), contains(node)
 * Supported: FluentPlayer (Vidstack <media-player>, used directly) and FV Player / Flowplayer (window.flowplayer API).
 */
(function (root) {
  'use strict';
  var cache = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

  function fvAdapter(el) {
    if (cache && cache.has(el)) return cache.get(el);
    var api = function () { return root.flowplayer && root.flowplayer(el); };
    var handlers = [], bound = false, pending = null;
    // FV only refreshes video.time on 'progress' (about 4x a second), so keep our own clock:
    var last = { t: 0, at: 0 }, seekTarget = null, seekAt = 0, holdUntil = 0, playUntil = 0;
    function nowMs() { return Date.now(); }
    function holding() { return nowMs() < holdUntil; }          // we asked to stay paused very recently
    function enforcePlay() { var b = api(); if (nowMs() < playUntil && b && b.ready && (!b.playing || b.paused)) b.resume(); }
    function enforcePause() { var b = api(); if (holding() && b && b.playing && !b.paused) b.pause(); }
    function bind() {
      var a = api(); if (bound || !a) return; bound = true;
      a.on('progress', function (e, x, t) { last = { t: typeof t === 'number' ? t : (a.video.time || 0), at: nowMs() }; if (seekTarget !== null && (Math.abs(last.t - seekTarget) < 0.5 || nowMs() - seekAt > 2000)) seekTarget = null; handlers.slice().forEach(function (h) { try { h(); } catch (er) {} }); });
      a.on('seek', function () { var vt = a.video.time || 0; if (seekTarget !== null && Math.abs(vt - seekTarget) > 0.5 && nowMs() - seekAt < 2000) return; /* stale seek event for an earlier position */ last = { t: vt, at: nowMs() }; seekTarget = null; enforcePause(); enforcePlay(); handlers.slice().forEach(function (h) { try { h(); } catch (er) {} }); });
      a.on('resume', function () { last.at = nowMs(); enforcePause(); });
      a.on('pause', function () { last = { t: a.video.time || last.t, at: nowMs() }; });
      a.on('ready', function () { if (pending !== null) { var t = pending; pending = null; ad.currentTime = t; } });
    }
    function liveTime() {
      var a = api(); if (!a || !a.video) return 0;
      if (seekTarget !== null && nowMs() - seekAt < 2000) return seekTarget;     // seek in flight: report where we are going
      var base = (last.at ? last.t : (a.video.time || 0));
      if (a.playing && !a.paused && last.at) base += (nowMs() - last.at) / 1000 * (Number(a.currentSpeed) || 1);
      var d = Number(a.video.duration) || 0; return d ? Math.min(base, d) : base;
    }
    var ad = {
      kind: 'fv', element: el,
      get currentTime() { bind(); return liveTime(); },
      set currentTime(t) {
        var a = api(); if (!a) return; bind(); t = Math.max(0, t);
        if (!a.ready) { pending = t; holdUntil = nowMs() + 3000; a.load(); return; }   // splash: start the engine, seek when ready
        if (!a.playing || a.paused) holdUntil = Math.max(holdUntil, nowMs() + 1500);
        seekTarget = t; seekAt = nowMs(); last = { t: t, at: nowMs() };
        a.seek(t);
        [150, 400, 800, 1300].forEach(function (ms) { setTimeout(enforcePause, ms); });
      },
      get paused() { var a = api(); return !a || !a.playing || !!a.paused || holding(); },
      get duration() { var a = api(); return a && a.video ? Number(a.video.duration) || 0 : 0; },
      get playbackRate() { var a = api(); return a ? Number(a.currentSpeed) || 1 : 1; },
      set playbackRate(r) {
        var a = api(); if (!a || typeof a.speed !== 'function') return;
        var speeds = (a.conf && a.conf.speeds) || [1], best = 1;
        speeds.forEach(function (s) { if (s <= r + 1e-9 && s > best) best = s; });   // highest allowed rate not above r
        if (r < 1) best = r;
        last = { t: liveTime(), at: nowMs() };
        try { a.speed(best); } catch (e) {}
      },
      play: function () { var a = api(); if (!a) return; bind(); holdUntil = 0; playUntil = nowMs() + 1500; if (!a.ready) a.load(); else a.resume(); [300, 800, 1300].forEach(function (ms) { setTimeout(enforcePlay, ms); }); },  // FV can ignore resume() while a seek is landing
      pause: function () { var a = api(); if (!a) return; last = { t: liveTime(), at: nowMs() }; holdUntil = nowMs() + 1500; playUntil = 0; a.pause(); },
      addEventListener: function (ev, h) { if (ev === 'timeupdate' || ev === 'time-update') { bind(); if (handlers.indexOf(h) < 0) handlers.push(h); } else el.addEventListener(ev, h); },
      removeEventListener: function (ev, h) { if (ev === 'timeupdate' || ev === 'time-update') { var i = handlers.indexOf(h); if (i >= 0) handlers.splice(i, 1); } else el.removeEventListener(ev, h); },
      contains: function (n) { return el.contains(n); }
    };
    if (cache) cache.set(el, ad);
    return ad;
  }

  var SEL = { fluent: 'media-player', fv: '.flowplayer, .freedomplayer' };

  /** Find the player for a widget. kind: 'auto' | 'fluent' | 'fv'. selector: optional explicit CSS selector. */
  function find(widget, kind, selector) {
    kind = (kind || 'auto').toLowerCase();
    var sel = selector || (kind === 'fluent' ? SEL.fluent : kind === 'fv' ? SEL.fv : SEL.fluent + ', ' + SEL.fv);
    var all = document.querySelectorAll(sel), best = null;
    // nearest player that comes before the widget in the document; else the first one on the page
    for (var i = 0; i < all.length; i++) { if (all[i].compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING) best = all[i]; }
    best = best || all[0] || null;
    if (!best) return null;
    if (best.matches && best.matches(SEL.fv)) return root.flowplayer ? fvAdapter(best) : null;
    return best; // Vidstack <media-player> already has the media-element surface
  }

  root.FTT_PLAYERS = { find: find, fvAdapter: fvAdapter };
})(window);
