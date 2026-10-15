/*
 * detail.js – Detailseite (view.php), Phase 5c
 *  - [data-lightbox]: Bild groß in <dialog id="lb"> (Schließen-Knopf, Esc und Tipp auf den Rand schließen; der Dialog fängt den Fokus selbst)
 *  - [data-loeschen] / [data-loeschen-zu]: Bestätigungsdialog zum Löschen (das Formular sendet per POST mit CSRF an delete.php)
 *  - Wischen nach links/rechts auf Touch-Geräten: nächster/vorheriger Eintrag (Ziele stehen in data-next/data-prev am <main>)
 */
(function () {
  'use strict';

  function oeffnen(dlg) {
    if (!dlg) return;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
  }
  function schliessen(dlg) {
    if (!dlg) return;
    if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open');
  }

  /* ---------- Bild groß ---------- */
  var lb = document.getElementById('lb');
  var lbImg = document.getElementById('lbImg');
  var bildKnopf = document.querySelector('[data-lightbox]');
  if (lb && lbImg && bildKnopf) {
    bildKnopf.addEventListener('click', function () {
      var gross = bildKnopf.getAttribute('data-gross');
      if (gross && lbImg.getAttribute('src') !== gross) lbImg.setAttribute('src', gross);   // erst beim Öffnen laden
      oeffnen(lb);
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-lb-zu]'), function (b) {
      b.addEventListener('click', function () { schliessen(lb); });
    });
    lb.addEventListener('click', function (e) { if (e.target === lb || e.target === lbImg) schliessen(lb); });
  }

  /* ---------- Löschen bestätigen ---------- */
  var loesch = document.getElementById('loeschDialog');
  if (loesch) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-loeschen]'), function (b) {
      b.addEventListener('click', function () { oeffnen(loesch); });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-loeschen-zu]'), function (b) {
      b.addEventListener('click', function () { schliessen(loesch); });
    });
    loesch.addEventListener('click', function (e) { if (e.target === loesch) schliessen(loesch); });
  }

  /* ---------- Wischen: nächster / vorheriger Eintrag ---------- */
  var haupt = document.getElementById('inhalt');
  if (haupt) {
    var start = null;
    var RAND = 28;          // Wischen vom Bildschirmrand gehört dem Browser (Zurück-Geste)
    var MIN_X = 70;         // Mindestweg waagerecht
    haupt.addEventListener('touchstart', function (e) {
      start = null;
      if (e.touches.length !== 1) return;
      if (document.querySelector('dialog[open]')) return;
      if (e.target.closest && e.target.closest('input, select, textarea, a[target], dialog')) return;
      var t = e.touches[0];
      var breite = window.innerWidth || document.documentElement.clientWidth;
      if (t.clientX < RAND || t.clientX > breite - RAND) return;
      start = { x: t.clientX, y: t.clientY, zeit: Date.now() };
    }, { passive: true });
    haupt.addEventListener('touchend', function (e) {
      if (!start) return;
      var t = e.changedTouches[0];
      var dx = t.clientX - start.x, dy = t.clientY - start.y, dt = Date.now() - start.zeit;
      start = null;
      if (Math.abs(dx) < MIN_X || Math.abs(dx) < 2 * Math.abs(dy) || dt > 900) return;   // klar waagerecht und zügig
      if (window.getSelection && String(window.getSelection())) return;                    // markierter Text: nichts tun
      var ziel = haupt.getAttribute(dx < 0 ? 'data-next' : 'data-prev');
      if (ziel) window.location.href = ziel;
    }, { passive: true });
    haupt.addEventListener('touchcancel', function () { start = null; }, { passive: true });
  }
})();
