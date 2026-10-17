/*
 * einkauf.js – Einkaufsliste (einkauf.php), Phase 5e-3.
 *  - Filterbereich: standardmäßig eingeklappt; die eigene Wahl (auf/zu) bleibt über das Neuladen erhalten (localStorage)
 *  - select[data-sort]: Sortierung wählen → Seite neu laden (sort, dir, page=1)
 */
(function () {
  'use strict';

  var box = document.getElementById('filterBox');
  if (box) {
    // Standard: eingeklappt (aktive Filter stehen als Chips darunter). Hat die Person den Bereich selbst auf- oder zugeklappt,
    // gilt das auch nach dem Neuladen (z. B. nach dem Umlegen eines Stand-Schalters).
    var MERKER = 'einkaufFilterOffen';
    var offen = false;
    try {
      var gemerkt = window.localStorage.getItem(MERKER);
      if (gemerkt === '1') offen = true; else if (gemerkt === '0') offen = false;
    } catch (_) {}
    box.open = offen;
    var kopf = box.querySelector('summary');   // nur echtes Auf-/Zuklappen merken, nicht das Setzen des Standards oben
    if (kopf) kopf.addEventListener('click', function () {
      setTimeout(function () {
        try { window.localStorage.setItem(MERKER, box.open ? '1' : '0'); } catch (_) {}
      }, 0);
    });
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
