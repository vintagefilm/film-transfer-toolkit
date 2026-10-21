/* FTT admin helpers: add/remove table rows from <template>, confirm buttons. No innerHTML. */
(function () {
  'use strict';
  var n = 1000;
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest && t.closest('[data-ftt-remove]')) { ev.preventDefault(); var tr = t.closest('tr'); if (tr) { tr.parentNode.removeChild(tr); } return; }
    var mv = t.closest && t.closest('[data-ftt-up],[data-ftt-down]');
    if (mv) {
      ev.preventDefault(); var row = mv.closest('tr'); if (!row) { return; }
      if (mv.hasAttribute('data-ftt-up')) { if (row.previousElementSibling) { row.parentNode.insertBefore(row, row.previousElementSibling); } }
      else if (row.nextElementSibling) { row.parentNode.insertBefore(row.nextElementSibling, row); }
      mv.focus(); return;
    }
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
  /* Add-on unit name: only for "per custom unit". */
  function unitBox(sel) { var tr = sel.closest('tr'), u = tr && tr.querySelector('.ftt-addon-unit'); if (u) { u.hidden = sel.value !== 'custom'; u.required = sel.value === 'custom'; } }
  document.addEventListener('change', function (ev) { if (ev.target.matches && ev.target.matches('select[name^="addons["][name$="[unit]"]')) { unitBox(ev.target); } });
  document.addEventListener('click', function () { setTimeout(function () { Array.prototype.forEach.call(document.querySelectorAll('select[name^="addons["][name$="[unit]"]'), unitBox); }, 0); });
  Array.prototype.forEach.call(document.querySelectorAll('select[name^="addons["][name$="[unit]"]'), unitBox);
})();
