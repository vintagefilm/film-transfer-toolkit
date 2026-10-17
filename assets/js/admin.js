/* Film Transfer Toolkit admin helpers: add/remove table rows from <template>, confirm buttons. No innerHTML. */
(function () {
  'use strict';
  var n = 1000;
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest && t.closest('[data-ftt-remove]')) { ev.preventDefault(); var tr = t.closest('tr'); if (tr) { tr.parentNode.removeChild(tr); } return; }
    var add = t.closest && t.closest('[data-ftt-add]');
    if (add) {
      ev.preventDefault();
      var key = add.getAttribute('data-ftt-add');
      var tpl = document.querySelector('template[data-ftt-template="' + key + '"]');
      var body = document.querySelector('[data-ftt-rows="' + key + '"]');
      if (!tpl || !body) { return; }
      var frag = tpl.content.cloneNode(true), i = n++;
      Array.prototype.forEach.call(frag.querySelectorAll('[name]'), function (el) { el.name = el.name.replace('__i__', String(i)); });
      body.appendChild(frag);
      return;
    }
    var c = t.closest && t.closest('[data-ftt-confirm]');
    if (c && !window.confirm(c.getAttribute('data-ftt-confirm'))) { ev.preventDefault(); }
  });
})();
