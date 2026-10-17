/* Film Calculator UI 1.1.0 - vanilla JS, no external calls. Math lives in film-calc-core.js. */
(function () {
  'use strict';
  var Core = window.FTTCore;
  if (!Core) { return; }
  var r = Core.round;

  function init(root) {
    var q = function (k) { return root.querySelector('[data-fc="' + k + '"]'); };
    var el = {
      format: q('format'), speed: q('speed'), custom: q('custom'), feet: q('feet'), meters: q('meters'),
      time: q('time'), frames: q('frames'), summary: q('summary'), ftmin: q('ftmin'), seconds: q('seconds'),
      sw: q('switch'), mode: q('mode'), tablefps: q('tablefps')
    };
    var customWrap = root.querySelector('.filmcalc__custom');
    var rows = root.querySelectorAll('[data-fc-row]');
    var last = 'feet';

    function fmtOpt() { return el.format.options[el.format.selectedIndex]; }
    function fpf() { return parseFloat(fmtOpt().getAttribute('data-fpf')); }
    function fps() { return el.speed.value === 'custom' ? parseFloat(el.custom.value) : parseFloat(el.speed.value); }

    function updateTable(s) {
      if (!rows.length) { return; }
      if (el.tablefps) { el.tablefps.textContent = s > 0 ? r(s, 3) : '–'; }
      Array.prototype.forEach.call(rows, function (row) {
        var cell = row.querySelector('[data-fc="rowftmin"]');
        cell.textContent = s > 0 ? Core.feetPerMinute(parseFloat(row.getAttribute('data-fpf')), s).toFixed(1) : '–';
        row.classList.toggle('is-current', row.getAttribute('data-fc-row') === el.format.value);
      });
    }

    function update(src) {
      if (src) { last = src; }
      customWrap.hidden = el.speed.value !== 'custom';
      var s = fps();
      updateTable(s);
      var res = Core.compute({ fpf: fpf(), fps: s, from: last, value: el[last].value });
      if (!res) {
        el.summary.textContent = 'Enter a valid length, time or frame count and a frame rate.';
        if (el.ftmin) { el.ftmin.textContent = s > 0 ? r(Core.feetPerMinute(fpf(), s), 2) + ' ft/min' : '–'; }
        if (el.seconds) { el.seconds.textContent = '–'; }
        return;
      }
      if (last !== 'feet') { el.feet.value = r(res.feet, 2); }
      if (last !== 'meters') { el.meters.value = r(res.meters, 2); }
      if (last !== 'time') { el.time.value = Core.formatTime(res.seconds); }
      if (last !== 'frames') { el.frames.value = r(res.frames, 0); }
      var label = fmtOpt().text.split(' –')[0];
      el.summary.textContent = label + ': ' + r(res.feet, 2) + ' ft (' + r(res.meters, 2) + ' m) = ' +
        Math.round(res.frames).toLocaleString() + ' frames (' + res.wholeFeet + ' ft + ' + res.extraFrames + ' fr) = ' +
        Core.formatTime(res.seconds) + ' at ' + r(s, 3) + ' fps';
      if (el.ftmin) { el.ftmin.textContent = r(res.ftPerMin, 2) + ' ft/min'; }
      if (el.seconds) { el.seconds.textContent = res.seconds.toFixed(3) + ' s'; }
    }

    function setMode(mode) {
      root.setAttribute('data-mode', mode);
      if (el.sw) { el.sw.setAttribute('aria-pressed', mode === 'time' ? 'true' : 'false'); }
      if (el.mode) { el.mode.textContent = el.mode.getAttribute(mode === 'time' ? 'data-time' : 'data-len'); }
    }

    ['feet', 'meters', 'time', 'frames'].forEach(function (k) {
      el[k].addEventListener('input', function () { update(k); });
    });
    [el.format, el.speed, el.custom].forEach(function (e) {
      e.addEventListener('input', function () { update(); });
      e.addEventListener('change', function () { update(); });
    });
    if (el.sw) {
      el.sw.addEventListener('click', function () {
        var next = root.getAttribute('data-mode') === 'time' ? 'len' : 'time';
        setMode(next);
        var target = next === 'time' ? el.time : el.feet;
        target.focus();
        if (target.select) { target.select(); }
      });
    }
    update('feet');
  }

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-ftt-calc]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
