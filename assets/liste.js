/*
 * liste.js – Verhalten der Sammlungsliste (index.php), Phase 5b. Ersetzt die früheren Inline-Skripte und onchange/onclick-Attribute.
 *
 *  - select[data-nav="<Parameter>"], input[data-nav="<Parameter>"]: Wert ändern → Seite mit diesem Parameter neu laden (leer = Parameter entfernen), Seite 1
 *  - select[data-kat]: Kategorie-Kaskade; Wert "<ID>:<inkl>" setzt kat und inkl (leer = beide entfernen)
 *  - #filterToggle: ab 1280 px Seitenleiste ein-/ausklappen (Cookie filterPanelOpen), darunter Bottom-Sheet öffnen
 *  - a.back-to-top: Knopf "Nach oben"
 *  - a.row-action: Duplizieren/Löschen per POST mit CSRF-Token (aus data-csrf an der Tabelle)
 */
(function () {
  'use strict';

  function navigiere(aendern) {
    var u = new URL(window.location.href);
    aendern(u.searchParams);
    u.searchParams.set('page', '1');
    window.location.href = u.toString();
  }

  /* ---------- Filter, die per Auswahl die Seite neu laden ---------- */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || !t.matches) return;
    if (t.matches('select[data-kat]')) {
      var teile = String(t.value).split(':');
      navigiere(function (p) {
        if (!teile[0]) { p.delete('kat'); p.delete('inkl'); return; }
        p.set('kat', teile[0]);
        if (teile[1] === '1') p.set('inkl', '1'); else p.delete('inkl');
      });
    } else if (t.matches('select[data-sort]')) {
      var sd = String(t.value).split(':');
      navigiere(function (p) { p.set('sort', sd[0]); p.set('dir', sd[1] === 'desc' ? 'desc' : 'asc'); });
    } else if (t.matches('select[data-nav], input[data-nav]')) {
      var name = t.getAttribute('data-nav');
      navigiere(function (p) { if (t.value) p.set(name, t.value); else p.delete(name); });
    }
  });

  /* ---------- Filterbereich: Seitenleiste (Desktop) bzw. Bottom-Sheet (schmal) ---------- */
  var layout = document.getElementById('listeLayout');
  var panel = document.getElementById('filterPanel');
  var toggle = document.getElementById('filterToggle');
  var backdrop = document.getElementById('filterBackdrop');
  var breit = window.matchMedia ? window.matchMedia('(min-width: 1280px)') : { matches: true };

  function sheetOffen() { return panel && panel.classList.contains('is-open'); }
  function sheetSchliessen() {
    if (!panel) return;
    panel.classList.remove('is-open');
    if (backdrop) backdrop.hidden = true;
    document.body.classList.remove('sheet-open');
    if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
  }
  function sheetOeffnen() {
    panel.classList.add('is-open');
    if (backdrop) backdrop.hidden = false;
    document.body.classList.add('sheet-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    var f = panel.querySelector('summary, a, button, input, select');
    if (f && f.focus) f.focus();
  }
  if (panel && toggle && layout) {
    // Startzustand: breit = Seitenleiste sichtbar (außer eingeklappt), schmal = Sheet geschlossen
    toggle.setAttribute('aria-expanded', breit.matches ? (layout.classList.contains('filter-collapsed') ? 'false' : 'true') : 'false');
    toggle.addEventListener('click', function () {
      if (breit.matches) {
        var zu = layout.classList.toggle('filter-collapsed');
        toggle.setAttribute('aria-expanded', zu ? 'false' : 'true');
        document.cookie = 'filterPanelOpen=' + (zu ? '0' : '1') + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
      } else if (sheetOffen()) {
        sheetSchliessen();
      } else {
        sheetOeffnen();
      }
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-filter-close]'), function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); sheetSchliessen(); if (toggle.focus) toggle.focus(); });
    });
    if (backdrop) backdrop.addEventListener('click', sheetSchliessen);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sheetOffen()) { sheetSchliessen(); if (toggle.focus) toggle.focus(); }
    });
    if (breit.addEventListener) breit.addEventListener('change', function () { if (breit.matches) sheetSchliessen(); });

    // Sheet bleibt über das Neuladen offen (Wahl treffen lädt die Seite neu; sonst müsste man für Marke → Plattform → Typ dreimal öffnen).
    // Gemerkt wird beim Verlassen der Seite, ob das Sheet offen war; schließen (Fertig, Esc, Hintergrund) vergisst es.
    var MERKER = 'listeFilterSheet';
    window.addEventListener('pagehide', function () {
      try { if (sheetOffen()) sessionStorage.setItem(MERKER, '1'); else sessionStorage.removeItem(MERKER); } catch (_) {}
    });
    try {
      if (!breit.matches && sessionStorage.getItem(MERKER) === '1') {
        sessionStorage.removeItem(MERKER);
        panel.classList.add('is-open', 'ohne-animation');
        if (backdrop) backdrop.hidden = false;
        document.body.classList.add('sheet-open');
        toggle.setAttribute('aria-expanded', 'true');
        // Animation erst danach wieder erlauben
        setTimeout(function () { panel.classList.remove('ohne-animation'); }, 50);
      }
    } catch (_) {}
  }

  /* ---------- Darstellung Tabelle/Raster (Cookie "la", ohne Neuladen) ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-ansicht-wahl]'), function (b) {
    b.addEventListener('click', function () {
      var wahl = b.getAttribute('data-ansicht-wahl');
      var inhalt = document.querySelector('.liste-ergebnis-inhalt');
      if (inhalt) inhalt.setAttribute('data-ansicht', wahl);
      Array.prototype.forEach.call(document.querySelectorAll('[data-ansicht-wahl]'), function (x) {
        x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
      });
      document.cookie = 'la=' + wahl + ';path=/sammlung/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
    });
  });

  /* ---------- Tabelle: Klick auf die Zeile öffnet den Eintrag (der Titel bleibt der echte Link) ---------- */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var zeile = e.target.closest ? e.target.closest('tr.liste-zeile') : null;
    if (!zeile || e.target.closest('a, button, input, select, textarea, summary, label')) return;
    var sel = window.getSelection ? String(window.getSelection()) : '';
    if (sel) return;                                   // Text markieren soll nicht navigieren
    var href = zeile.getAttribute('data-href');
    if (href) window.location.href = href;
  });

  /* ---------- iPhone: obere Leiste läuft beim Runterscrollen weg und kommt beim Hochscrollen wieder ---------- */
  var leiste = document.getElementById('appbar');
  var mobil = window.matchMedia ? window.matchMedia('(max-width: 599px)') : { matches: false };
  if (leiste) {
    var letzteY = window.pageYOffset || 0;
    window.addEventListener('scroll', function () {
      var y = window.pageYOffset || 0;
      if (!mobil.matches || leiste.contains(document.activeElement)) { leiste.classList.remove('versteckt'); letzteY = y; return; }
      if (y > letzteY + 8 && y > 80) leiste.classList.add('versteckt');
      else if (y < letzteY - 8 || y <= 0) leiste.classList.remove('versteckt');
      letzteY = y;
    }, { passive: true });
  }

  /* ---------- Untere Navigation: Suche und Mehr ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-nav-suche]'), function (b) {
    b.addEventListener('click', function () {
      var feld = document.getElementById('suchfeld');
      window.scrollTo(0, 0);
      if (leiste) leiste.classList.remove('versteckt');
      if (feld && feld.focus) feld.focus();   // direkt im Tipp-Ereignis, damit iOS die Tastatur öffnet
    });
  });
  var mehr = document.getElementById('mehrDialog');
  if (mehr) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-nav-mehr]'), function (b) {
      b.addEventListener('click', function () {
        if (typeof mehr.showModal === 'function') mehr.showModal(); else mehr.setAttribute('open', '');
      });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-nav-mehr-zu]'), function (b) {
      b.addEventListener('click', function () { if (typeof mehr.close === 'function') mehr.close(); else mehr.removeAttribute('open'); });
    });
    mehr.addEventListener('click', function (e) { if (e.target === mehr && typeof mehr.close === 'function') mehr.close(); });   // Tipp auf den abgedunkelten Rand
  }

  /* ---------- Nach oben ---------- */
  var oben = document.querySelector('.back-to-top');
  if (oben) {
    var wurzel = document.documentElement;
    var pruefen = function () {
      if ((window.scrollY || wurzel.scrollTop || document.body.scrollTop) > 400) oben.classList.add('show');
      else oben.classList.remove('show');
    };
    window.addEventListener('scroll', pruefen, { passive: true });
    pruefen();
    oben.addEventListener('click', function (e) {
      e.preventDefault();
      var weniger = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;   // weniger Bewegung: ohne weiches Scrollen
      try { window.scrollTo({ top: 0, behavior: weniger ? 'auto' : 'smooth' }); } catch (_) { window.scrollTo(0, 0); }
      wurzel.scrollTop = 0; document.body.scrollTop = 0;   // Absicherung für Safari
      if (location.hash !== '#top-marker') setTimeout(function () { location.hash = '#top-marker'; }, 60);
    });
  }

  /* ---------- Zeilenaktionen (Duplizieren/Löschen) per POST mit CSRF ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a.row-action') : null;
    if (!a) return;
    e.preventDefault();
    if (a.dataset.confirm && !window.confirm(a.dataset.confirm)) return;
    var tbl = a.closest('table');
    var p = JSON.parse((tbl && tbl.dataset.ctx) || '{}');   // Filter, Seite, Sortierung (einmal an der Tabelle)
    p.id = a.dataset.id;
    p.csrf = (tbl && tbl.dataset.csrf) || '';
    var f = document.createElement('form');
    f.method = 'post';
    f.action = a.dataset.action;
    Object.keys(p).forEach(function (k) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = p[k];
      f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  });
})();
