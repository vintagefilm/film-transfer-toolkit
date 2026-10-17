/*!
 * FTTCore 1.0.0 - Film Transfer Toolkit portable core.
 * Film length / time / frames, reel size estimates, price quotes and SMPTE timecode.
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
  var LIMITS = { lines: 50, reels: 999, feet: 100000, addons: 20 };
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
        if (ad.unit === 'order') { bad = err(i, 'order_addon_on_line', 'That add-on applies to the whole order.'); break; }
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
      var oid = String(oids[o]); if (oseen[oid]) { continue; } oseen[oid] = 1;
      var oa = findBy(table.addons, function (x) { return x.id === oid && x.unit === 'order'; });
      if (!oa) { errors.push(err(-1, 'bad_order_addon', 'Unknown order add-on.')); continue; }
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

  return {
    VERSION: '1.0.0',
    /* length (same API as FilmCalcCore 1.1.0) */
    M_PER_FT: M_PER_FT, FORMATS: FORMATS, SPEEDS: SPEEDS, getFormat: getFormat, parseTime: parseTime,
    formatTime: formatTime, feetPerMinute: feetPerMinute, compute: compute, round: round,
    /* reels */
    reels: { REELS: REELS, THICKNESS: THICKNESS, HUB: HUB, group: reelGroup, sizes: reelSizes, feetFromDiameter: feetFromDiameter },
    /* pricing */
    pricing: { LIMITS: LIMITS, quote: quote, toCents: toCents, formatMoney: formatMoney, formatMills: formatMills },
    /* timecode */
    timecode: timecode
  };
}));
