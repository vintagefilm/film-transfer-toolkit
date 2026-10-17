/* node tests/core-tests.js  - runs core/test-vectors against core/ftt-core.js */
'use strict';
var path = require('path'), fs = require('fs');
var C = require('../core/ftt-core.js');
var V = path.join(__dirname, '..', 'core', 'test-vectors');
var pass = 0, fail = 0;
function ok(c, m) { if (c) { pass++; } else { fail++; console.log('FAIL', m); } }
function sub(exp, got, where) {
  Object.keys(exp).forEach(function (k) {
    if (exp[k] && typeof exp[k] === 'object' && !Array.isArray(exp[k])) { sub(exp[k], got && got[k], where + '.' + k); }
    else if (Array.isArray(exp[k]) && exp[k].length && typeof exp[k][0] === 'object') { exp[k].forEach(function (e, i) { sub(e, got && got[k] && got[k][i], where + '.' + k + '[' + i + ']'); }); }
    else { ok(JSON.stringify(got && got[k]) === JSON.stringify(exp[k]), where + '.' + k + ' expected ' + JSON.stringify(exp[k]) + ' got ' + JSON.stringify(got && got[k])); }
  });
}
var P = JSON.parse(fs.readFileSync(path.join(V, 'pricing.json')));
var table = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'core', 'data', P.table)));
P.cases.forEach(function (c) {
  var q = C.pricing.quote(table, c.request), e = JSON.parse(JSON.stringify(c.expect));
  if (e.error_codes) { ok(JSON.stringify(q.errors.map(function (x) { return x.code; })) === JSON.stringify(e.error_codes), c.name + ' error codes ' + JSON.stringify(q.errors.map(function (x) { return x.code; }))); delete e.error_codes; }
  sub(e, q, c.name);
});
['price-table-vfc-draft.json'].forEach(function (f) {
  var t = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'core', 'data', f)));
  ok(t.status === 'draft' && /DRAFT FOR REVIEW/.test(t.label), f + ' is marked DRAFT FOR REVIEW');
  ok(C.pricing.quote(t, { lines: [{ format: 's8', service: 'hd', reels: 1, size: '7' }] }).totals.total_cents === 12800, f + ' 7in Super 8 HD = $128.00');
});
var Lv = JSON.parse(fs.readFileSync(path.join(V, 'length.json')));
Lv.cases.forEach(function (c) {
  var r = C.compute({ fpf: c.fpf, fps: c.fps, from: c.from, value: c.value });
  ['seconds', 'frames', 'feet'].forEach(function (k) { if (c[k] != null) { ok(Math.abs(r[k] - c[k]) < 0.01, 'length ' + JSON.stringify(c) + ' ' + k + '=' + r[k]); } });
});
Lv.reels.forEach(function (c) {
  var r = C.reels.feetFromDiameter(c);
  if (c.null) { ok(r === null, 'reel invalid ' + JSON.stringify(c)); } else { ok(r && r.feet >= c.feet_min && r.feet <= c.feet_max, 'reel ' + JSON.stringify(c) + ' -> ' + (r && r.feet)); }
});
var T = JSON.parse(fs.readFileSync(path.join(V, 'timecode.json'))), tc = C.timecode;
T.known.forEach(function (k) { ok(tc.tcToFrames(k.tc, k.rate) === k.frames, 'tc->f ' + k.tc); ok(tc.framesToTC(k.frames, k.rate) === k.tc, 'f->tc ' + k.frames); });
T.invalid.forEach(function (k) { var threw = false; try { tc.tcToFrames(k.tc, k.rate); } catch (e) { threw = true; } ok(threw, 'invalid ' + k.tc); });
T.seconds.forEach(function (k) { ok(Math.abs(tc.framesToSeconds(k.frames, k.rate) - k.seconds) < 1e-3, 'sec ' + k.frames); });
T.roundtrip_rates.forEach(function (r) { var bad = 0; for (var f = 0; f <= T.roundtrip_max_frames; f += 7) { if (tc.tcToFrames(tc.framesToTC(f, r), r) !== f) { bad++; } } ok(bad === 0, 'roundtrip ' + r); });
ok(C.pricing.formatMoney(123456) === '$1,234.56' && C.pricing.formatMills(325) === '$0.325' && C.pricing.formatMills(320) === '$0.32', 'money formatting');
var D = JSON.parse(fs.readFileSync(path.join(V, 'datarate.json'))), DRP = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'core', 'data', D.presets)));
ok(JSON.stringify(C.datarate.PRESETS) === JSON.stringify(DRP), 'embedded datarate presets equal core/data/datarate-presets.json (run tools/embed-presets.js)');
D.cases.forEach(function (c) {
  var q = C.datarate.storage(c.request), e = JSON.parse(JSON.stringify(c.expect));
  if (e.error_codes) { ok(JSON.stringify(q.errors.map(function (x) { return x.code; })) === JSON.stringify(e.error_codes), c.name + ' error codes ' + JSON.stringify(q.errors.map(function (x) { return x.code; }))); delete e.error_codes; }
  sub(e, q, c.name);
});
D.fmt_bytes.forEach(function (f) { ok(C.datarate.fmtBytes(f.bytes, f.binary) === f.text, 'fmtBytes ' + f.bytes + ' -> ' + C.datarate.fmtBytes(f.bytes, f.binary)); });
D.fmt_drive.forEach(function (f) { ok(C.datarate.fmtDrive(f.drive) === f.text, 'fmtDrive ' + f.text); });
console.log('core-tests.js: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
