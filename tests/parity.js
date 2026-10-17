/* node tests/parity.js [n]  - random requests priced by JS and PHP must match exactly. */
'use strict';
var path = require('path'), fs = require('fs'), cp = require('child_process');
var C = require('../core/ftt-core.js'), N = +process.argv[2] || 3000, seed = 12345;
function rnd() { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; }
function pick(a) { return a[Math.floor(rnd() * a.length)]; }
var tables = ['price-table-example.json', 'price-table-vfc-draft.json'].map(function (f) { return JSON.parse(fs.readFileSync(path.join(__dirname, '../core/data', f))); });
var jobs = [];
for (var n = 0; n < N; n++) {
  var t = n % 2, tb = tables[t], lines = [];
  var k = Math.floor(rnd() * 4) + (rnd() < 0.02 ? 50 : 0);
  for (var i = 0; i < k; i++) {
    var L = { format: pick(['8', 's8', '16', '35', '35', 'x']), service: pick(['hd', '4k', 'hd', 'zz']) };
    if (rnd() < 0.8) { L.reels = pick([1, 1, 2, 3, 7, 0, 1000, '2', 1.5]); }
    if (rnd() < 0.6) { L.size = pick(['3', '5', '6', '7', '10', '14', '15', '9']); }
    if (rnd() < 0.5) { L.feet = pick([10, 49.9, 50, 120.25, 333, 1234.4, 99999, -1, 0, '75', 200000]); }
    if (rnd() < 0.6) { L.addons = tb.addons.filter(function () { return rnd() < 0.3; }).map(function (a) { return a.id; }); if (rnd() < 0.1) { L.addons.push('nope'); } }
    lines.push(L);
  }
  var oa = tb.addons.filter(function (a) { return a.unit === 'order' && rnd() < 0.4; }).map(function (a) { return a.id; });
  jobs.push({ t: t, r: { lines: lines, order_addons: oa } });
}
var php = '<?php define("ABSPATH","/"); require "' + path.join(__dirname, '../includes/core/class-ftt-pricing.php') + '";' +
  '$t=[json_decode(file_get_contents("' + path.join(__dirname, '../core/data/price-table-example.json') + '"),true),json_decode(file_get_contents("' + path.join(__dirname, '../core/data/price-table-vfc-draft.json') + '"),true)];' +
  '$o=[];foreach(json_decode(stream_get_contents(STDIN),true) as $j){$o[]=FTT_Pricing::quote($t[$j["t"]],$j["r"]);} echo json_encode($o);';
fs.writeFileSync('/tmp/ftt-parity.php', php);
var out = JSON.parse(cp.execFileSync('php', ['/tmp/ftt-parity.php'], { input: JSON.stringify(jobs), maxBuffer: 1 << 28 }));
var bad = 0, okc = 0;
jobs.forEach(function (j, i) {
  var a = JSON.stringify(C.pricing.quote(tables[j.t], j.r)), b = JSON.stringify(out[i]);
  if (a !== b) { bad++; if (bad < 4) { console.log('MISMATCH', JSON.stringify(j.r), '\nJS ', a, '\nPHP', b); } }
  if (JSON.parse(a).ok) { okc++; }
});
console.log('parity.js: ' + N + ' random requests (' + okc + ' fully valid), ' + bad + ' mismatches');
process.exit(bad ? 1 : 0);
