/*!
 * FTTCore 1.1.0 - FTT portable core.
 * Film length / time / frames, reel size estimates, price quotes, SMPTE timecode and storage / data rates.
 * No DOM, no WordPress, no network. Browser global (window.FTTCore), CommonJS or AMD,
 * so the same file can ship inside a mobile app. PHP mirror: includes/core/*.php
 * (kept in step by the shared files in core/test-vectors/).
 */
(function (root, factory) {
  'use strict';
  if (typeof define === 'function' && define.amd) { define([], factory); }
  else if (typeof module === 'object' && module.exports) { module.exports = factory(); }
  else { root.FTTCore = factory(); }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /* ------------------------------------------------------------------ length */
  var M_PER_FT = 0.3048;
  var FORMATS = [
    { id: '8',    label: '8mm (Regular / Double 8)', fpf: 80,     stdFps: [16, 18, 24] },
    { id: 's8',   label: 'Super 8',                  fpf: 72,     stdFps: [18, 24, 25] },
    { id: '16',   label: '16mm',                     fpf: 40,     stdFps: [16, 18, 24, 25] },
    { id: '35-4', label: '35mm 4-perf',              fpf: 16,     stdFps: [24, 25] },
    { id: '35-3', label: '35mm 3-perf',              fpf: 64 / 3, stdFps: [24, 25] },
    { id: '35-2', label: '35mm 2-perf',              fpf: 32,     stdFps: [24, 25] }
  ];
  var SPEEDS = [16, 18, 24, 25, 29.97, 30];

  function getFormat(id) {
    for (var i = 0; i < FORMATS.length; i++) { if (FORMATS[i].id === String(id)) { return FORMATS[i]; } }
    return null;
  }
  function parseTime(text) {
    var s = String(text == null ? '' : text).trim();
    if (!s) { return NaN; }
    var parts = s.split(':');
    if (parts.length > 3) { return NaN; }
    var total = 0;
    for (var i = 0; i < parts.length; i++) {
      if (!/^\d*\.?\d+$|^\d+\.$/.test(parts[i])) { return NaN; }
      total = total * 60 + parseFloat(parts[i]);
    }
    return total;
  }
  function formatTime(seconds) {
    if (!isFinite(seconds) || seconds < 0) { return ''; }
    var t = Math.round(seconds * 100) / 100;
    var h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60);
    var sec = Math.round((t - h * 3600 - m * 60) * 100) / 100;
    var ss = sec.toFixed(2).replace(/\.00$/, '');
    if (sec < 10) { ss = '0' + ss; }
    return h ? h + ':' + (m < 10 ? '0' : '') + m + ':' + ss : m + ':' + ss;
  }
  function feetPerMinute(fpf, fps) { return fps * 60 / fpf; }
  function compute(opts) {
    var fpf = Number(opts && opts.fpf), fps = Number(opts && opts.fps);
    if (!(fpf > 0) || !(fps > 0)) { return null; }
    var v = opts.value, frames;
    switch (opts.from) {
      case 'feet':    frames = parseFloat(v) * fpf; break;
      case 'meters':  frames = parseFloat(v) / M_PER_FT * fpf; break;
      case 'time':    frames = parseTime(v) * fps; break;
      case 'seconds': frames = parseFloat(v) * fps; break;
      case 'frames':  frames = parseFloat(v); break;
      default: return null;
    }
    if (!isFinite(frames) || frames < 0) { return null; }
    var feet = frames / fpf;
    var wholeFeet = Math.floor(feet + 1e-9);
    var extra = Math.round(frames - wholeFeet * fpf);
    if (extra >= Math.round(fpf) && Math.round(fpf) === fpf) { wholeFeet += 1; extra = 0; }
    return { frames: frames, feet: feet, meters: feet * M_PER_FT, seconds: frames / fps,
      ftPerMin: feetPerMinute(fpf, fps), wholeFeet: wholeFeet, extraFrames: Math.max(0, extra) };
  }
  function round(n, d) { var f = Math.pow(10, d); return String(Math.round(n * f) / f); }

  /* ------------------------------------------------------------------- reels */
  /* Nominal capacities (feet of film a full reel of that diameter holds). */
  var REELS = [
    { format: '8',  size: '3',  label: '3 in',  feet: 50 },
    { format: '8',  size: '5',  label: '5 in',  feet: 200 },
    { format: '8',  size: '6',  label: '6 in',  feet: 300 },
    { format: '8',  size: '7',  label: '7 in',  feet: 400 },
    { format: 's8', size: '3',  label: '3 in',  feet: 50 },
    { format: 's8', size: '5',  label: '5 in',  feet: 200 },
    { format: 's8', size: '6',  label: '6 in',  feet: 300 },
    { format: 's8', size: '7',  label: '7 in',  feet: 400 },
    { format: '16', size: '3',  label: '3 in',  feet: 50 },
    { format: '16', size: '4',  label: '4 in',  feet: 100 },
    { format: '16', size: '5',  label: '5 in',  feet: 200 },
    { format: '16', size: '7',  label: '7 in',  feet: 400 },
    { format: '16', size: '10', label: '10.5 in', feet: 800 },
    { format: '16', size: '12', label: '12 in', feet: 1200 },
    { format: '16', size: '14', label: '14 in', feet: 1600 },
    { format: '35', size: '6',  label: '6 in (200 ft)', feet: 200 },
    { format: '35', size: '10', label: '10.5 in (1000 ft)', feet: 1000 },
    { format: '35', size: '15', label: '15 in (2000 ft)', feet: 2000 }
  ];
  /* Approximate base thickness in inches (acetate / polyester). Real stock varies by about 10%. */
  var THICKNESS = {
    '8':  { acetate: 0.0055, polyester: 0.0045 },
    's8': { acetate: 0.0055, polyester: 0.0045 },
    '16': { acetate: 0.0060, polyester: 0.0050 },
    '35': { acetate: 0.0060, polyester: 0.0050 }
  };
  var HUB = { '8': 1.25, 's8': 1.25, '16': 2.0, '35': 2.0 };
  function reelGroup(format) { var f = String(format); return f.indexOf('35') === 0 ? '35' : f; }
  function reelSizes(format) {
    var g = reelGroup(format), out = [];
    for (var i = 0; i < REELS.length; i++) { if (REELS[i].format === g) { out.push(REELS[i]); } }
    return out;
  }
  /**
   * Feet of film wound on a reel: L = pi * (D^2 - d^2) / (4 t), in inches, then / 12.
   * opts: { outer, hub, thickness, unit: 'in'|'cm' } (hub and thickness default per format).
   */
  function feetFromDiameter(opts) {
    opts = opts || {};
    var k = opts.unit === 'cm' ? 1 / 2.54 : 1;
    var g = reelGroup(opts.format || '8');
    var D = Number(opts.outer) * k;
    var d = opts.hub != null && opts.hub !== '' ? Number(opts.hub) * k : (HUB[g] || 1.25);
    var base = opts.base === 'polyester' ? 'polyester' : 'acetate';
    var t = Number(opts.thickness) > 0 ? Number(opts.thickness) : (THICKNESS[g] || THICKNESS['8'])[base];
    if (!(D > 0) || !(d >= 0) || D <= d || D > 40) { return null; }
    var feet = Math.PI * (D * D - d * d) / (4 * t) / 12;
    return { feet: feet, low: feet * 0.9, high: feet * 1.1, hub: d, thickness: t };
  }

  /* ----------------------------------------------------------------- pricing */
  var LIMITS = { lines: 50, reels: 999, feet: 100000, addons: 20, qty: 999 };
  function toCents(mills) { return Math.floor((mills + 5) / 10); }
  function int(v) { var n = Number(v); return isFinite(n) ? Math.round(n) : 0; }
  function findBy(list, pred) { list = list || []; for (var i = 0; i < list.length; i++) { if (pred(list[i])) { return list[i]; } } return null; }
  function applies(item, format) {
    return !item.formats || !item.formats.length || item.formats.indexOf(String(format)) !== -1;
  }
  function err(line, code, message) { return { line: line, code: code, message: message }; }

  /**
   * quote(table, request) -> priced estimate.
   * table: price table (see core/data/*.json). All money is integer mills (1/1000 of a currency unit).
   * request: { lines: [{ format, service, reels, size?, feet?, addons: [] }], order_addons: [] }
   * Result money is in integer cents. Lines with errors are reported and left out of the totals.
   */
  function quote(table, request) {
    table = table || {}; request = request || {};
    var fees = table.fees || {};
    var minFeet = Math.max(0, int(fees.min_feet_per_reel));
    var lines = [], errors = [], rq = Array.isArray(request.lines) ? request.lines : [];
    if (rq.length > LIMITS.lines) { errors.push(err(-1, 'too_many_lines', 'Too many lines in one estimate.')); rq = rq.slice(0, LIMITS.lines); }
    var sumCents = 0, sumBill = 0, sumFeet = 0, sumSeconds = 0;

    for (var i = 0; i < rq.length; i++) {
      var L = rq[i] || {};
      var fmt = findBy(table.formats, function (f) { return f.id === String(L.format); });
      if (!fmt) { errors.push(err(i, 'bad_format', 'Unknown film format.')); continue; }
      var svc = findBy(table.services, function (s) { return s.id === String(L.service); });
      if (!svc) { errors.push(err(i, 'bad_service', 'Unknown service.')); continue; }
      var reels = L.reels == null || L.reels === '' ? 1 : Number(L.reels);
      if (!(reels >= 1 && reels <= LIMITS.reels && Math.floor(reels) === reels)) { errors.push(err(i, 'bad_reels', 'Number of reels must be a whole number from 1 to 999.')); continue; }
      var hasFeet = L.feet != null && L.feet !== '';
      var feet = hasFeet ? Number(L.feet) : 0;
      if (hasFeet && !(feet > 0 && feet <= LIMITS.feet)) { errors.push(err(i, 'bad_feet', 'Footage must be more than 0 and at most 100000 feet.')); continue; }
      var size = L.size == null ? '' : String(L.size), reel = null;
      if (size !== '') {
        reel = findBy(table.reels, function (r) { return r.format === fmt.id && r.size === size; });
        if (!reel) { errors.push(err(i, 'bad_size', 'That reel size is not listed for this format.')); continue; }
      }
      if (!hasFeet && !reel) { errors.push(err(i, 'need_length', 'Enter a reel size or the footage.')); continue; }
      var totalFeet = hasFeet ? feet : reel.feet * reels;

      var reelRate = !hasFeet && reel ? findBy(table.rates, function (r) { return r.format === fmt.id && r.service === svc.id && r.unit === 'reel' && r.size === size; }) : null;
      var footRate = findBy(table.rates, function (r) { return r.format === fmt.id && r.service === svc.id && r.unit === 'foot'; });
      var method, baseMills, bill;
      if (reelRate) {
        method = 'reel'; baseMills = int(reelRate.price) * reels; bill = reel.feet * reels;
      } else if (footRate) {
        method = 'foot';
        var per = Math.max(minFeet, Math.ceil(totalFeet / reels - 1e-9));
        bill = per * reels; baseMills = int(footRate.price) * bill;
      } else { errors.push(err(i, 'no_rate', 'This service is not offered for this format.')); continue; }

      var ids = Array.isArray(L.addons) ? L.addons.slice(0, LIMITS.addons) : [], seen = {}, adds = [], addCents = 0, bad = null;
      for (var a = 0; a < ids.length; a++) {
        var id = String(ids[a]); if (seen[id]) { continue; } seen[id] = 1;
        var ad = findBy(table.addons, function (x) { return x.id === id; });
        if (!ad) { bad = err(i, 'bad_addon', 'Unknown add-on.'); break; }
        if (ad.unit === 'order' || ad.unit === 'custom') { bad = err(i, 'order_addon_on_line', 'That add-on applies to the whole order.'); break; }
        if (!applies(ad, fmt.id)) { bad = err(i, 'addon_not_available', 'That add-on is not available for this format.'); break; }
        var c = toCents(int(ad.price) * (ad.unit === 'reel' ? reels : bill));
        adds.push({ id: ad.id, label: ad.label, cents: c }); addCents += c;
      }
      if (bad) { errors.push(bad); continue; }
      var baseCents = toCents(baseMills);
      var seconds = Math.round(totalFeet * fmt.fpf / fmt.fps);
      lines.push({ index: i, format: fmt.id, format_label: fmt.label, service: svc.id, service_label: svc.label,
        reels: reels, size: size, feet: totalFeet, billable_feet: bill, method: method, seconds: seconds,
        base_cents: baseCents, addons: adds, addons_cents: addCents, line_cents: baseCents + addCents });
      sumCents += baseCents + addCents; sumBill += bill; sumFeet += totalFeet; sumSeconds += seconds;
    }

    var pct = 0, tiers = (table.tiers || []).slice().sort(function (x, y) { return int(y.min_feet) - int(x.min_feet); });
    for (var t = 0; t < tiers.length; t++) { if (sumBill >= int(tiers[t].min_feet)) { pct = Math.min(100, Math.max(0, int(tiers[t].pct))); break; } }
    var discount = Math.floor((sumCents * pct + 50) / 100);

    var orderAdds = [], orderCents = 0, oids = Array.isArray(request.order_addons) ? request.order_addons.slice(0, LIMITS.addons) : [], oseen = {};
    for (var o = 0; o < oids.length; o++) {
      // An order add-on is an id, or { id, qty } for a per-custom-unit add-on (qty defaults to 1).
      var raw = oids[o], isObj = raw !== null && typeof raw === 'object' && !Array.isArray(raw);
      var oid = isObj ? (raw.id === undefined || raw.id === null ? '' : String(raw.id)) : String(raw); if (oseen[oid]) { continue; } oseen[oid] = 1;
      var oa = findBy(table.addons, function (x) { return x.id === oid && (x.unit === 'order' || x.unit === 'custom'); });
      if (!oa) { errors.push(err(-1, 'bad_order_addon', 'Unknown order add-on.')); continue; }
      if (oa.unit === 'custom') {
        var qv = isObj && raw.qty !== undefined && raw.qty !== null ? raw.qty : 1;
        var qn = typeof qv === 'number' && isFinite(qv) && Math.floor(qv) === qv ? qv : (typeof qv === 'string' && /^[0-9]{1,4}$/.test(qv) ? Number(qv) : NaN);
        if (!(qn >= 1 && qn <= LIMITS.qty)) { errors.push(err(-1, 'bad_quantity', 'Quantity must be a whole number from 1 to 999.')); continue; }
        var qc = toCents(int(oa.price) * qn);
        orderAdds.push({ id: oa.id, label: oa.label, cents: qc, qty: qn, unit_label: oa.unit_label || '', unit_mills: int(oa.price) }); orderCents += qc;
        continue;
      }
      var oc = toCents(int(oa.price)); orderAdds.push({ id: oa.id, label: oa.label, cents: oc }); orderCents += oc;
    }
    var setup = lines.length ? toCents(int(fees.setup)) : 0;
    var minimum = toCents(int(fees.min_order));
    var pre = lines.length ? sumCents - discount + orderCents + setup : 0;
    var adjust = lines.length && pre < minimum ? minimum - pre : 0;
    return {
      ok: errors.length === 0 && lines.length > 0,
      currency: table.currency || 'USD', status: table.status || 'example', label: table.label || '', as_of: table.as_of || '',
      lines: lines, errors: errors,
      totals: { feet: sumFeet, billable_feet: sumBill, seconds: sumSeconds, lines_cents: sumCents,
        discount_pct: pct, discount_cents: discount, order_addons: orderAdds, order_addons_cents: orderCents,
        setup_cents: setup, minimum_cents: minimum, minimum_adjust_cents: adjust, total_cents: pre + adjust }
    };
  }
  function formatMoney(cents, currency) {
    var neg = cents < 0, c = Math.abs(int(cents)), d = String(Math.floor(c / 100)), r = c % 100;
    d = d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var sym = { USD: '$', CAD: 'CA$', EUR: '\u20ac', GBP: '\u00a3', AUD: 'A$' }[currency || 'USD'] || ((currency || '') + ' ');
    return (neg ? '-' : '') + sym + d + '.' + (r < 10 ? '0' : '') + r;
  }
  /** Mills (integer) -> "0.32" style string with at least 2 decimals, for display of unit prices. */
  function formatMills(mills, currency) {
    var m = int(mills);
    if (m % 10 === 0) { return formatMoney(m / 10, currency); }
    var s = formatMoney(Math.floor(m / 10), currency);
    return s + String(m % 10);
  }

  /* ---------------------------------------------------------------- timecode */
  var TC_RATES = { '23.976': [24000, 1001, 24, 0], '24': [24, 1, 24, 0], '25': [25, 1, 25, 0],
    '29.97df': [30000, 1001, 30, 2], '29.97ndf': [30000, 1001, 30, 0], '30': [30, 1, 30, 0],
    '59.94df': [60000, 1001, 60, 4], '60': [60, 1, 60, 0] };
  var TC_LABELS = { '23.976': '23.976', '24': '24', '25': '25', '29.97df': '29.97 DF', '29.97ndf': '29.97 NDF', '30': '30', '59.94df': '59.94 DF', '60': '60' };
  function tcNorm(key) {
    key = String(key || '').toLowerCase().replace(/\s+/g, '');
    if (key === '29.97') { key = '29.97ndf'; }
    if (key === '59.94') { key = '59.94df'; }
    if (!TC_RATES[key]) { throw new Error('Unsupported frame rate: ' + key); }
    return key;
  }
  function tcInfo(key) { key = tcNorm(key); var r = TC_RATES[key]; return { key: key, num: r[0], den: r[1], base: r[2], drop: r[3], label: TC_LABELS[key], fps: r[0] / r[1] }; }
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  var timecode = {
    RATES: TC_RATES, info: tcInfo, norm: tcNorm,
    secondsToFrames: function (sec, key) { var i = tcInfo(key); return Math.floor(sec * i.num / i.den + 1e-6); },
    framesToSeconds: function (f, key) { var i = tcInfo(key); return f * i.den / i.num; },
    framesToTC: function (f, key) {
      var i = tcInfo(key), base = i.base, d = i.drop, neg = f < 0; f = Math.abs(Math.round(f));
      if (d) {
        var per10 = base * 600 - d * 9, perMin = base * 60 - d;
        var tens = Math.floor(f / per10), rem = f % per10;
        f += d * 9 * tens + (rem > d ? d * Math.floor((rem - d) / perMin) : 0);
      }
      var ff = f % base, s = Math.floor(f / base), hh = Math.floor(s / 3600);
      return (neg ? '-' : '') + pad2(hh) + ':' + pad2(Math.floor(s / 60) % 60) + ':' + pad2(s % 60) + (d ? ';' : ':') + pad2(ff);
    },
    tcToFrames: function (tc, key) {
      var i = tcInfo(key), m = /^(-)?(\d+):(\d\d):(\d\d)[:;.](\d\d)$/.exec(String(tc).trim());
      if (!m) { throw new Error('Bad timecode: ' + tc); }
      var hh = +m[2], mm = +m[3], ss = +m[4], ff = +m[5];
      if (mm > 59 || ss > 59 || ff >= i.base) { throw new Error('Bad timecode: ' + tc); }
      if (i.drop && ss === 0 && mm % 10 !== 0 && ff < i.drop) { throw new Error('Timecode does not exist in drop-frame: ' + tc); }
      var totalMin = hh * 60 + mm;
      var fr = (hh * 3600 + mm * 60 + ss) * i.base + ff - i.drop * (totalMin - Math.floor(totalMin / 10));
      return m[1] ? -fr : fr;
    }
  };

  /* ---------------------------------------------------------------- datarate */
  /* Storage / data-rate maths. Presets are a copy of core/data/datarate-presets.json
     (tests fail if they drift; regenerate with: node tools/embed-presets.js). */
  /* BEGIN DATARATE PRESETS */
  var DR = {"version":"1.0.0","as_of":"2026-10-16","note":"Shared by core/ftt-core.js (embedded copy, kept equal by tests) and includes/core/class-ftt-datarate.php. Edit here, then run: node tools/embed-presets.js","sources":{"apple_prores":{"title":"Apple ProRes White Paper (April 2022), Target Data Rates tables, pp. 19-21","url":"https://www.apple.com/final-cut-pro/docs/Apple_ProRes_White_Paper.pdf"},"youtube":{"title":"YouTube Help: YouTube recommended upload encoding settings (answer 1722171)","url":"https://support.google.com/youtube/answer/1722171"},"apple_tn2162":{"title":"Apple Technical Note TN2162: Uncompressed Y'CbCr Video in QuickTime Files ('2vuy', 'v210', 'v410' layouts)","url":"https://developer.apple.com/library/archive/technotes/tn2162/_index.html"},"loc_dpx":{"title":"Library of Congress FDD000178: Digital Moving-Picture Exchange (DPX), Version 2.0 (SMPTE ST 268; 10-bit components filled into 32-bit words, 12-bit into 16-bit words)","url":"https://www.loc.gov/preservation/digital/formats/fdd/fdd000178.shtml"},"tiff6":{"title":"TIFF Revision 6.0 (Adobe, 1992): uncompressed RGB, BitsPerSample 8 or 16","url":"https://download.osgeo.org/libtiff/doc/TIFF6.pdf"},"h264":{"title":"ITU-T H.264 (AVC). File size = bitrate x time; the bitrate is your choice","url":"https://www.itu.int/rec/T-REC-H.264"},"h265":{"title":"ITU-T H.265 (HEVC). File size = bitrate x time; the bitrate is your choice","url":"https://www.itu.int/rec/T-REC-H.265"}},"sizes":[{"id":"sd486","label":"SD NTSC 720×486","w":720,"h":486},{"id":"sd576","label":"SD PAL 720×576","w":720,"h":576},{"id":"hd","label":"HD 1920×1080","w":1920,"h":1080},{"id":"2k-dci","label":"2K DCI 2048×1080","w":2048,"h":1080},{"id":"2k-fa","label":"2K full aperture 2048×1556","w":2048,"h":1556},{"id":"uhd","label":"UHD 4K 3840×2160","w":3840,"h":2160},{"id":"4k-dci","label":"4K DCI 4096×2160","w":4096,"h":2160},{"id":"4k-fa","label":"4K full aperture 4096×3112","w":4096,"h":3112}],"prores":{"source":"apple_prores","fps_tolerance":0.0011,"profiles":[{"id":"proxy","label":"ProRes 422 Proxy"},{"id":"lt","label":"ProRes 422 LT"},{"id":"422","label":"ProRes 422"},{"id":"hq","label":"ProRes 422 HQ"},{"id":"4444","label":"ProRes 4444 (no alpha)"},{"id":"4444xq","label":"ProRes 4444 XQ (no alpha)"}],"mbps":{"sd486":{"24":{"422":34,"4444":75,"proxy":10,"lt":23,"hq":50,"4444xq":113},"30":{"422":42,"4444":94,"proxy":12,"lt":29,"hq":63,"4444xq":141}},"sd576":{"25":{"422":41,"4444":92,"proxy":12,"lt":28,"hq":61,"4444xq":138}},"hd":{"24":{"422":117,"4444":264,"proxy":36,"lt":82,"hq":176,"4444xq":396},"25":{"422":122,"4444":275,"proxy":38,"lt":85,"hq":184,"4444xq":413},"30":{"422":147,"4444":330,"proxy":45,"lt":102,"hq":220,"4444xq":495},"50":{"422":245,"4444":551,"proxy":76,"lt":170,"hq":367,"4444xq":826},"60":{"422":293,"4444":660,"proxy":91,"lt":204,"hq":440,"4444xq":990}},"2k-dci":{"24":{"422":134,"4444":302,"proxy":41,"lt":93,"hq":201,"4444xq":453},"25":{"422":140,"4444":315,"proxy":43,"lt":97,"hq":210,"4444xq":472},"30":{"422":168,"4444":377,"proxy":52,"lt":116,"hq":251,"4444xq":566},"50":{"422":280,"4444":629,"proxy":86,"lt":194,"hq":419,"4444xq":944},"60":{"422":335,"4444":754,"proxy":103,"lt":232,"hq":503,"4444xq":1131}},"2k-fa":{"24":{"422":181,"4444":407,"proxy":56,"lt":126,"hq":272,"4444xq":611},"25":{"422":189,"4444":425,"proxy":58,"lt":131,"hq":283,"4444xq":637},"30":{"422":226,"4444":509,"proxy":70,"lt":157,"hq":340,"4444xq":764},"50":{"422":377,"4444":850,"proxy":117,"lt":262,"hq":567,"4444xq":1275},"60":{"422":452,"4444":1019,"proxy":140,"lt":314,"hq":679,"4444xq":1528}},"uhd":{"24":{"422":471,"4444":1061,"proxy":145,"lt":328,"hq":707,"4444xq":1591},"25":{"422":492,"4444":1106,"proxy":151,"lt":342,"hq":737,"4444xq":1659},"30":{"422":589,"4444":1326,"proxy":182,"lt":410,"hq":884,"4444xq":1989},"50":{"422":983,"4444":2212,"proxy":303,"lt":684,"hq":1475,"4444xq":3318},"60":{"422":1178,"4444":2652,"proxy":363,"lt":821,"hq":1768,"4444xq":3977}},"4k-dci":{"24":{"422":503,"4444":1131,"proxy":155,"lt":350,"hq":754,"4444xq":1697},"25":{"422":524,"4444":1180,"proxy":162,"lt":365,"hq":786,"4444xq":1769},"30":{"422":629,"4444":1414,"proxy":194,"lt":437,"hq":943,"4444xq":2121},"50":{"422":1049,"4444":2359,"proxy":323,"lt":730,"hq":1573,"4444xq":3539},"60":{"422":1257,"4444":2828,"proxy":388,"lt":875,"hq":1886,"4444xq":4242}}}},"youtube":{"source":"youtube","high_fps_from":48,"res":[{"id":"1080p","label":"1080p"},{"id":"1440p","label":"1440p"},{"id":"2160p","label":"2160p (4K)"}],"sdr":{"1080p":{"std":[8,8],"high":[12,12]},"1440p":{"std":[16,16],"high":[24,24]},"2160p":{"std":[35,45],"high":[53,68]}},"hdr":{"1080p":{"std":[10,10],"high":[15,15]},"1440p":{"std":[20,20],"high":[30,30]},"2160p":{"std":[44,56],"high":[66,85]}}},"image":{"dpx":{"source":"loc_dpx","header_bytes":2048,"row_align":4,"depths":{"8":3,"10":4,"12":6,"16":6},"label":"DPX image sequence (RGB)"},"tiff":{"source":"tiff6","header_bytes":0,"row_align":1,"depths":{"8":3,"16":6},"label":"TIFF image sequence (RGB, uncompressed)"}},"uncompressed":{"source":"apple_tn2162","types":[{"id":"v210","label":"Uncompressed 10-bit 4:2:2 (v210)","group_px":48,"group_bytes":128},{"id":"2vuy","label":"Uncompressed 8-bit 4:2:2 (2vuy)","group_px":2,"group_bytes":4},{"id":"v410","label":"Uncompressed 10-bit 4:4:4 (v410)","group_px":2,"group_bytes":8}]},"drives_gb":[128,256,500,1000,2000,4000,5000,8000,10000,12000,14000,16000,18000,20000,22000,24000],"limits":{"lines":50,"deliverables":10,"reels":999,"feet":100000,"seconds":360000,"mbps_min":0.01,"mbps_max":20000,"px_min":16,"px_max":16384,"fps_min":1,"fps_max":120,"audio_kbps_max":20000,"headroom_max":100}};
  /* END DATARATE PRESETS */
  var DR_DEFAULT_FPS = { '8': 16, 's8': 18, '16': 24, '35-4': 24, '35-3': 24, '35-2': 24 };
  var DR_KINDS = ['prores', 'h264', 'h265', 'youtube', 'dpx', 'tiff', 'uncompressed', 'custom'];
  var NUM_RE = /^-?\d+(\.\d+)?$/;
  /* Strict number parse shared with PHP: real numbers or plain decimal strings only. */
  function drNum(v) {
    if (typeof v === 'number') { return isFinite(v) ? v : NaN; }
    if (typeof v === 'string' && NUM_RE.test(v)) { return Number(v); }
    return NaN;
  }
  function drStr(v) { return typeof v === 'string' ? v : typeof v === 'number' && isFinite(v) ? String(v) : ''; }
  function drBlank(v) { return v === undefined || v === null || v === ''; }
  function rnd0(x) { return Math.floor(x + 0.5); }
  function drErr(line, d, code, message) { return { line: line, deliverable: d, code: code, message: message }; }
  function drFind(list, id) { for (var i = 0; i < list.length; i++) { if (list[i].id === id) { return list[i]; } } return null; }
  function drSize(spec, L) {
    if (spec.size === 'custom') {
      var w = drNum(spec.width), h = drNum(spec.height);
      if (!(w >= L.px_min && w <= L.px_max && Math.floor(w) === w && h >= L.px_min && h <= L.px_max && Math.floor(h) === h)) { return null; }
      return { id: 'custom', label: w + '×' + h, w: w, h: h };
    }
    return drFind(DR.sizes, drStr(spec.size));
  }
  /* ProRes target rate at an output frame rate: Apple's listed figure when the rate is one Apple lists
     (24p row also covers 23.976, 30p covers 29.97, 60p covers 59.94); otherwise scaled from the nearest listed rate. */
  function proresMbps(sizeId, profile, fps) {
    var tab = DR.prores.mbps[sizeId];
    if (!tab) { return null; }
    var keys = Object.keys(tab).map(Number).sort(function (a, b) { return a - b; });
    var best = keys[0];
    for (var i = 0; i < keys.length; i++) {
      var k = keys[i];
      if (Math.abs(fps - k) / k <= DR.prores.fps_tolerance) { return { mbps: tab[String(k)][profile], listed: k, scaled: false }; }
      if (Math.abs(fps - k) < Math.abs(fps - best)) { best = k; }
    }
    return { mbps: tab[String(best)][profile] * fps / best, listed: best, scaled: true };
  }
  /* Validate one deliverable. Returns { d: normalised } or { error: [code, message] }. */
  function drDeliverable(spec, L) {
    spec = spec && typeof spec === 'object' && !Array.isArray(spec) ? spec : {};
    var kind = drStr(spec.kind), d = { kind: kind, source: '', label: '' };
    if (DR_KINDS.indexOf(kind) < 0) { return { error: ['bad_kind', 'Unknown deliverable type.'] }; }
    if (drBlank(spec.fps) || spec.fps === 'film') { d.fps = 'film'; }
    else { var f = drNum(spec.fps); if (!(f >= L.fps_min && f <= L.fps_max)) { return { error: ['bad_out_fps', 'Output frame rate must be from 1 to 120 fps.'] }; } d.fps = f; }
    var ak = drBlank(spec.audio_kbps) ? 0 : drNum(spec.audio_kbps);
    if (!(ak >= 0 && ak <= L.audio_kbps_max)) { return { error: ['bad_audio', 'Audio bitrate must be from 0 to 20000 kbps.'] }; }
    d.audio_kbps = ak;
    if (kind === 'prores') {
      var p = drFind(DR.prores.profiles, drStr(spec.profile));
      if (!p) { return { error: ['bad_profile', 'Unknown ProRes profile.'] }; }
      var ps = drFind(DR.sizes, drStr(spec.size));
      if (!ps) { return { error: ['bad_frame_size', 'Unknown frame size.'] }; }
      if (!DR.prores.mbps[ps.id]) { return { error: ['prores_size', 'Apple publishes no ProRes target rate for that frame size.'] }; }
      d.profile = p.id; d.size = ps.id; d.size_label = ps.label; d.label = p.label + ' ' + ps.label; d.source = DR.prores.source;
    } else if (kind === 'h264' || kind === 'h265' || kind === 'custom') {
      var m = drNum(spec.mbps);
      if (!(m >= L.mbps_min && m <= L.mbps_max)) { return { error: ['bad_mbps', 'Bitrate must be from 0.01 to 20000 Mbps.'] }; }
      d.mbps = m; d.label = (kind === 'h264' ? 'H.264 MP4 ' : kind === 'h265' ? 'H.265 MP4 ' : 'Custom ') + m + ' Mbps'; d.source = kind === 'custom' ? '' : kind;
    } else if (kind === 'youtube') {
      var y = drFind(DR.youtube.res, drStr(spec.res));
      if (!y) { return { error: ['bad_res', 'Unknown YouTube resolution.'] }; }
      d.res = y.id; d.hdr = spec.hdr === true || spec.hdr === 1 || spec.hdr === '1'; d.label = 'YouTube ' + y.label + (d.hdr ? ' HDR' : ' SDR'); d.source = DR.youtube.source;
    } else if (kind === 'dpx' || kind === 'tiff') {
      var im = DR.image[kind], sz = drSize(spec, L), dep = drStr(spec.depth);
      if (!sz) { return { error: ['bad_frame_size', 'Unknown frame size.'] }; }
      if (!Object.prototype.hasOwnProperty.call(im.depths, dep)) { return { error: ['bad_depth', 'That bit depth is not offered for this format.'] }; }
      var row = Math.ceil(sz.w * im.depths[dep] / im.row_align) * im.row_align;
      d.size = sz.id; d.size_label = sz.label; d.depth = Number(dep); d.frame_bytes = row * sz.h + im.header_bytes;
      d.label = (kind === 'dpx' ? 'DPX ' : 'TIFF ') + dep + '-bit ' + sz.label; d.source = im.source;
    } else {
      var ut = drFind(DR.uncompressed.types, drStr(spec.type)), us = drSize(spec, L);
      if (!ut) { return { error: ['bad_type', 'Unknown uncompressed type.'] }; }
      if (!us) { return { error: ['bad_frame_size', 'Unknown frame size.'] }; }
      d.type = ut.id; d.size = us.id; d.size_label = us.label; d.frame_bytes = Math.ceil(us.w / ut.group_px) * ut.group_bytes * us.h;
      d.label = ut.label + ' ' + us.label; d.source = DR.uncompressed.source;
    }
    return { d: d };
  }
  /* Bytes for one reel of one deliverable. */
  function drItem(d, frames, filmFps) {
    var out = d.fps === 'film' ? filmFps : d.fps;
    var n = Math.abs(out - filmFps) < 1e-9 ? frames : rnd0(frames / filmFps * out);
    var secs = n / out, mbps, scaled = false, range = null, bytes;
    if (d.frame_bytes) {
      bytes = n * d.frame_bytes; mbps = d.frame_bytes * 8 * out / 1e6;
    } else {
      if (d.kind === 'prores') { var pr = proresMbps(d.size, d.profile, out); mbps = pr.mbps; scaled = pr.scaled; }
      else if (d.kind === 'youtube') { var t = DR.youtube[d.hdr ? 'hdr' : 'sdr'][d.res][out >= DR.youtube.high_fps_from ? 'high' : 'std']; range = [t[0], t[1]]; mbps = t[1]; }
      else { mbps = d.mbps; }
      bytes = rnd0(mbps * 125000 * secs);
    }
    if (d.audio_kbps > 0) { bytes += rnd0(d.audio_kbps * 125 * secs); }
    return { out_fps: out, frames: n, mbps: mbps, scaled: scaled, range: range, bytes_per_reel: bytes };
  }
  function units(bytes) { return { bytes: bytes, gb: bytes / 1e9, tb: bytes / 1e12, gib: bytes / 1073741824, tib: bytes / 1099511627776 }; }
  function suggestDrive(bytes, headroomPct) {
    if (!(bytes > 0)) { return null; }
    var need = Math.ceil(bytes * (100 + headroomPct) / 100), list = DR.drives_gb;
    for (var i = 0; i < list.length; i++) { if (list[i] * 1e9 >= need) { return { gb: list[i], count: 1, need_bytes: need }; } }
    var big = list[list.length - 1];
    return { gb: big, count: Math.ceil(need / (big * 1e9)), need_bytes: need };
  }
  /* request: { lines: [{ format, fps, reels, size | feet | seconds }], deliverables: [spec], headroom_pct } */
  function storage(request) {
    request = request && typeof request === 'object' && !Array.isArray(request) ? request : {};
    var L = DR.limits, errors = [], dels = [], lines = [];
    var rd = Array.isArray(request.deliverables) ? request.deliverables : [];
    if (rd.length > L.deliverables) { errors.push(drErr(-1, -1, 'too_many_deliverables', 'Too many deliverables.')); rd = rd.slice(0, L.deliverables); }
    for (var j = 0; j < rd.length; j++) {
      var r = drDeliverable(rd[j], L);
      if (r.error) { errors.push(drErr(-1, j, r.error[0], r.error[1])); } else { r.d.index = j; r.d.bytes = 0; dels.push(r.d); }
    }
    if (!rd.length) { errors.push(drErr(-1, -1, 'no_deliverables', 'Add at least one deliverable.')); }
    var head = drBlank(request.headroom_pct) ? 20 : drNum(request.headroom_pct);
    if (!(head >= 0 && head <= L.headroom_max)) { errors.push(drErr(-1, -1, 'bad_headroom', 'Headroom must be from 0 to 100%.')); head = 20; }
    var rl = Array.isArray(request.lines) ? request.lines : [];
    if (rl.length > L.lines) { errors.push(drErr(-1, -1, 'too_many_lines', 'Too many lines.')); rl = rl.slice(0, L.lines); }
    var sumSec = 0, sumFrames = 0, total = 0;
    for (var i = 0; i < rl.length; i++) {
      var q = rl[i] && typeof rl[i] === 'object' && !Array.isArray(rl[i]) ? rl[i] : {}, fid = drStr(q.format) === '35' ? '35-4' : drStr(q.format), fmt = getFormat(fid);
      if (!fmt) { errors.push(drErr(i, -1, 'bad_format', 'Unknown film format.')); continue; }
      var fps = drBlank(q.fps) ? DR_DEFAULT_FPS[fid] : drNum(q.fps);
      if (!(fps >= L.fps_min && fps <= L.fps_max)) { errors.push(drErr(i, -1, 'bad_fps', 'Film speed must be from 1 to 120 fps.')); continue; }
      var reels = drBlank(q.reels) ? 1 : drNum(q.reels);
      if (!(reels >= 1 && reels <= L.reels && Math.floor(reels) === reels)) { errors.push(drErr(i, -1, 'bad_reels', 'Number of reels must be a whole number from 1 to 999.')); continue; }
      var frames, method;
      if (!drBlank(q.seconds)) {
        var s = drNum(q.seconds);
        if (!(s > 0 && s <= L.seconds)) { errors.push(drErr(i, -1, 'bad_seconds', 'Running time must be more than 0 and at most 100 hours.')); continue; }
        frames = rnd0(s / reels * fps); method = 'time';
      } else if (!drBlank(q.feet)) {
        var ft = drNum(q.feet);
        if (!(ft > 0 && ft <= L.feet)) { errors.push(drErr(i, -1, 'bad_feet', 'Footage must be more than 0 and at most 100000 feet.')); continue; }
        frames = rnd0(ft / reels * fmt.fpf); method = 'feet';
      } else if (!drBlank(q.size)) {
        var g = reelGroup(fid), rc = null;
        for (var k = 0; k < REELS.length; k++) { if (REELS[k].format === g && REELS[k].size === drStr(q.size)) { rc = REELS[k]; } }
        if (!rc) { errors.push(drErr(i, -1, 'bad_size', 'That reel size is not listed for this format.')); continue; }
        frames = rnd0(rc.feet * fmt.fpf); method = 'size';
      } else { errors.push(drErr(i, -1, 'need_length', 'Enter a reel size, footage or running time.')); continue; }
      if (frames < 1) { errors.push(drErr(i, -1, 'too_short', 'Less than one frame per reel.')); continue; }
      var items = [], lineBytes = 0;
      for (var x = 0; x < dels.length; x++) {
        var it = drItem(dels[x], frames, fps);
        it.deliverable = dels[x].index; it.bytes = it.bytes_per_reel * reels;
        items.push(it); lineBytes += it.bytes; dels[x].bytes += it.bytes;
      }
      var secs = frames / fps;
      lines.push({ index: i, format: fid, format_label: fmt.label, fps: fps, reels: reels, method: method,
        frames_per_reel: frames, feet_per_reel: frames / fmt.fpf, seconds_per_reel: secs, items: items, bytes: lineBytes });
      sumSec += secs * reels; sumFrames += frames * reels; total += lineBytes;
    }
    if (!rl.length) { errors.push(drErr(-1, -1, 'no_lines', 'Add at least one reel.')); }
    return { ok: errors.length === 0 && lines.length > 0 && dels.length > 0, as_of: DR.as_of, lines: lines, deliverables: dels, errors: errors,
      totals: { seconds: sumSec, frames: sumFrames, size: units(total), headroom_pct: head, drive: suggestDrive(total, head) } };
  }
  function fmtBytes(bytes, binary) {
    var u = binary ? ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'] : ['B', 'KB', 'MB', 'GB', 'TB', 'PB'], b = binary ? 1024 : 1000, i = 0, v = Number(bytes) || 0;
    while (v >= b && i < u.length - 1) { v /= b; i++; }
    var d = i === 0 ? 0 : v < 10 ? 2 : v < 100 ? 1 : 0;
    return v.toFixed(d) + ' ' + u[i];
  }
  function fmtDrive(dr) { if (!dr) { return ''; } var s = dr.gb >= 1000 ? (dr.gb / 1000) + ' TB' : dr.gb + ' GB'; return (dr.count > 1 ? dr.count + ' × ' : '') + s; }

  return {
    VERSION: '1.1.0',
    /* length (same API as FilmCalcCore 1.1.0) */
    M_PER_FT: M_PER_FT, FORMATS: FORMATS, SPEEDS: SPEEDS, getFormat: getFormat, parseTime: parseTime,
    formatTime: formatTime, feetPerMinute: feetPerMinute, compute: compute, round: round,
    /* reels */
    reels: { REELS: REELS, THICKNESS: THICKNESS, HUB: HUB, group: reelGroup, sizes: reelSizes, feetFromDiameter: feetFromDiameter },
    /* pricing */
    pricing: { LIMITS: LIMITS, quote: quote, toCents: toCents, formatMoney: formatMoney, formatMills: formatMills },
    /* timecode */
    timecode: timecode,
    /* storage / data rate */
    datarate: { PRESETS: DR, storage: storage, proresMbps: proresMbps, units: units, suggestDrive: suggestDrive, fmtBytes: fmtBytes, fmtDrive: fmtDrive }
  };
}));
