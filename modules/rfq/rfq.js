/* RFQ provider picker: limit selections, show a bar with a link to the request form. No innerHTML. */
(function () {
  'use strict';
  function boot() {
    var pickers = document.querySelectorAll('[data-ftt-rfq-picker]');
    Array.prototype.forEach.call(pickers, function (root) {
      var max = parseInt(root.getAttribute('data-max'), 10) || 5, go = root.getAttribute('data-go');
      var cbs = Array.prototype.slice.call(root.querySelectorAll('.ftt-rfq-cb'));
      var bar = root.querySelector('[data-rfq-bar]'), count = root.querySelector('[data-rfq-count]'), link = root.querySelector('[data-rfq-go]');
      function upd() {
        var sel = cbs.filter(function (c) { return c.checked; });
        cbs.forEach(function (c) { c.disabled = !c.checked && sel.length >= max; });
        count.textContent = String(sel.length); bar.hidden = !sel.length;
        var u = new URL(go, location.href); u.searchParams.set('labs', sel.map(function (c) { return c.value; }).join(',')); link.href = u.toString();
      }
      cbs.forEach(function (c) { c.addEventListener('change', upd); });
      upd();
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
