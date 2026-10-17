/* login.js – Anmeldung: „Passwort anzeigen“ (Phase 5e-1) */
(function () {
  'use strict';
  var knopf = document.getElementById('pwZeigen');
  var feld = document.getElementById('f_password');
  if (!knopf || !feld) return;
  knopf.hidden = false;
  knopf.addEventListener('click', function () {
    var sichtbar = feld.type === 'text';
    feld.type = sichtbar ? 'password' : 'text';
    knopf.setAttribute('aria-pressed', sichtbar ? 'false' : 'true');
    knopf.setAttribute('aria-label', sichtbar ? 'Passwort anzeigen' : 'Passwort verbergen');
    knopf.querySelector('use').setAttribute('href', sichtbar ? '#i-eye' : '#i-eye-off');
    feld.focus();
  });
})();
