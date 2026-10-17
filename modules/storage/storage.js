/* [ftt_storage_calculator]. All maths in FTTCore.datarate; DOM built with createElement/textContent only. */
(function () {
  'use strict';
  var Core = window.FTTCore;
  if (!Core || !Core.datarate) { return; }
  var D = Core.datarate, PR = D.PRESETS;
  var FILM_FPS = [16, 18, 24, 25];
  var OUT_FPS = [['film', 'Same as film speed (frame for frame)'], ['23.976', '23.976'], ['24', '24'], ['25', '25'], ['29.97', '29.97'], ['30', '30'], ['50', '50'], ['59.94', '59.94'], ['60', '60']];
  var KINDS = [['prores', 'Apple ProRes (.mov)'], ['h264', 'H.264 MP4'], ['h265', 'H.265 / HEVC MP4'], ['youtube', 'YouTube upload (H.264, recommended bitrate)'], ['dpx', 'DPX image sequence'], ['tiff', 'TIFF image sequence'], ['uncompressed', 'Uncompressed video (.mov)'], ['custom', 'Custom bitrate (Mbps)']];
  var AUDIO = [['0', 'No audio'], ['384', 'AAC stereo 384 kbps (YouTube recommendation)'], ['2304', 'PCM 48 kHz 24-bit stereo (2304 kbps)']];
  var PRESET = {
    'prores-hq-hd': { kind: 'prores', profile: 'hq', size: 'hd', fps: 'film' },
    'prores-422-uhd': { kind: 'prores', profile: '422', size: 'uhd', fps: 'film' },
    'h264': { kind: 'h264', mbps: '20', fps: 'film' },
    'youtube-4k': { kind: 'youtube', res: '2160p', fps: 'film', audio_kbps: '384' },
    'dpx-2k': { kind: 'dpx', size: '2k-fa', depth: '10', fps: 'film' },
    'tiff-4k': { kind: 'tiff', size: '4k-fa', depth: '16', fps: 'film' }
  };
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    if (attrs) { Object.keys(attrs).forEach(function (k) { if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) { n.setAttribute(k, attrs[k] === true ? '' : attrs[k]); } }); }
    if (text != null) { n.textContent = text; }
    return n;
  }
  function clear(n) { while (n.firstChild) { n.removeChild(n.firstChild); } }
  function link(src) {
    var s = PR.sources[src]; if (!s) { return null; }
    var a = el('a', { href: /^https:\/\//.test(s.url) ? s.url : '#', target: '_blank', rel: 'noopener noreferrer' }, s.title); return a;
  }
  var uid = 0;
  function field(label, control) { var id = 'ftt-s' + (++uid), w = el('label', { 'class': 'ftt-field', 'for': id }); control.id = id; w.appendChild(el('span', null, label)); w.appendChild(control); return w; }
  function select(opts, val, onchange) {
    var s = el('select');
    opts.forEach(function (o) { var op = el('option', { value: o[0] }, o[1]); if (String(o[0]) === String(val)) { op.selected = true; } s.appendChild(op); });
    s.addEventListener('change', function () { onchange(s.value); }); return s;
  }
  function input(type, val, attrs, onchange) {
    var a = attrs || {}; a.type = type; var i = el('input', a); i.value = val == null ? '' : val;
    i.addEventListener('input', function () { onchange(i.value); }); return i;
  }
  function fmtGB(b) { return D.fmtBytes(b) + ' (' + D.fmtBytes(b, true) + ')'; }
  function sizeCell(b, attrs) { var td = el('td', attrs || null, D.fmtBytes(b)); td.appendChild(el('span', { 'class': 'ftt-store__bin' }, D.fmtBytes(b, true))); td.setAttribute('aria-label', fmtGB(b)); return td; }
  function fmtMbps(m) { return (m >= 100 ? Math.round(m) : Math.round(m * 100) / 100) + ' Mb/s'; }

  function init(root) {
    var q = function (k) { return root.querySelector('[data-s="' + k + '"]'); };
    var linesBox = q('lines'), delsBox = q('dels'), out = q('out'), head = q('headroom'), fromEst = q('from-estimate');
    var preset = PRESET[root.getAttribute('data-preset')] || PRESET['prores-hq-hd'];
    var state = { lines: [newLine('s8')], dels: [JSON.parse(JSON.stringify(preset))] }, estLines = null;
    function newLine(fmt) { var f = Core.getFormat(fmt) || Core.FORMATS[1]; var r = Core.reels.sizes(f.id); return { format: f.id, fps: f.id === '8' ? '16' : f.id === 's8' ? '18' : '24', reels: '1', mode: 'size', size: r.length ? r[r.length - 1].size : '', feet: '', time: '' }; }
    function request() {
      return {
        headroom_pct: head.value === '' ? '' : Number(head.value),
        lines: state.lines.map(function (l) {
          var o = { format: l.format, fps: l.fps === '' ? '' : Number(l.fps), reels: l.reels === '' ? '' : Number(l.reels) };
          if (l.mode === 'size') { o.size = l.size; }
          else if (l.mode === 'feet') { o.feet = l.feet === '' ? '' : Number(l.feet); }
          else { var s = Core.parseTime(l.time); o.seconds = s == null || isNaN(s) ? (l.time === '' ? '' : -1) : s; }
          return o;
        }),
        deliverables: state.dels.map(function (d) {
          var o = { kind: d.kind, fps: d.fps === 'film' ? 'film' : Number(d.fps), audio_kbps: Number(d.audio_kbps || 0) };
          ['profile', 'size', 'res', 'type'].forEach(function (k) { if (d[k] != null) { o[k] = d[k]; } });
          if (d.depth != null) { o.depth = Number(d.depth); }
          if (d.mbps != null) { o.mbps = d.mbps === '' ? '' : Number(d.mbps); }
          if (d.size === 'custom') { o.width = Number(d.width); o.height = Number(d.height); }
          if (d.kind === 'youtube') { o.hdr = !!d.hdr; }
          return o;
        })
      };
    }
    function drawLines() {
      clear(linesBox);
      state.lines.forEach(function (l, idx) {
        var row = el('div', { 'class': 'ftt-line' }), h = el('div', { 'class': 'ftt-line__head' });
        h.appendChild(el('strong', null, 'Reels ' + (idx + 1)));
        h.appendChild(el('span', { 'class': 'ftt-line__price', 'data-line-info': idx }));
        if (state.lines.length > 1) { var rm = el('button', { type: 'button', 'class': 'ftt-btn ftt-btn--link', 'aria-label': 'Remove reels ' + (idx + 1) }, 'Remove'); rm.addEventListener('click', function () { state.lines.splice(idx, 1); drawLines(); update(); }); h.appendChild(rm); }
        row.appendChild(h);
        var g = el('div', { 'class': 'ftt-grid' });
        g.appendChild(field('Film format', select(Core.FORMATS.map(function (f) { return [f.id, f.label]; }), l.format, function (v) { var n = newLine(v); n.reels = l.reels; n.mode = l.mode; n.feet = l.feet; n.time = l.time; state.lines[idx] = n; drawLines(); update(); })));
        var fpsOpts = FILM_FPS.map(function (f) { return [String(f), f + ' fps']; });
        if (FILM_FPS.indexOf(Number(l.fps)) < 0) { fpsOpts.push([l.fps, l.fps + ' fps']); }
        g.appendChild(field('Film speed', select(fpsOpts, l.fps, function (v) { l.fps = v; update(); })));
        g.appendChild(field('How many reels', input('number', l.reels, { min: 1, max: 999, step: 1, inputmode: 'numeric' }, function (v) { l.reels = v; update(); })));
        g.appendChild(field('Length from', select([['size', 'Reel size (full reel)'], ['feet', 'Total feet'], ['time', 'Total running time']], l.mode, function (v) { l.mode = v; drawLines(); update(); })));
        if (l.mode === 'size') {
          g.appendChild(field('Reel size', select(Core.reels.sizes(l.format).map(function (r) { return [r.size, r.label + ' \u2013 ' + r.feet + ' ft']; }), l.size, function (v) { l.size = v; update(); })));
        } else if (l.mode === 'feet') {
          g.appendChild(field('Total feet (all these reels)', input('number', l.feet, { min: 1, max: 100000, step: 'any', inputmode: 'decimal' }, function (v) { l.feet = v; update(); })));
        } else {
          g.appendChild(field('Total running time (h:mm:ss)', input('text', l.time, { inputmode: 'numeric', placeholder: '1:30:00', autocomplete: 'off' }, function (v) { l.time = v; update(); })));
        }
        row.appendChild(g);
        row.appendChild(el('p', { 'class': 'ftt-msg ftt-msg--bad', 'data-line-err': idx, role: 'alert' }));
        linesBox.appendChild(row);
      });
    }
    function sizeOpts(kind) {
      var s = PR.sizes.filter(function (z) { return kind !== 'prores' || PR.prores.mbps[z.id]; }).map(function (z) { return [z.id, z.label]; });
      if (kind !== 'prores') { s.push(['custom', 'Custom size\u2026']); }
      return s;
    }
    function drawDels() {
      clear(delsBox);
      state.dels.forEach(function (d, idx) {
        var row = el('div', { 'class': 'ftt-line' }), h = el('div', { 'class': 'ftt-line__head' });
        h.appendChild(el('strong', null, 'Deliverable ' + (idx + 1)));
        h.appendChild(el('span', { 'class': 'ftt-line__price', 'data-del-info': idx }));
        if (state.dels.length > 1) { var rm = el('button', { type: 'button', 'class': 'ftt-btn ftt-btn--link', 'aria-label': 'Remove deliverable ' + (idx + 1) }, 'Remove'); rm.addEventListener('click', function () { state.dels.splice(idx, 1); drawDels(); update(); }); h.appendChild(rm); }
        row.appendChild(h);
        var g = el('div', { 'class': 'ftt-grid' });
        g.appendChild(field('File type', select(KINDS, d.kind, function (v) {
          var n = { kind: v, fps: d.fps, audio_kbps: d.audio_kbps };
          if (v === 'prores') { n.profile = 'hq'; n.size = 'hd'; }
          else if (v === 'youtube') { n.res = '1080p'; n.hdr = false; }
          else if (v === 'dpx') { n.size = '2k-fa'; n.depth = '10'; }
          else if (v === 'tiff') { n.size = 'hd'; n.depth = '16'; }
          else if (v === 'uncompressed') { n.type = 'v210'; n.size = 'hd'; }
          else { n.mbps = v === 'h265' ? '10' : '20'; }
          state.dels[idx] = n; drawDels(); update();
        })));
        if (d.kind === 'prores') { g.appendChild(field('ProRes flavour', select(PR.prores.profiles.map(function (p) { return [p.id, p.label]; }), d.profile, function (v) { d.profile = v; update(); }))); }
        if (d.kind === 'uncompressed') { g.appendChild(field('Pixel format', select(PR.uncompressed.types.map(function (t) { return [t.id, t.label]; }), d.type, function (v) { d.type = v; update(); }))); }
        if (d.kind === 'youtube') {
          g.appendChild(field('Resolution', select(PR.youtube.res.map(function (r) { return [r.id, r.label]; }), d.res, function (v) { d.res = v; update(); })));
          var cb = el('input', { type: 'checkbox' }); cb.checked = !!d.hdr; cb.addEventListener('change', function () { d.hdr = cb.checked; update(); });
          g.appendChild(field('HDR upload', cb));
        }
        if (d.size != null) {
          g.appendChild(field('Frame size', select(sizeOpts(d.kind), d.size, function (v) { d.size = v; if (v === 'custom') { d.width = d.width || '2048'; d.height = d.height || '1556'; } drawDels(); update(); })));
          if (d.size === 'custom') {
            g.appendChild(field('Width (px)', input('number', d.width, { min: 16, max: 16384, step: 1, inputmode: 'numeric' }, function (v) { d.width = v; update(); })));
            g.appendChild(field('Height (px)', input('number', d.height, { min: 16, max: 16384, step: 1, inputmode: 'numeric' }, function (v) { d.height = v; update(); })));
          }
        }
        if (d.kind === 'dpx' || d.kind === 'tiff') { g.appendChild(field('Bit depth', select(Object.keys(PR.image[d.kind].depths).map(function (k) { return [k, k + '-bit']; }), d.depth, function (v) { d.depth = v; update(); }))); }
        if (d.mbps != null) { g.appendChild(field('Video bitrate (Mbps)', input('number', d.mbps, { min: 0.01, max: 20000, step: 'any', inputmode: 'decimal' }, function (v) { d.mbps = v; update(); }))); }
        g.appendChild(field(d.kind === 'dpx' || d.kind === 'tiff' ? 'Frames per second of film' : 'Output frame rate', select(OUT_FPS, d.fps, function (v) { d.fps = v; update(); })));
        if (d.kind !== 'dpx' && d.kind !== 'tiff') { g.appendChild(field('Audio', select(AUDIO, String(d.audio_kbps || 0), function (v) { d.audio_kbps = v; update(); }))); }
        row.appendChild(g);
        var srcKey = d.kind === 'prores' ? 'apple_prores' : d.kind === 'youtube' ? 'youtube' : d.kind === 'dpx' ? 'loc_dpx' : d.kind === 'tiff' ? 'tiff6' : d.kind === 'uncompressed' ? 'apple_tn2162' : d.kind === 'custom' ? '' : d.kind;
        var note = el('p', { 'class': 'ftt-store__note', 'data-del-note': idx });
        var a = link(srcKey); if (a) { note.appendChild(document.createTextNode('Source: ')); note.appendChild(a); }
        row.appendChild(note);
        row.appendChild(el('p', { 'class': 'ftt-msg ftt-msg--bad', 'data-del-err': idx, role: 'alert' }));
        delsBox.appendChild(row);
      });
    }
    function drawSources() {
      var box = q('sources'), ul = el('ul');
      Object.keys(PR.sources).forEach(function (k) { var li = el('li'); li.appendChild(link(k)); ul.appendChild(li); });
      box.appendChild(ul);
      [
        'ProRes sizes use Apple\u2019s published target data rates. Apple\u2019s 24p, 30p and 60p figures also cover 23.976, 29.97 and 59.94. Other speeds (for example 16 or 18 fps frame-for-frame) are scaled from the nearest listed rate. Real ProRes files vary with picture content.',
        'H.264, H.265 and custom sizes are bitrate \u00d7 running time. YouTube uses the upper end of YouTube\u2019s recommended range; 48 fps and above use the high-frame-rate row.',
        'DPX: 8-bit RGB = 3 bytes per pixel, 10-bit = 4 bytes (three 10-bit values in one 32-bit word, method A), 12- and 16-bit = 6 bytes; lines padded to 4 bytes; plus a 2048-byte header per frame (files with larger user-data headers will be slightly bigger). TIFF: uncompressed RGB, 3 or 6 bytes per pixel; tag overhead ignored.',
        'Uncompressed: v210 packs 48 pixels into 128 bytes per line (so 1280-wide lines pad to 1296 pixels), 2vuy = 2 bytes per pixel, v410 = 4 bytes per pixel. Container overhead is ignored.',
        'If the output frame rate differs from the film speed, the running time stays the same and frames are added or dropped (for example 18 fps film delivered at 29.97). Image sequences then hold one file per output frame.',
        'GB and TB are decimal (1 GB = 1,000,000,000 bytes), as drive makers use. GiB and TiB are binary (1 GiB = 1,073,741,824 bytes), as some operating systems report. Drive sizes are common retail capacities. Rates checked ' + PR.as_of + '.'
      ].forEach(function (t) { box.appendChild(el('p', null, t)); });
    }
    function update() {
      var r = D.storage(request());
      Array.prototype.forEach.call(root.querySelectorAll('[data-line-err],[data-del-err],[data-line-info],[data-del-info]'), function (n) { n.textContent = ''; });
      r.errors.forEach(function (e) {
        var n = e.line >= 0 ? root.querySelector('[data-line-err="' + e.line + '"]') : e.deliverable >= 0 ? root.querySelector('[data-del-err="' + e.deliverable + '"]') : null;
        if (n) { n.textContent = e.message; }
      });
      r.lines.forEach(function (l) {
        var n = root.querySelector('[data-line-info="' + l.index + '"]');
        if (n) { n.textContent = Math.round(l.feet_per_reel).toLocaleString() + ' ft \u00b7 ' + Core.formatTime(l.seconds_per_reel) + ' per reel'; }
      });
      r.deliverables.forEach(function (d) {
        var n = root.querySelector('[data-del-info="' + d.index + '"]'), it = r.lines.length ? r.lines[0].items.filter(function (x) { return x.deliverable === d.index; })[0] : null;
        if (n && it) { n.textContent = fmtMbps(it.mbps) + (it.range && it.range[0] !== it.range[1] ? ' (YouTube range ' + it.range[0] + '\u2013' + it.range[1] + ')' : '') + (it.scaled ? ' \u00b7 scaled from Apple\u2019s ' + D.proresMbps(d.size, d.profile, it.out_fps).listed + 'p figure' : '') + (d.frame_bytes ? ' \u00b7 ' + D.fmtBytes(d.frame_bytes) + ' per frame' : ''); }
      });
      clear(out);
      if (!r.lines.length || !r.deliverables.length) { out.appendChild(el('p', { 'class': 'ftt-msg' }, 'Add at least one reel and one deliverable.')); return; }
      var tb = el('table', { 'class': 'ftt-table ftt-store__table' }), th = el('thead'), tr = el('tr'), body = el('tbody');
      ['Reels', 'Deliverable', 'Per reel', 'Total'].forEach(function (h) { tr.appendChild(el('th', { scope: 'col' }, h)); });
      th.appendChild(tr); tb.appendChild(th);
      var names = {}; r.deliverables.forEach(function (d) { names[d.index] = d.label; });
      r.lines.forEach(function (l) {
        l.items.forEach(function (it, k) {
          var row = el('tr');
          row.appendChild(el('td', null, k === 0 ? l.reels + ' \u00d7 ' + l.format_label + ' @ ' + l.fps + ' fps (' + Core.formatTime(l.seconds_per_reel) + ' each)' : ''));
          row.appendChild(el('td', null, names[it.deliverable] + (it.out_fps !== l.fps ? ' @ ' + it.out_fps + ' fps' : '')));
          row.appendChild(sizeCell(it.bytes_per_reel));
          row.appendChild(sizeCell(it.bytes));
          body.appendChild(row);
        });
      });
      tb.appendChild(body);
      var tf = el('tfoot');
      if (r.deliverables.length > 1) {
        r.deliverables.forEach(function (d) { var row = el('tr'); row.appendChild(el('th', { scope: 'row', colspan: 3 }, 'All reels: ' + d.label)); row.appendChild(sizeCell(d.bytes)); tf.appendChild(row); });
      }
      var s = r.totals.size, row2 = el('tr', { 'class': 'ftt-store__grand' });
      row2.appendChild(el('th', { scope: 'row', colspan: 3 }, 'Total (' + Core.formatTime(r.totals.seconds) + ' of film' + (r.deliverables.length > 1 ? ', all deliverables' : '') + ')'));
      row2.appendChild(sizeCell(s.bytes, { 'data-s': 'total' }));
      tf.appendChild(row2); tb.appendChild(tf);
      out.appendChild(tb);
      var dl = el('dl', { 'class': 'ftt-store__sum' });
      function pair(k, v, attr) { dl.appendChild(el('dt', null, k)); dl.appendChild(el('dd', attr || null, v)); }
      pair('Decimal (drive labels)', (s.gb < 1000 ? (Math.round(s.gb * 100) / 100) + ' GB' : (Math.round(s.tb * 1000) / 1000) + ' TB'));
      pair('Binary (macOS / Windows may show)', (s.gib < 1024 ? (Math.round(s.gib * 100) / 100) + ' GiB' : (Math.round(s.tib * 1000) / 1000) + ' TiB'));
      if (r.totals.drive) { pair('Suggested drive (' + r.totals.headroom_pct + '% headroom, needs ' + D.fmtBytes(r.totals.drive.need_bytes) + ')', D.fmtDrive(r.totals.drive), { 'data-s': 'drive' }); }
      out.appendChild(dl);
      if (r.errors.length) { out.appendChild(el('p', { 'class': 'ftt-msg ftt-msg--bad' }, 'Some rows have a problem and are left out; see the messages above.')); }
    }
    q('add-line').addEventListener('click', function () { if (state.lines.length < PR.limits.lines) { state.lines.push(newLine(state.lines[state.lines.length - 1].format)); drawLines(); update(); } });
    q('add-del').addEventListener('click', function () { if (state.dels.length < PR.limits.deliverables) { state.dels.push({ kind: 'h264', mbps: '20', fps: 'film', audio_kbps: '0' }); drawDels(); update(); } });
    head.addEventListener('input', update);
    function gotEst(v) { estLines = Array.isArray(v) ? v : null; fromEst.hidden = !(estLines && estLines.length); }
    document.addEventListener('ftt:estimate-lines', function (ev) { gotEst(ev.detail); });
    gotEst(window.FTTEstimateLines); /* estimate builder may have run first */
    fromEst.addEventListener('click', function () {
      if (!estLines) { return; }
      var ls = estLines.slice(0, PR.limits.lines).map(function (e) {
        var n = newLine(String(e.format) === '35' ? '35-4' : String(e.format)); n.reels = String(e.reels || 1);
        if (e.feet != null && e.feet !== '') { n.mode = 'feet'; n.feet = String(e.feet); } else if (e.size) { n.mode = 'size'; n.size = String(e.size); }
        return n;
      });
      if (ls.length) { state.lines = ls; drawLines(); update(); }
    });
    drawLines(); drawDels(); drawSources(); update();
  }
  function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-ftt-storage]'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
