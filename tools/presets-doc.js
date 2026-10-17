/* node tools/presets-doc.js > docs/datarate-presets.md - preset table generated from core/data/datarate-presets.json via FTTCore. */
'use strict';
var C = require('../core/ftt-core.js'), D = C.datarate, P = D.PRESETS, o = [];
function src(k) { var s = P.sources[k]; return '[' + s.title + '](' + s.url + ')'; }
o.push('# Film Transfer Toolkit - data rate / storage presets', '', 'Presets version ' + P.version + ', rates checked ' + P.as_of + '. Generated from `core/data/datarate-presets.json` by `node tools/presets-doc.js`; the same numbers drive `FTTCore.datarate` (JS) and `FTT_Datarate` (PHP).', '');
o.push('## Sources', '');
Object.keys(P.sources).forEach(function (k) { o.push('- `' + k + '`: ' + src(k)); });
o.push('', '## Apple ProRes target data rates (Mb/s)', '', 'Source: ' + src('apple_prores') + '. Apple\'s 24p/30p/60p rows also cover 23.976/29.97/59.94 (Apple quotes 220 Mb/s for 422 HQ at 1920x1080 29.97). Rates not listed (e.g. 16 or 18 fps frame-for-frame film) are scaled linearly from the nearest listed rate; Apple\'s own table is linear in frame rate (176 x 30/24 = 220). Apple publishes no figure for 4096x3112, so ProRes is not offered at that size. GB/hr = Mb/s x 0.45.', '');
var pr = P.prores.profiles;
o.push('| Frame size | fps | ' + pr.map(function (p) { return p.label.replace('ProRes ', ''); }).join(' | ') + ' |');
o.push('|---|---|' + pr.map(function () { return '---:'; }).join('|') + '|');
P.sizes.forEach(function (s) {
  var t = P.prores.mbps[s.id]; if (!t) { return; }
  Object.keys(t).sort(function (a, b) { return a - b; }).forEach(function (f) { o.push('| ' + s.label + ' | ' + f + 'p | ' + pr.map(function (p) { return t[f][p.id]; }).join(' | ') + ' |'); });
});
o.push('', '## YouTube recommended upload bitrates (Mb/s, H.264)', '', 'Source: ' + src('youtube') + '. Standard frame rate = 24/25/30; high = 48/50/60 (the calculator uses "high" at 48 fps and above). Where YouTube gives a range, the calculator sizes with the upper figure and shows the range.', '');
o.push('| Resolution | SDR standard | SDR high | HDR standard | HDR high |', '|---|---:|---:|---:|---:|');
function rg(a) { return a[0] === a[1] ? String(a[0]) : a[0] + '-' + a[1]; }
P.youtube.res.forEach(function (r) { o.push('| ' + r.label + ' | ' + rg(P.youtube.sdr[r.id].std) + ' | ' + rg(P.youtube.sdr[r.id].high) + ' | ' + rg(P.youtube.hdr[r.id].std) + ' | ' + rg(P.youtube.hdr[r.id].high) + ' |'); });
o.push('', 'YouTube also lists audio: mono 128 kbps, stereo 384 kbps, 5.1 512 kbps (the calculator offers AAC stereo 384 kbps).', '');
o.push('## Image sequences: bytes per frame', '', 'DPX: ' + src('loc_dpx') + '. RGB 8-bit = 3 bytes/pixel; 10-bit = 4 bytes/pixel (three 10-bit samples filled into one 32-bit word, packing method A); 12-bit and 16-bit = 6 bytes/pixel (12-bit samples in 16-bit words). Lines padded to 32-bit boundaries. Plus a ' + P.image.dpx.header_bytes + '-byte header per file (the generic + industry header; files with user data or an 8 KB image offset are slightly larger).', '', 'TIFF: ' + src('tiff6') + '. Uncompressed RGB, 8 or 16 bits per sample (10/12-bit material is stored in 16-bit samples). Tag/IFD overhead (a few hundred bytes) ignored.', '');
var head = ['DPX 8', 'DPX 10', 'DPX 12', 'DPX 16', 'TIFF 8', 'TIFF 16'];
o.push('| Frame size | ' + head.join(' | ') + ' |', '|---|' + head.map(function () { return '---:'; }).join('|') + '|');
P.sizes.forEach(function (s) {
  var cells = [];
  [['dpx', 8], ['dpx', 10], ['dpx', 12], ['dpx', 16], ['tiff', 8], ['tiff', 16]].forEach(function (k) {
    var r = D.storage({ lines: [{ format: '16', fps: 24, seconds: 1 }], deliverables: [{ kind: k[0], size: s.id, depth: k[1] }] });
    cells.push(D.fmtBytes(r.deliverables[0].frame_bytes));
  });
  o.push('| ' + s.label + ' | ' + cells.join(' | ') + ' |');
});
o.push('', '## Uncompressed video (QuickTime)', '', 'Source: ' + src('apple_tn2162') + '. v210 = 10-bit 4:2:2, 48 pixels in 128 bytes per line (widths pad up to a multiple of 48); 2vuy = 8-bit 4:2:2, 2 bytes/pixel; v410 = 10-bit 4:4:4, 4 bytes/pixel. Cross-check: Apple\'s ProRes white paper charts uncompressed 10-bit 4:2:2 1920x1080 29.97 at 1,326 Mb/s; the v210 formula gives ' + (5529600 * 8 * 29.97 / 1e6).toFixed(2) + ' Mb/s.', '');
o.push('| Frame size | ' + P.uncompressed.types.map(function (t) { return t.id + ' @24 Mb/s | ' + t.id + ' @29.97 Mb/s'; }).join(' | ') + ' |', '|---|' + P.uncompressed.types.map(function () { return '---:|---:'; }).join('|') + '|');
P.sizes.forEach(function (s) {
  var c = [];
  P.uncompressed.types.forEach(function (t) { [24, 29.97].forEach(function (f) { var r = D.storage({ lines: [{ format: '16', fps: f, seconds: 1 }], deliverables: [{ kind: 'uncompressed', type: t.id, size: s.id }] }); c.push(r.lines[0].items[0].mbps.toFixed(1)); }); });
  o.push('| ' + s.label + ' | ' + c.join(' | ') + ' |');
});
o.push('', '## H.264 / H.265 / custom', '', 'File size = (video Mb/s + audio kb/s / 1000) x running time / 8. H.264 ' + src('h264') + ' and H.265 ' + src('h265') + ' do not fix a bitrate; the customer or lab chooses it. Container overhead ignored.', '');
o.push('## Running time, frame rates and units', '', '- Film frames = feet x frames per foot (8mm 80, Super 8 72, 16mm 40, 35mm 4-perf 16, 3-perf 21.33, 2-perf 32), or running time x film speed. A full reel uses the nominal reel capacity (e.g. 7 in Super 8 = 400 ft).', '- "Same as film speed" = frame-for-frame (e.g. 18 fps Super 8 delivered as an 18 fps file). A different output rate keeps the running time and adds or drops frames (e.g. 18 fps film delivered at 29.97 fps).', '- GB/TB are decimal (10^9 / 10^12 bytes, as on drive labels); GiB/TiB are binary (2^30 / 2^40). Suggested drive = smallest of ' + P.drives_gb.map(function (g) { return g >= 1000 ? g / 1000 + ' TB' : g + ' GB'; }).join(', ') + ' that holds total x (1 + headroom), default headroom 20%; above 24 TB it suggests several 24 TB drives.', '');
o.push('## Worked example (from the tests)', '');
var ex = D.storage({ lines: [{ format: 's8', fps: 18, reels: 3, size: '7' }], deliverables: [{ kind: 'prores', profile: 'hq', size: 'hd', fps: 24 }, { kind: 'h264', mbps: 20 }] });
o.push('3 x 7 in Super 8 (400 ft, 18 fps, 26:40 each): ProRes 422 HQ HD at 24 fps = 176 Mb/s x 1600 s = ' + D.fmtBytes(ex.lines[0].items[0].bytes_per_reel) + ' per reel; H.264 20 Mb/s frame-for-frame = ' + D.fmtBytes(ex.lines[0].items[1].bytes_per_reel) + ' per reel. Total ' + D.fmtBytes(ex.totals.size.bytes) + ' (' + D.fmtBytes(ex.totals.size.bytes, true) + '); with 20% headroom it needs ' + D.fmtBytes(ex.totals.drive.need_bytes) + ' -> ' + D.fmtDrive(ex.totals.drive) + ' drive.', '');
process.stdout.write(o.join('\n'));
