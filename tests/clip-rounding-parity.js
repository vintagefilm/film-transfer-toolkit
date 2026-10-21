/* node tests/clip-rounding-parity.js cases.json - runs the real estimate() from clip-selector.js against PHP results. */
'use strict';
var fs = require('fs'), path = require('path');
var src = fs.readFileSync(path.join(__dirname, '..', 'modules', 'clip-selector', 'assets', 'clip-selector.js'), 'utf8');
var m = src.match(/function estimate\(dur\) \{[\s\S]*?\n  \}\n/);
if (!m) { console.log('FAIL estimate() not found'); process.exit(1); }
var cases = JSON.parse(fs.readFileSync(process.argv[2] || '/tmp/ftt151-cases.json', 'utf8'));
var pass = 0, fail = 0, modes = {};
cases.forEach(function (c) {
  var estimate = new Function('C', m[0] + '; return estimate;')({ card: c.card });
  var got = estimate(c.dur);
  modes[c.card.rounding] = (modes[c.card.rounding] || 0) + 1;
  if (got === c.cents) { pass++; } else { fail++; if (fail < 10) console.log('FAIL', c.card.rounding, 'min', c.card.min_seconds, 'dur', c.dur, 'php', c.cents, 'js', got); }
});
// the 59.94 example in the browser's own timecode maths
var TC = require('../core/ftt-core.js').timecode, d = TC.framesToSeconds(1800, '59.94df');
var e = function (r) { return new Function('C', m[0] + '; return estimate;')({ card: { rounding: r, min_seconds: 5, per_second_cents: 200, min_charge_cents: 1000 } })(d); };
[['up', 6200], ['down', 6000], ['nearest', 6000]].forEach(function (t) { if (e(t[0]) === t[1]) { pass++; } else { fail++; console.log('FAIL js 59.94 snap', t[0], e(t[0])); } });
console.log('clip-rounding-parity.js: ' + pass + ' passed, ' + fail + ' failed (' + JSON.stringify(modes) + ')');
process.exit(fail ? 1 : 0);
