// 1.5.12: unit tests for the clip selector's fresh-nonce + retry path (no browser). Run: node tests/clip-nonce-retry.js
'use strict';
const fs = require('fs'), path = require('path'), vm = require('vm');
const SRC = fs.readFileSync(path.join(__dirname, '../modules/clip-selector/assets/clip-selector.js'), 'utf8');
let P = 0, F = 0; const ok = (n, c, d) => { if (c) P++; else { F++; console.log('FAIL ' + n + (d !== undefined ? ' :: ' + JSON.stringify(d).slice(0, 300) : '')); } };
const resp = (status, body) => Promise.resolve({ ok: status >= 200 && status < 300, status, json: () => body === undefined ? Promise.reject(new Error('no json')) : Promise.resolve(body) });
const BAD = { code: 'rest_cookie_invalid_nonce', message: 'Cookie check failed', data: { status: 403 } };
const FBAD = { code: 'fwcc_nonce', message: 'Missing or invalid nonce.', data: { status: 403 } };
// Load the script with a stub page and a scripted fetch. `routes` maps a URL test to a handler(call) -> response promise.
function load(cfg, routes) {
  const calls = [];
  const fetch = (url, o) => { const c = { url, o: o || {}, nonce: o && o.headers && o.headers['X-WP-Nonce'], body: o && o.body ? JSON.parse(o.body) : null }; calls.push(c);
    for (const [re, h] of routes) if (re.test(url)) return h(c, calls); return Promise.reject(new TypeError('unrouted ' + url)); };
  const doc = { addEventListener() {}, querySelectorAll() { return []; }, querySelector() { return null; }, cookie: '' };
  const win = { FTT_CLIP: Object.assign({ rest: 'https://s.test/wp-json/ftt/v1/clip', nonce: 'BAKED00000', nonceUrl: 'https://s.test/wp-admin/admin-ajax.php?action=ftt_clip_nonce' }, cfg),
    FTTCore: { timecode: {} }, addEventListener() {}, dispatchEvent() {} };
  const ctx = { window: win, document: doc, fetch, localStorage: { removeItem() {} }, location: { href: 'https://s.test/p/', search: '' }, Event: function () {}, CustomEvent: function () {}, setTimeout, clearInterval, setInterval, Promise, JSON, Date, Math, Error, TypeError, console };
  vm.createContext(ctx); vm.runInContext(SRC, ctx);
  return { net: win.FTT_CLIP_NET, calls, posts: () => calls.filter(c => c.o.method === 'POST') };
}
let n = 0; const tick = () => 'FRESH' + String(++n).padStart(5, '0');
const ajaxOK = [/admin-ajax\.php/, () => resp(200, { nonce: tick(), ttl: 86400 })];
const restOK = [/\/nonce$/, () => resp(200, { nonce: 'REST' + String(++n).padStart(6, '0'), ttl: 86400 })];
const T = [];
const t = (name, fn) => T.push([name, fn]);

