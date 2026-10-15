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
      try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (_) { window.scrollTo(0, 0); }
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
