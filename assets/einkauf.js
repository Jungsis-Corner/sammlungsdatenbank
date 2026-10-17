/*
 * einkauf.js – Einkaufsliste (einkauf.php), Phase 5e-3.
 *  - Filterbereich: ab 900 px offen, darunter eingeklappt (offen nur, wenn Filter aktiv sind)
 *  - select[data-sort]: Sortierung wählen → Seite neu laden (sort, dir, page=1)
 */
(function () {
  'use strict';

  var box = document.getElementById('filterBox');
  if (box) {
    var breit = window.matchMedia ? window.matchMedia('(min-width: 900px)').matches : true;
    box.open = breit || box.getAttribute('data-aktiv') === '1';
  }

  var sel = document.querySelector('select[data-sort]');
  if (sel) {
    sel.addEventListener('change', function () {
      var teile = String(sel.value).split(':');
      var u = new URL(window.location.href);
      u.searchParams.set('sort', teile[0]);
      u.searchParams.set('dir', teile[1] === 'desc' ? 'desc' : 'asc');
      u.searchParams.set('page', '1');
      window.location.href = u.toString();
    });
  }
})();
