/* Film Transfer Toolkit admin: Copy buttons. No innerHTML. */
(function () {
  'use strict';
  var L = window.FTT_ADMIN_UI || { copied: 'Copied', copy: 'Copy', failed: 'Press Ctrl+C to copy' };
  function flash(btn, text) {
    btn.textContent = text;
    btn.classList.add('is-copied');
    setTimeout(function () { btn.textContent = L.copy; btn.classList.remove('is-copied'); }, 1800);
  }
  function fallback(text) {
    var ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    return ok;
  }
  var live = document.createElement('div');
  live.className = 'screen-reader-text'; live.setAttribute('aria-live', 'polite');
  document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(live); });
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest && ev.target.closest('[data-ftt-copy]');
    if (!btn) { return; }
    ev.preventDefault();
    var text = btn.getAttribute('data-ftt-copy');
    var done = function () { flash(btn, L.copied); live.textContent = L.copied + ': ' + text; };
    var fail = function () { if (fallback(text)) { done(); } else { flash(btn, L.failed); } };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, fail); } else { fail(); }
  });
})();

/* 1.3.0: Storage calculator defaults form - show only the fields the chosen file type uses. */
(function () {
  'use strict';
  function sync(row) {
    var k = row.querySelector('[data-ftt-kind]'); if (!k) { return; }
    Array.prototype.forEach.call(row.querySelectorAll('label[data-ftt-kinds]'), function (l) {
      l.hidden = !k.value || (' ' + l.getAttribute('data-ftt-kinds') + ' ').indexOf(' ' + k.value + ' ') < 0;
    });
  }
  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-ftt-st-row]'), function (row) {
      sync(row);
      var k = row.querySelector('[data-ftt-kind]'); if (k) { k.addEventListener('change', function () { sync(row); }); }
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
