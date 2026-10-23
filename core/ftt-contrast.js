/* FTT contrast check (1.5.7). If a tool's computed text/background contrast is under
 * 4.5:1 (WCAG AA), add .ftt--auto-contrast, which switches it to palette9 background / palette3 text.
 * Sites can set --ftt-site-bg / --ftt-site-text instead; a passing pair is left alone. */
(function () {
	'use strict';
	function rgba(c) {
		var m = String(c).match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,\/]\s*([\d.]+%?))?/);
		if (!m) { return null; }
		var a = m[4] === undefined ? 1 : (m[4].slice(-1) === '%' ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
		return [+m[1], +m[2], +m[3], a];
	}
	function lum(c) {
		var v = [c[0], c[1], c[2]].map(function (x) { x /= 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); });
		return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
	}
	function over(top, bot) { var a = top[3]; return [top[0] * a + bot[0] * (1 - a), top[1] * a + bot[1] * (1 - a), top[2] * a + bot[2] * (1 - a), 1]; }
	function bgOf(el) {
		var layers = [];
		for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
			var c = rgba(getComputedStyle(n).backgroundColor);
			if (c && c[3] > 0) { layers.push(c); if (c[3] >= 1) { break; } }
		}
		var out = [255, 255, 255, 1];
		for (var i = layers.length - 1; i >= 0; i--) { out = over(layers[i], out); }
		return out;
	}
	function ratio(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
	function check() {
		var tools = document.querySelectorAll('.ftt-tool');
		for (var i = 0; i < tools.length; i++) {
			var el = tools[i];
			if (el.classList.contains('ftt--auto-contrast')) { continue; }
			var bg = bgOf(el), fg = rgba(getComputedStyle(el).color);
			if (!fg) { continue; }
			var r = ratio(over(fg, bg), bg);
			el.setAttribute('data-ftt-contrast', r.toFixed(2));
			if (r < 4.5) { el.classList.add('ftt--auto-contrast'); }
		}
	}
	window.fttContrastCheck = check;
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', check); } else { check(); }
	window.addEventListener('load', check);
}());
