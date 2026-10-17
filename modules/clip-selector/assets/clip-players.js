/* FTT clip-selector player adapters (from fw-clip-cart). Every adapter exposes the same small, HTMLMediaElement-like surface that the widget uses:
 *   currentTime (get/set seek), paused, duration, playbackRate (get/set, clamped to what the player allows),
 *   play(), pause(), addEventListener/removeEventListener('timeupdate'|'time-update'|mouse events), contains(node)
 * Supported: FluentPlayer (Vidstack <media-player>, used directly), FV Player / Flowplayer (window.flowplayer API),
 * plain HTML5 <video> (core Video block, [video], any uploaded file; used directly), and YouTube / Vimeo iframes
 * (driven with each player's own postMessage protocol, so no third-party script is loaded).
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

  // ---- YouTube / Vimeo: postMessage adapters with our own clock between player updates ----
  function iframeAdapter(el, kind) {
    if (cache && cache.has(el)) return cache.get(el);
    var origin = (function () { try { return new URL(el.src, location.href).origin; } catch (e) { return '*'; } })();
    var handlers = [], st = { t: 0, at: 0, playing: false, dur: 0, rate: 1, rates: [1], ready: false };
    var holdUntil = 0, seekTarget = null, seekAt = 0;
    function nowMs() { return Date.now(); }
    function post(msg) { try { el.contentWindow && el.contentWindow.postMessage(JSON.stringify(msg), origin); } catch (e) {} }
    function fire() { handlers.slice().forEach(function (h) { try { h(); } catch (er) {} }); }
    function setTime(t) { st.t = t; st.at = nowMs(); if (seekTarget !== null && (Math.abs(t - seekTarget) < 0.3 || nowMs() - seekAt > 2000)) seekTarget = null; }
    // YouTube needs enablejsapi=1 in the embed URL; add it once (the iframe reloads) when the page did not.
    if (kind === 'youtube' && !/[?&]enablejsapi=1/.test(el.src)) { el.src = el.src + (el.src.indexOf('?') < 0 ? '?' : '&') + 'enablejsapi=1&origin=' + encodeURIComponent(location.origin); }
    var yt = { id: 'ftt-yt-' + Math.random().toString(36).slice(2) };
    function cmd(name, args) {
      if (kind === 'youtube') post({ event: 'command', func: name, args: args || [], id: yt.id, channel: 'widget' });
      else post(args === undefined ? { method: name } : { method: name, value: args });
    }
    function hello() {
      if (kind === 'youtube') post({ event: 'listening', id: yt.id, channel: 'widget' });
      else ['timeupdate', 'play', 'pause', 'seeked', 'ended', 'playbackratechange'].forEach(function (ev) { post({ method: 'addEventListener', value: ev }); });
      if (kind === 'vimeo') { post({ method: 'getDuration' }); post({ method: 'getPaused' }); post({ method: 'getCurrentTime' }); }
    }
    root.addEventListener('message', function (ev) {
      if (ev.source !== el.contentWindow) return;
      var d = ev.data; if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return; } }
      if (!d || typeof d !== 'object') return;
      st.ready = true;
      if (kind === 'youtube') {
        var i = d.info; if (!i || typeof i !== 'object') return;
        if (typeof i.playerState === 'number') { st.playing = i.playerState === 1; if (!st.playing) st.at = nowMs(); }
        if (typeof i.currentTime === 'number') setTime(i.currentTime);
        if (typeof i.duration === 'number' && i.duration > 0) st.dur = i.duration;
        if (typeof i.playbackRate === 'number') st.rate = i.playbackRate;
        if (i.availablePlaybackRates && i.availablePlaybackRates.length) st.rates = i.availablePlaybackRates;
        fire();
      } else {
        var e = d.event, m = d.method, v = d.value, x = d.data || {};
        if (e === 'ready') { hello(); return; }
        if (e === 'timeupdate' || e === 'seeked') { if (typeof x.seconds === 'number') setTime(x.seconds); if (x.duration) st.dur = x.duration; }
        if (e === 'play') { st.playing = true; st.at = nowMs(); }
        if (e === 'pause' || e === 'ended') { st.playing = false; if (typeof x.seconds === 'number') setTime(x.seconds); }
        if (e === 'playbackratechange' && x.playbackRate) st.rate = x.playbackRate;
        if (m === 'getDuration' && typeof v === 'number') st.dur = v;
        if (m === 'getPaused') st.playing = !v;
        if (m === 'getCurrentTime' && typeof v === 'number') setTime(v);
        fire();
      }
    });
    el.addEventListener('load', function () { setTimeout(hello, 300); });
    hello(); var tries = 0, iv = setInterval(function () { if (st.ready || ++tries > 40) { clearInterval(iv); return; } hello(); }, 250);
    function liveTime() {
      if (seekTarget !== null && nowMs() - seekAt < 2000) return seekTarget;
      var t = st.t; if (st.playing && st.at && nowMs() >= holdUntil) t += (nowMs() - st.at) / 1000 * (st.rate || 1);
      return st.dur ? Math.min(t, st.dur) : t;
    }
    var ad = {
      kind: kind, element: el,
      get currentTime() { return liveTime(); },
      set currentTime(t) { t = Math.max(0, t); seekTarget = t; seekAt = nowMs(); st.t = t; st.at = nowMs(); if (kind === 'youtube') cmd('seekTo', [t, true]); else cmd('setCurrentTime', t); },
      get paused() { return !st.playing || nowMs() < holdUntil; },
      get duration() { return st.dur; },
      get playbackRate() { return st.rate; },
      set playbackRate(r) {
        var best = 1; (st.rates || [1]).forEach(function (s) { if (s <= r + 1e-9 && s > best) best = s; });
        if (r < 1) best = r;
        st.t = liveTime(); st.at = nowMs();
        if (kind === 'youtube') cmd('setPlaybackRate', [best]); else cmd('setPlaybackRate', best);
        st.rate = best;
      },
      play: function () { holdUntil = 0; st.at = nowMs(); cmd(kind === 'youtube' ? 'playVideo' : 'play'); },
      pause: function () { st.t = liveTime(); st.at = nowMs(); st.playing = false; holdUntil = nowMs() + 400; cmd(kind === 'youtube' ? 'pauseVideo' : 'pause'); },
      addEventListener: function (ev, h) { if (ev === 'timeupdate' || ev === 'time-update') { if (handlers.indexOf(h) < 0) handlers.push(h); } else el.addEventListener(ev, h); },
      removeEventListener: function (ev, h) { if (ev === 'timeupdate' || ev === 'time-update') { var i = handlers.indexOf(h); if (i >= 0) handlers.splice(i, 1); } else el.removeEventListener(ev, h); },
      contains: function (n) { return el === n || el.contains(n); }
    };
    if (cache) cache.set(el, ad);
    return ad;
  }

  var SEL = {
    fluent: 'media-player',
    fv: '.flowplayer, .freedomplayer',
    html5: 'video',
    youtube: 'iframe[src*="youtube.com/embed/"], iframe[src*="youtube-nocookie.com/embed/"]',
    vimeo: 'iframe[src*="player.vimeo.com/video/"]'
  };
  var ORDER = ['fluent', 'fv', 'html5', 'youtube', 'vimeo'];

  function kindOf(node) {
    for (var i = 0; i < ORDER.length; i++) { if (node.matches && node.matches(SEL[ORDER[i]])) return ORDER[i]; }
    var inner = node.querySelector && node.querySelector(ORDER.map(function (k) { return SEL[k]; }).join(', '));
    return inner ? kindOf(inner) : null;
  }
  /** A <video> that belongs to FluentPlayer or FV Player is driven through that player instead. */
  function owned(node) { return node.tagName === 'VIDEO' && node.parentElement && node.parentElement.closest(SEL.fluent + ', ' + SEL.fv); }

  /** Wrap a player element in its adapter (HTML5 video and Vidstack are used as they are). */
  function adapt(node) {
    var k = kindOf(node); if (!k) return null;
    if (!node.matches(SEL[k])) node = node.querySelector(SEL[k]);
    if (k === 'fv') return root.flowplayer ? fvAdapter(node) : null;
    if (k === 'youtube' || k === 'vimeo') return iframeAdapter(node, k);
    return node;
  }

  /**
   * Find the player for a widget. kind: auto | fluent | fv | html5 | youtube | vimeo.
   * selector: optional CSS selector (e.g. "#interview") naming the player, or an element that contains it.
   */
  function find(widget, kind, selector) {
    kind = (kind || 'auto').toLowerCase();
    if (selector) {
      var n = null; try { n = document.querySelector(selector); } catch (e) { n = null; }
      return n ? adapt(n) : null;
    }
    var keys = SEL[kind] ? [kind] : ORDER;
    var all = Array.prototype.filter.call(document.querySelectorAll(keys.map(function (k) { return SEL[k]; }).join(', ')), function (n) { return !owned(n) && !widget.contains(n); });
    var best = null;
    // nearest player that comes before the widget in the document; else the first one on the page
    for (var i = 0; i < all.length; i++) { if (all[i].compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING) best = all[i]; }
    best = best || all[0] || null;
    return best ? adapt(best) : null;
  }

  root.FTT_PLAYERS = { find: find, adapt: adapt, fvAdapter: fvAdapter, iframeAdapter: iframeAdapter, selectors: SEL };
})(window);
