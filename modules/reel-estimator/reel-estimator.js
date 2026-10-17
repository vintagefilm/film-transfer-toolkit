/* [ftt_reel_estimator] UI. Maths in FTTCore. textContent only. */
(function () {
  'use strict';
  var Core = window.FTTCore;
  function init(root) {
    var q = function (k) { return root.querySelector('[data-r="' + k + '"]'); };
    var fmt = q('format'), size = q('size'), count = q('count'), outer = q('outer'), unit = q('unit'), hub = q('hub'), base = q('base'), out = q('out');
    var fixedFps = parseFloat(root.getAttribute('data-fps'));
    function method() { var m = root.querySelector('[data-r="method"]:checked'); return m ? m.value : 'size'; }
    function sizes() {
      var keep = size.value;
      while (size.firstChild) { size.removeChild(size.firstChild); }
      Core.reels.sizes(fmt.value).forEach(function (r) {
        var o = document.createElement('option'); o.value = r.size; o.textContent = r.label + ' \u2013 up to ' + r.feet + ' ft'; o.setAttribute('data-feet', r.feet); size.appendChild(o);
      });
      if (keep) { size.value = keep; }
      if (!size.value && size.options.length) { size.selectedIndex = 0; }
      hub.placeholder = String(Core.reels.HUB[Core.reels.group(fmt.value)] * (unit.value === 'cm' ? 2.54 : 1)).slice(0, 5);
    }
    function run() {
      var opt = fmt.options[fmt.selectedIndex], fpf = +opt.getAttribute('data-fpf'), fps = fixedFps > 0 ? fixedFps : +opt.getAttribute('data-fps');
      var feet = null, range = '';
      root.querySelector('[data-r="size-panel"]').hidden = method() !== 'size';
      root.querySelector('[data-r="measure-panel"]').hidden = method() !== 'measure';
      if (method() === 'size') {
        var so = size.options[size.selectedIndex], n = parseInt(count.value, 10);
        if (so && n >= 1 && n <= 999) { feet = +so.getAttribute('data-feet') * n; range = n > 1 ? ' (' + n + ' full reels)' : ' (a full reel)'; }
      } else {
        var r = Core.reels.feetFromDiameter({ format: fmt.value, outer: outer.value, hub: hub.value, unit: unit.value, base: base.value });
        if (r) { feet = r.feet; range = ' (roughly ' + Math.round(r.low) + '\u2013' + Math.round(r.high) + ' ft)'; }
      }
      if (feet === null) { out.textContent = method() === 'size' ? 'Choose a reel size and a count from 1 to 999.' : 'Enter the diameter of the wound film (larger than the hub).'; return; }
      var c = Core.compute({ fpf: fpf, fps: fps, from: 'feet', value: feet });
      out.textContent = 'About ' + Math.round(feet).toLocaleString() + ' ft' + range + ' \u2248 ' + Core.formatTime(c.seconds) + ' of running time at ' + fps + ' fps.';
      root.dispatchEvent(new CustomEvent('ftt:reels', { bubbles: true, detail: { format: fmt.value, feet: feet, size: method() === 'size' ? size.value : '', reels: method() === 'size' ? parseInt(count.value, 10) : 1 } }));
    }
    fmt.addEventListener('change', function () { sizes(); run(); });
    unit.addEventListener('change', function () { sizes(); run(); });
    root.addEventListener('input', run);
    root.addEventListener('change', run);
    sizes(); run();
  }
  function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-ftt-reels]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
