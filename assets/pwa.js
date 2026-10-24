/* pwa.js: Service Worker registrieren (aus inc_pwa_footer.php ausgelagert, wegen CSP ohne Inline-Skript) */
(function () {
  'use strict';
  if (!('serviceWorker' in navigator)) return;
  window.addEventListener('load', function () {
    // sw.js nie aus dem HTTP-Cache (Strato liefert es mit 30 Tagen)
    navigator.serviceWorker.register('/sammlung/sw.js', { updateViaCache: 'none' }).catch(console.error);
  });
})();
