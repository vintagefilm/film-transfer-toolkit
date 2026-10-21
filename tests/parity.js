/* node tests/parity.js [n]  - random requests priced by JS and PHP must match exactly. */
'use strict';
var path = require('path'), fs = require('fs'), cp = require('child_process');
var C = require('../core/ftt-core.js'), N = +process.argv[2] || 3000, seed = 12345;
function rnd() { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; }
function pick(a) { return a[Math.floor(rnd() * a.length)]; }
var tables = ['price-table-example.json', 'price-table-vfc-draft.json'].map(function (f) { return JSON.parse(fs.readFileSync(path.join(__dirname, '../core/data', f))); });
/* 1.5.2: each table also gets per-custom-unit add-ons (order-level, with a quantity). */
tables.forEach(function (tb) {
  tb.addons.push({ id: 'usbx', label: 'USB drive', unit: 'custom', unit_label: 'USB drive', price: 15000, formats: [], description: 'A <b>32 GB</b> drive' });
  tb.addons.push({ id: 'hour', label: 'Editing', unit: 'custom', unit_label: 'hour', price: 47500, formats: [] });
});
var jobs = [];
for (var n = 0; n < N; n++) {
  var t = n % 2, tb = tables[t], lines = [];
  var k = Math.floor(rnd() * 4) + (rnd() < 0.02 ? 50 : 0);
  for (var i = 0; i < k; i++) {
    var L = { format: pick(['8', 's8', '16', '35', '35', 'x']), service: pick(['hd', '4k', 'hd', 'zz', '4k_out', '1080_out']) };
    if (rnd() < 0.8) { L.reels = pick([1, 1, 2, 3, 7, 0, 1000, '2', 1.5]); }
    if (rnd() < 0.6) { L.size = pick(['3', '5', '6', '7', '10', '14', '15', '9']); }
    if (rnd() < 0.5) { L.feet = pick([10, 49.9, 50, 120.25, 333, 1234.4, 99999, -1, 0, '75', 200000]); }
    if (rnd() < 0.6) { L.addons = tb.addons.filter(function () { return rnd() < 0.3; }).map(function (a) { return a.id; }); if (rnd() < 0.1) { L.addons.push('nope'); } }
    lines.push(L);
  }
  var oa = tb.addons.filter(function (a) { return (a.unit === 'order' || a.unit === 'custom') && rnd() < 0.4; }).map(function (a) {
    if (a.unit !== 'custom' || rnd() < 0.15) { return a.id; }
    var o = { id: a.id }; if (rnd() < 0.9) { o.qty = pick([1, 2, 3, 3, 10, 999, 1000, 0, -2, 1.5, 2.0, '4', '04', '3.0', ' 3', '', null, true, 'x', 1e3]); } return o;
  });
  if (rnd() < 0.05) { oa.push(pick([{ id: 'usbx', qty: 2 }, { id: 'usb' }, { id: 'zz', qty: 1 }, {}, 'usbx'])); }
  jobs.push({ t: t, r: { lines: lines, order_addons: oa } });
}
var php = '<?php define("ABSPATH","/"); require "' + path.join(__dirname, '../includes/core/class-ftt-pricing.php') + '";' +
  '$in=json_decode(stream_get_contents(STDIN),true);$t=$in["tables"];' +
  '$o=[];foreach($in["jobs"] as $j){$o[]=FTT_Pricing::quote($t[$j["t"]],$j["r"]);} echo json_encode($o);';
fs.writeFileSync('/tmp/ftt-parity.php', php);
var out = JSON.parse(cp.execFileSync('php', ['/tmp/ftt-parity.php'], { input: JSON.stringify({ tables: tables, jobs: jobs }), maxBuffer: 1 << 28 }));
var bad = 0, okc = 0, qtyc = 0;
jobs.forEach(function (j, i) {
  var a = JSON.stringify(C.pricing.quote(tables[j.t], j.r)), b = JSON.stringify(out[i]);
  if (a !== b) { bad++; if (bad < 4) { console.log('MISMATCH', JSON.stringify(j.r), '\nJS ', a, '\nPHP', b); } }
  var pa = JSON.parse(a); if (pa.ok) { okc++; if (pa.totals.order_addons.some(function (x) { return x.qty; })) { qtyc++; } }
});
console.log('parity.js: ' + N + ' random requests (' + okc + ' fully valid, ' + qtyc + ' with custom-unit quantities), ' + bad + ' mismatches');
process.exit(bad ? 1 : 0);