t('fresh nonce fetched before /add; baked nonce not used', async () => {
  const L = load({}, [ajaxOK, [/\/add$/, () => resp(200, { added: 'x' })]]);
  const r = await L.net.apiPost('/add', { a: 1 });
  ok('add ok', r.ok && r.status === 200, r);
  ok('nonce request first, no-store, same-origin cookies', L.calls[0].url.includes('admin-ajax.php?action=ftt_clip_nonce') && L.calls[0].o.cache === 'no-store' && L.calls[0].o.credentials === 'same-origin', L.calls[0]);
  ok('add sent the fresh nonce, not the baked one', L.posts()[0].nonce.startsWith('FRESH') && L.posts()[0].nonce !== 'BAKED00000', L.posts()[0].nonce);
  ok('add body passed through', L.posts()[0].body.a === 1);
});
t('nonce reused in memory for later calls (quote then add = one nonce fetch)', async () => {
  const L = load({}, [ajaxOK, [/\/(quote|add)$/, () => resp(200, {})]]);
  await L.net.apiPost('/quote', {}); await L.net.apiPost('/add', {});
  ok('one nonce fetch', L.calls.filter(c => /admin-ajax/.test(c.url)).length === 1, L.calls.map(c => c.url));
  ok('two posts with the same nonce', L.posts().length === 2 && L.posts()[0].nonce === L.posts()[1].nonce);
});
t('concurrent callers share one nonce request', async () => {
  const L = load({}, [ajaxOK, [/\/quote$/, () => resp(200, {})]]);
  await Promise.all([L.net.apiPost('/quote', {}), L.net.apiPost('/quote', {}), L.net.apiPost('/quote', {})]);
  ok('one nonce fetch for 3 quotes', L.calls.filter(c => /admin-ajax/.test(c.url)).length === 1);
});
for (const [label, err] of [['core rest_cookie_invalid_nonce', BAD], ['FTT fwcc_nonce', FBAD]]) {
  t('403 ' + label + ' -> refresh once, retry once, success', async () => {
    let k = 0; const L = load({}, [ajaxOK, [/\/add$/, () => (++k === 1 ? resp(403, err) : resp(200, { added: 'y' }))]]);
    const r = await L.net.apiPost('/add', { s: 1 });
    ok(label + ': final ok', r.ok && r.j.added === 'y', r);
    ok(label + ': exactly 2 posts', L.posts().length === 2);
    ok(label + ': 2 nonce fetches (second forced)', L.calls.filter(c => /admin-ajax/.test(c.url)).length === 2);
    ok(label + ': retry used the new nonce', L.posts()[0].nonce !== L.posts()[1].nonce, L.posts().map(c => c.nonce));
    ok(label + ': same body on retry', JSON.stringify(L.posts()[0].body) === JSON.stringify(L.posts()[1].body));
    ok(label + ': not flagged expired', !r.expired);
  });
}
t('still invalid after one retry -> stop (no loop), flagged expired', async () => {
  const L = load({}, [ajaxOK, [/\/add$/, () => resp(403, BAD)]]);
  const r = await L.net.apiPost('/add', {});
  ok('expired flag', r.expired === true && r.status === 403, r);
  ok('exactly 2 posts', L.posts().length === 2, L.posts().length);
});
t('other errors are not retried (403 other code, 409 price mismatch, 429 rate, 500)', async () => {
  for (const [st, body] of [[403, { code: 'rest_forbidden' }], [409, { code: 'fwcc_price_mismatch' }], [429, { code: 'fwcc_rate' }], [500, undefined]]) {
    const L = load({}, [ajaxOK, [/\/add$/, () => resp(st, body)]]);
    const r = await L.net.apiPost('/add', {});
    ok(st + ': one post only', L.posts().length === 1 && r.status === st && !r.expired, { n: L.posts().length, r });
  }
});
t('admin-ajax blocked -> REST /nonce fallback', async () => {
  const L = load({}, [[/admin-ajax/, () => resp(400, 0)], restOK, [/\/add$/, () => resp(200, {})]]);
  const r = await L.net.apiPost('/add', {});
  ok('ok via REST nonce', r.ok && L.posts()[0].nonce.startsWith('REST'), L.posts()[0]);
  ok('REST nonce request is no-store', L.calls.find(c => /\/nonce$/.test(c.url)).o.cache === 'no-store');
});
t('admin-ajax returns junk -> REST /nonce fallback', async () => {
  const L = load({}, [[/admin-ajax/, () => resp(200, { nope: 1 })], restOK, [/\/add$/, () => resp(200, {})]]);
  await L.net.apiPost('/add', {});
  ok('used REST nonce', L.posts()[0].nonce.startsWith('REST'));
});
t('both nonce sources down -> baked nonce as last resort, and not cached', async () => {
  const L = load({}, [[/admin-ajax/, () => Promise.reject(new TypeError('offline'))], [/\/nonce$/, () => resp(503, {})], [/\/add$/, () => resp(200, {})]]);
  await L.net.apiPost('/add', {});
  ok('baked nonce sent', L.posts()[0].nonce === 'BAKED00000', L.posts()[0]);
  ok('not stored as fresh', L.net._state.v === '');
});
t('no nonce anywhere -> header omitted (server answers fwcc_nonce; one retry)', async () => {
  const L = load({ nonce: '' }, [[/admin-ajax/, () => resp(400, 0)], [/\/nonce$/, () => resp(404, {})], [/\/add$/, () => resp(403, FBAD)]]);
  const r = await L.net.apiPost('/add', {});
  ok('no X-WP-Nonce header', !('X-WP-Nonce' in L.posts()[0].o.headers));
  ok('expired after one retry', r.expired === true && L.posts().length === 2);
});
t('nonce older than 30 min is refreshed before use', async () => {
  const L = load({}, [ajaxOK, [/\/quote$/, () => resp(200, {})]]);
  await L.net.apiPost('/quote', {}); L.net._state.t -= 31 * 60 * 1000; await L.net.apiPost('/quote', {});
  ok('two nonce fetches', L.calls.filter(c => /admin-ajax/.test(c.url)).length === 2);
});
t('non-JSON error reply does not throw', async () => {
  const L = load({}, [ajaxOK, [/\/add$/, () => resp(502, undefined)]]);
  const r = await L.net.apiPost('/add', {});
  ok('502 returned with j=null', r.status === 502 && r.j === null, r);
});
t('isNonceError only matches 403 + nonce codes', async () => {
  const L = load({}, []); const f = L.net.isNonceError;
  ok('403 core', f({ status: 403, j: BAD })); ok('403 ftt', f({ status: 403, j: FBAD }));
  ok('401 core no', !f({ status: 401, j: BAD })); ok('403 other no', !f({ status: 403, j: { code: 'x' } })); ok('null j no', !f({ status: 403, j: null }));
});
(async () => { for (const [name, fn] of T) { try { await fn(); } catch (e) { F++; console.log('FAIL ' + name + ' threw ' + e.stack); } }
  console.log('clip-nonce-retry.js: ' + P + ' passed, ' + F + ' failed'); process.exit(F ? 1 : 0); })();
