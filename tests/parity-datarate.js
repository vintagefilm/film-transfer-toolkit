/* node tests/parity-datarate.js [n]  - random storage requests computed by JS and PHP must match exactly. */
'use strict';
var path = require('path'), fs = require('fs'), cp = require('child_process');
var C = require('../core/ftt-core.js'), N = +process.argv[2] || 3000, seed = 777;
function rnd() { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; }
function pick(a) { return a[Math.floor(rnd() * a.length)]; }
var P = C.datarate.PRESETS, sizes = P.sizes.map(function (s) { return s.id; }).concat(['custom', 'custom', 'nope', 7]);
function del() {
  var k = pick(['prores', 'prores', 'h264', 'h265', 'youtube', 'dpx', 'tiff', 'uncompressed', 'custom', 'zip', '', 5]), d = { kind: k };
  if (rnd() < 0.6) { d.fps = pick(['film', '', null, 16, 18, 23.976, 24, 25, 29.97, '30', 47.952, 50, 59.94, 60, 0, 121, '24fps', true]); }
  if (rnd() < 0.3) { d.audio_kbps = pick([0, 128, 384, 2304, '384', -1, 30000]); }
  d.profile = pick(['proxy', 'lt', '422', 'hq', '4444', '4444xq', 'raw']);
  d.size = pick(sizes); d.width = pick([1001, 1920, 4096, 15, 16384, 20000, '720', 12.5]); d.height = pick([16, 1080, 3112, 9, '486']);
  d.mbps = pick([0.005, 0.01, 1, 8, 12.5, '45', 0.1 + 0.2, 20000, 20001, '1e3', -3, [5]]);
  d.res = pick(['1080p', '1440p', '2160p', '720p']); d.hdr = pick([true, false, 1, '1', 0, 'yes']);
  d.depth = pick([8, 10, 12, 16, '10', '010', 32, 10.0]); d.type = pick(['v210', '2vuy', 'v410', 'r210']);
  return d;
}
function line() {
  var L = { format: pick(['8', 's8', '16', '35', '35-3', '35-2', 'x', 16, null]) };
  if (rnd() < 0.7) { L.fps = pick([16, 18, 24, 25, 29.97, '18', 0, 200, null, '']); }
  if (rnd() < 0.8) { L.reels = pick([1, 1, 2, 3, 12, 0, 1000, '2', 1.5]); }
  var m = rnd();
  if (m < 0.4) { L.size = pick(['3', '4', '5', '6', '7', '10', '12', '14', '15', '9', 7, '07']); }
  else if (m < 0.7) { L.feet = pick([10, 49.9, 50, 120.25, 333, 1234.4, 99999, -1, 0, '75', 200000, 0.001]); }
  else if (m < 0.95) { L.seconds = pick([1, 59.5, 600, 3600, 7200.25, '90', 0, 400000]); }
  return L;
}
function vdel() {
  var k = pick(['prores', 'prores', 'h264', 'h265', 'youtube', 'dpx', 'tiff', 'uncompressed', 'custom']), d = { kind: k, fps: pick(['film', 'film', 23.976, 24, 25, 29.97, 30, 59.94, 60, 18]) };
  if (rnd() < 0.3) { d.audio_kbps = pick([128, 384, 2304]); }
  if (k === 'prores') { d.profile = pick(['proxy', 'lt', '422', 'hq', '4444', '4444xq']); d.size = pick(['sd486', 'sd576', 'hd', '2k-dci', '2k-fa', 'uhd', '4k-dci']); }
  else if (k === 'youtube') { d.res = pick(['1080p', '1440p', '2160p']); d.hdr = rnd() < 0.3; }
  else if (k === 'dpx' || k === 'tiff') { d.size = pick(sizes.slice(0, 9)); d.width = pick([1001, 1440, 6144]); d.height = pick([1080, 777]); d.depth = k === 'dpx' ? pick([8, 10, 12, 16]) : pick([8, 16]); }
  else if (k === 'uncompressed') { d.type = pick(['v210', '2vuy', 'v410']); d.size = pick(sizes.slice(0, 9)); d.width = pick([1280, 1001, 2048]); d.height = pick([720, 1556]); }
  else { d.mbps = pick([2.5, 8, 12.5, 45, 100, 0.1 + 0.2, '17.25']); }
  return d;
}
function vline() {
  var L = { format: pick(['8', 's8', '16', '35', '35-3', '35-2']), fps: pick([16, 18, 24, 25, 29.97, undefined]), reels: pick([1, 2, 3, 12, 40]) };
  var m = rnd(), g = L.format.indexOf('35') === 0 ? ['6', '10', '15'] : L.format === '16' ? ['3', '4', '5', '7', '10', '12', '14'] : ['3', '5', '6', '7'];
  if (m < 0.4) { L.size = pick(g); } else if (m < 0.7) { L.feet = pick([50, 120.25, 333, 1234.4, 9999]); } else { L.seconds = pick([59.5, 600, 3600, 7200.25]); }
  return L;
}
var jobs = [];
for (var n = 0; n < N; n++) {
  var r = { lines: [], deliverables: [] }, i;
  var nl = Math.floor(rnd() * 4) + (rnd() < 0.01 ? 50 : 0), nd = Math.floor(rnd() * 4) + (rnd() < 0.01 ? 10 : 0);
  var V = n % 2 === 1;
  if (V) { nl = nl || 1; nd = nd || 1; }
  for (i = 0; i < nl; i++) { r.lines.push(V ? vline() : line()); }
  for (i = 0; i < nd; i++) { r.deliverables.push(V ? vdel() : del()); }
  if (rnd() < 0.3) { r.headroom_pct = pick([0, 10, 20, 25, '30', 101, -5]); }
  if (rnd() < 0.02) { r = pick([[], 'x', null, { lines: {}, deliverables: 'a' }]); }
  jobs.push(r);
}
var php = '<?php define("ABSPATH","/"); require "' + path.join(__dirname, '../includes/core/class-ftt-datarate.php') + '";' +
  '$o=[];foreach(json_decode(stream_get_contents(STDIN),true) as $j){$o[]=FTT_Datarate::storage($j);} echo json_encode($o);';
fs.writeFileSync('/tmp/ftt-parity-dr.php', php);
var out = JSON.parse(cp.execFileSync('php', ['/tmp/ftt-parity-dr.php'], { input: JSON.stringify(jobs), maxBuffer: 1 << 29 }));
var bad = 0, okc = 0;
jobs.forEach(function (j, i) {
  var a = JSON.stringify(C.datarate.storage(j)), b = JSON.stringify(out[i]);
  if (a !== b) { bad++; if (bad < 4) { console.log('MISMATCH', JSON.stringify(j), '\nJS ', a, '\nPHP', b); } }
  if (JSON.parse(a).ok) { okc++; }
});
console.log('parity-datarate.js: ' + N + ' random requests (' + okc + ' fully valid), ' + bad + ' mismatches');
process.exit(bad ? 1 : 0);
