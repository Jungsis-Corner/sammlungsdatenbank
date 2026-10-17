/*
 * verwaltung.js – Verwaltungsseiten (Pflegeseiten, Kategorien, Bildprüfung), Phase 5e-2. Ersetzt onclick/onsubmit-Attribute.
 *
 *  - button[data-loeschen]: Löschen-Dialog (#loeschDialog) füllen und öffnen. data-id, data-name; data-sperre = Text, wenn Löschen gesperrt ist
 *    (Knopf „Löschen“ dann ausgeblendet); data-hinweis = Zusatztext, wenn gelöscht werden darf.
 *  - button[data-merge-pruefen]: Zusammenführen bestätigen (#mergeDialog); das Formular #mergeForm wird erst nach dem Dialog gesendet.
 *  - tr.zeile-klick[data-href]: Tipp/Klick auf die Zeile öffnet den Eintrag zum Bearbeiten (der Name bleibt der echte Link)
 *  - [data-dialog-zu]: Dialog schließen; Tipp auf den Rand schließt ebenfalls.
 */
(function () {
  'use strict';

  function auf(d) { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }
  function zu(d) { if (typeof d.close === 'function') d.close(); else d.removeAttribute('open'); }

  Array.prototype.forEach.call(document.querySelectorAll('dialog.v-dialog'), function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) zu(d); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-dialog-zu]'), function (b) {
    b.addEventListener('click', function () { var d = b.closest('dialog'); if (d) zu(d); });
  });

  /* ---------- Zeile antippen = Bearbeiten ---------- */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var zeile = e.target.closest ? e.target.closest('tr.zeile-klick') : null;
    if (!zeile || e.target.closest('a, button, input, select, textarea, summary, label')) return;
    if (window.getSelection && String(window.getSelection())) return;   // Text markieren soll nicht navigieren
    var href = zeile.getAttribute('data-href');
    if (href) window.location.href = href;
  });

  /* ---------- Löschen ---------- */
  var loesch = document.getElementById('loeschDialog');
  if (loesch) {
    var text = document.getElementById('loeschText');
    var feld = document.getElementById('loeschId');
    var ok = document.getElementById('loeschOk');
    Array.prototype.forEach.call(document.querySelectorAll('button[data-loeschen]'), function (b) {
      b.addEventListener('click', function () {
        var name = b.getAttribute('data-name') || '';
        var sperre = b.getAttribute('data-sperre') || '';
        var hinweis = b.getAttribute('data-hinweis') || '';
        feld.value = b.getAttribute('data-id') || '';
        if (sperre) {
          text.textContent = '„' + name + '“ kann nicht gelöscht werden: ' + sperre;
          ok.hidden = true;
        } else {
          text.textContent = '„' + name + '“ wird unwiderruflich gelöscht.' + (hinweis ? ' ' + hinweis : '');
          ok.hidden = false;
        }
        auf(loesch);
      });
    });
  }

  /* ---------- Zusammenführen ---------- */
  var merge = document.getElementById('mergeDialog');
  var mergeForm = document.getElementById('mergeForm');
  var pruefen = document.querySelector('button[data-merge-pruefen]');
  if (merge && mergeForm && pruefen) {
    pruefen.addEventListener('click', function () {
      if (!mergeForm.reportValidity()) return;
      var ziel = mergeForm.querySelector('input[name="merge_into"]').value.trim();
      var quelle = pruefen.getAttribute('data-name') || '';
      document.getElementById('mergeText').textContent =
        'Alle Verweise von „' + quelle + '“ werden auf „' + ziel + '“ umgestellt, danach wird „' + quelle + '“ gelöscht.';
      auf(merge);
    });
  }
})();
