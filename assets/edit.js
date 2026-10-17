/*
 * edit.js – Verhalten des Bearbeiten-Formulars (edit.php), Phase 5d. Ersetzt die früheren Inline-Skripte und onclick/onsubmit-Attribute.
 *
 *  - Barcode-Scanner (Kamera, BarcodeDetector bzw. ZXing)
 *  - „Sonstiges Beschreibung“ nur zeigen, wenn „Sonstiges“ angehakt ist
 *  - Warnung bei ungespeicherten Änderungen (Links/Knöpfe ohne data-nocheck, Tab schließen)
 *  - Gruppen (<details>): auf dem iPhone (unter 600 px) alle außer der ersten eingeklappt; ein Feld im Fokus öffnet seine Gruppe
 *  - Löschen: Bestätigungsdialog (POST mit CSRF an delete.php)
 *  - deaktivierte Navigationslinks (aria-disabled) folgen nicht
 *  - Tastatur: das fokussierte Feld wird in die Bildschirmmitte gescrollt (iPhone-Tastatur verdeckt nichts)
 */
/* ---------- Barcode-Scanner ---------- */
(function(){
  const input     = document.getElementById('barcodeInput');
  const startBtn  = document.getElementById('startScanBtn');
  const stopBtn   = document.getElementById('stopScanBtn');
  const wrap      = document.getElementById('barcodeScanWrap');
  const video     = document.getElementById('barcodeVideo');
  const statusBox = document.getElementById('barcodeStatus');
  const torchBtn  = document.getElementById('torchBtn');
  const torchLabel = document.getElementById('torchLabel');
  const captureBtn = document.getElementById('captureBtn');
  const canvas     = document.getElementById('barcodeCanvas');

  if (!input || !startBtn || !stopBtn || !wrap || !video || !statusBox) return;

  // Kamera-Constraints: hohe Auflösung + kontinuierlicher Autofokus, wo unterstützt
  const VIDEO_CONSTRAINTS = {
    facingMode: { ideal: 'environment' },
    width:  { ideal: 1920 },
    height: { ideal: 1080 },
    advanced: [{ focusMode: 'continuous' }]
  };

  let stream     = null;
  let detector   = null;  // nativer BarcodeDetector
  let codeReader = null;  // ZXing-Fallback
  let scanTimer  = null;
  let scanning   = false;
  let lastValue  = '';
  let torchOn    = false;
  let torchTrack = null;

  function setStatus(msg, kind) {
    statusBox.textContent = msg || '';
    statusBox.classList.remove('ok', 'err');
    if (kind) statusBox.classList.add(kind);
  }

  function focusBarcode() {
    input.focus();
    try { const len = input.value.length; input.setSelectionRange(len, len); } catch (e) {}
  }

  // Torch (Blitzlicht) an/aus, falls vom Gerät unterstützt
  async function setupTorch(mediaStream) {
    torchTrack = null;
    if (!torchBtn) return;
    const track = mediaStream.getVideoTracks()[0];
    if (!track) { torchBtn.hidden = true; return; }
    const caps = track.getCapabilities ? track.getCapabilities() : {};
    if (caps && caps.torch) {
      torchTrack = track;
      torchBtn.hidden = false;
      torchOn = false;
      torchLabel.textContent = 'Licht an';
    } else {
      torchBtn.hidden = true;
    }
  }

  async function toggleTorch() {
    if (!torchTrack) return;
    try {
      torchOn = !torchOn;
      await torchTrack.applyConstraints({ advanced: [{ torch: torchOn }] });
      torchLabel.textContent = torchOn ? 'Licht aus' : 'Licht an';
    } catch (e) {
      setStatus('Blitzlicht konnte nicht umgeschaltet werden.', 'err');
    }
  }

  // Gemeinsame Ergebnis-Verarbeitung für beide Pfade
  function onBarcodeFound(rawValue) {
    if (!rawValue || rawValue === lastValue) return;
    lastValue = rawValue;
    input.value = rawValue;
    input.dispatchEvent(new Event('input',  { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    setStatus('Barcode erkannt: ' + rawValue, 'ok');
    stopScanner();
    focusBarcode();
  }

  function stopScanner() {
    scanning = false;

    if (scanTimer)   { clearInterval(scanTimer); scanTimer = null; }
    if (codeReader)  { try { codeReader.reset(); } catch (e) {} codeReader = null; }

    if (video.srcObject) {
      video.srcObject.getTracks().forEach(t => t.stop());
      video.srcObject = null;
    }
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }

    torchTrack = null;
    torchOn = false;
    if (torchBtn) { torchBtn.hidden = true; torchLabel.textContent = 'Licht an'; }
    if (captureBtn) captureBtn.hidden = true;

    wrap.hidden     = true;
    stopBtn.hidden  = true;
    startBtn.hidden = false;
  }

  // ---- Pfad A: nativer BarcodeDetector (Chrome, Edge, Android Chrome) ----
  async function startScannerNative() {
    try {
      const supported = await BarcodeDetector.getSupportedFormats();
      const wanted    = ['ean_13','ean_8','upc_a','upc_e','code_128','code_39'];
      const formats   = wanted.filter(f => supported.includes(f));
      detector = new BarcodeDetector({ formats: formats.length ? formats : supported });
    } catch (e) {
      setStatus('Barcode-Scanner konnte nicht initialisiert werden.', 'err');
      focusBarcode(); return;
    }

    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: VIDEO_CONSTRAINTS, audio: false
      });
      video.srcObject = stream;
      await video.play();
      await setupTorch(stream);

      wrap.hidden = false; stopBtn.hidden = false; startBtn.hidden = true; if (captureBtn) captureBtn.hidden = false;
      scanning = true; lastValue = '';
      setStatus('Scanner läuft …', '');

      scanTimer = setInterval(async () => {
        if (!scanning || !detector) return;
        try {
          const barcodes = await detector.detect(video);
          if (barcodes && barcodes.length)
            onBarcodeFound((barcodes[0].rawValue || '').trim());
        } catch (e) {}
      }, 700);

    } catch (e) {
      setStatus('Kamera konnte nicht geöffnet werden. Bitte Berechtigung prüfen.', 'err');
      focusBarcode(); stopScanner();
    }
  }

  // ---- Pfad B: ZXing-Fallback (iOS Safari, Firefox) ----
  function loadZXing() {
    return new Promise((resolve, reject) => {
      if (window.ZXing) { resolve(); return; }
      const s = document.createElement('script');
      s.src     = 'https://unpkg.com/@zxing/library@0.19.2/umd/index.min.js';
      s.onload  = resolve;
      s.onerror = () => reject(new Error('ZXing konnte nicht geladen werden.'));
      document.head.appendChild(s);
    });
  }

  async function startScannerZXing() {
    setStatus('Scanner wird geladen …', '');
    try {
      await loadZXing();
    } catch (e) {
      setStatus('Scanner-Bibliothek konnte nicht geladen werden. Bitte Barcode manuell eingeben.', 'err');
      focusBarcode(); return;
    }

    try {
      codeReader = new ZXing.BrowserMultiFormatReader();

      // Eigenen Stream mit unseren Constraints holen, statt ZXing raten zu lassen
      stream = await navigator.mediaDevices.getUserMedia({
        video: VIDEO_CONSTRAINTS, audio: false
      });
      video.srcObject = stream;
      await video.play();
      await setupTorch(stream);

      wrap.hidden = false; stopBtn.hidden = false; startBtn.hidden = true; if (captureBtn) captureBtn.hidden = false;
      scanning = true; lastValue = '';
      setStatus('Scanner läuft …', '');

      await codeReader.decodeFromStream(stream, video, (result, err) => {
        if (result) onBarcodeFound(result.getText());
        // err ist während der Suche normal – nicht anzeigen
      });

    } catch (e) {
      setStatus('Kamera konnte nicht geöffnet werden. Bitte Berechtigung prüfen.', 'err');
      focusBarcode(); stopScanner();
    }
  }

  // ---- Einzelbild aufnehmen und mit mehreren Zoomstufen auswerten ----
  // Löst zwei typische Live-Scan-Probleme: Bewegungsunschärfe und zu kleine
  // Barcodes im Vollbild. Ein scharfes Standbild wird mehrfach ausgewertet,
  // jeweils auf die Bildmitte vergrößert (100 % / 60 % / 35 % Bildausschnitt).
  async function captureAndDecode() {
    if (!video.videoWidth || !video.videoHeight) return;
    setStatus('Foto wird ausgewertet …', '');

    const vw = video.videoWidth, vh = video.videoHeight;
    canvas.width = vw; canvas.height = vh;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, vw, vh);

    const zoomLevels = [1, 0.6, 0.35]; // Anteil der Bildmitte, der genutzt wird
    let zxingReader = null;

    for (const zoom of zoomLevels) {
      const cw = Math.round(vw * zoom), ch = Math.round(vh * zoom);
      const cx = Math.round((vw - cw) / 2), cy = Math.round((vh - ch) / 2);

      const cropCanvas = document.createElement('canvas');
      // auf min. 1000px Breite hochskalieren, damit kleine Barcodes genug Pixel haben
      const scale = Math.max(1, 1000 / cw);
      cropCanvas.width  = Math.round(cw * scale);
      cropCanvas.height = Math.round(ch * scale);
      const cropCtx = cropCanvas.getContext('2d');
      cropCtx.drawImage(canvas, cx, cy, cw, ch, 0, 0, cropCanvas.width, cropCanvas.height);

      // Pfad A: nativer BarcodeDetector, falls verfügbar
      if ('BarcodeDetector' in window) {
        try {
          if (!detector) {
            const supported = await BarcodeDetector.getSupportedFormats();
            const wanted    = ['ean_13','ean_8','upc_a','upc_e','code_128','code_39'];
            const formats   = wanted.filter(f => supported.includes(f));
            detector = new BarcodeDetector({ formats: formats.length ? formats : supported });
          }
          const results = await detector.detect(cropCanvas);
          if (results && results.length) {
            onBarcodeFound((results[0].rawValue || '').trim());
            return;
          }
        } catch (e) { /* weiter mit nächster Zoomstufe */ }
      } else {
        // Pfad B: ZXing gegen das Standbild
        try {
          await loadZXing();
          if (!zxingReader) zxingReader = new ZXing.BrowserMultiFormatReader();
          const dataUrl = cropCanvas.toDataURL('image/png');
          const result = await zxingReader.decodeFromImageUrl(dataUrl);
          if (result) {
            onBarcodeFound(result.getText());
            return;
          }
        } catch (e) { /* weiter mit nächster Zoomstufe */ }
      }
    }

    if (zxingReader) { try { zxingReader.reset(); } catch (e) {} }
    setStatus('Kein Barcode im Foto erkannt. Näher heran oder anderer Ausschnitt versuchen.', 'err');
  }

  // ---- Einheitlicher Einstieg ----
  async function startScanner() {
    if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
      setStatus('Kamerazugriff wird von diesem Browser nicht unterstützt.', 'err');
      focusBarcode(); return;
    }
    if ('BarcodeDetector' in window) {
      await startScannerNative();
    } else {
      await startScannerZXing();
    }
  }

  startBtn.addEventListener('click', startScanner);
  stopBtn.addEventListener('click', function(){
    stopScanner();
    setStatus('Scan beendet.', '');
    focusBarcode();
  });
  if (torchBtn) torchBtn.addEventListener('click', toggleTorch);
  if (captureBtn) captureBtn.addEventListener('click', captureAndDecode);

  window.addEventListener('beforeunload', stopScanner);
})();

/* ---------- „Sonstiges Beschreibung“ ein-/ausblenden ---------- */
(function(){
  var checkbox = document.querySelector('input[name="Sonstiges"]');
  var descField = document.querySelector('.field[data-col="Sonstiges_Beschreibung"]');
  if (!checkbox || !descField) return;
  function sync(){ descField.hidden = !checkbox.checked; }
  checkbox.addEventListener('change', sync);
  sync();
})();

/* ---------- Deaktivierte Navigationslinks ---------- */
document.addEventListener('click', function(e){
  var a = e.target.closest ? e.target.closest('a[aria-disabled="true"]') : null;
  if (a) e.preventDefault();
});

/* ---------- Gruppen einklappen (nur iPhone) und Fokus öffnet die Gruppe ---------- */
(function(){
  var gruppen = document.querySelectorAll('details.form-group');
  var klein = window.matchMedia ? window.matchMedia('(max-width: 599px)') : { matches: false };
  if (klein.matches) {
    Array.prototype.forEach.call(gruppen, function(d, i){ if (i > 0) d.open = false; });
    Array.prototype.forEach.call(document.querySelectorAll('details[data-klein-zu]'), function(d){ d.open = false; });
  }
  document.addEventListener('focusin', function(e){
    var d = e.target.closest ? e.target.closest('details.form-group') : null;
    if (d && !d.open) d.open = true;
  });
})();

/* ---------- Tastatur (iPhone): fokussiertes Feld sichtbar halten ---------- */
(function(){
  var touch = window.matchMedia ? window.matchMedia('(pointer: coarse)') : { matches: false };
  if (!touch.matches) return;
  document.addEventListener('focusin', function(e){
    var t = e.target;
    if (!t.matches || !t.matches('input:not([type=checkbox]):not([type=hidden]), select, textarea')) return;
    // nach dem Einblenden der Tastatur (Größenänderung) in die Mitte holen
    var weniger = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(function(){ try { t.scrollIntoView({ block: 'center', behavior: weniger ? 'auto' : 'smooth' }); } catch (_) { t.scrollIntoView(); } }, 320);
  });
})();

/* ---------- Löschen bestätigen ---------- */
(function(){
  var dlg = document.getElementById('loeschDialog');
  if (!dlg) return;
  function auf(){ if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', ''); }
  function zu(){ if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open'); }
  Array.prototype.forEach.call(document.querySelectorAll('[data-loeschen]'), function(b){ b.addEventListener('click', auf); });
  Array.prototype.forEach.call(document.querySelectorAll('[data-loeschen-zu]'), function(b){ b.addEventListener('click', zu); });
  dlg.addEventListener('click', function(e){ if (e.target === dlg) zu(); });
})();

/* ---------- Warnung bei ungespeicherten Änderungen ---------- */
(function(){
  var form = document.getElementById('f');
  if (!form) return;

  var isDirty = false;

  function markDirty(){ isDirty = true; }
  form.addEventListener('input',  markDirty, {capture:true});
  form.addEventListener('change', markDirty, {capture:true});

  // Beim Absenden NICHT warnen
  form.addEventListener('submit', function(){ isDirty = false; });

  // Tab schließen / Reload / andere URL
  window.addEventListener('beforeunload', function(e){
    if (!isDirty) return;
    e.preventDefault();
    e.returnValue = '';
  });

  function isSubmitRelated(el){
    if (!el) return false;

    // <button> ohne type ist standardmäßig submit
    var isButtonSubmit = (el.tagName === 'BUTTON' && (el.type === 'submit' || !el.hasAttribute('type')));
    var isInputSubmit  = (el.tagName === 'INPUT'  && el.type === 'submit');

    if (!(isButtonSubmit || isInputSubmit)) return false;

    var insideForm = !!el.closest('form');
    var targetsF   = (el.getAttribute('form') === 'f');

    if (insideForm) {
      var f = el.closest('form');
      if (f && f.id === 'f') return true;
      return targetsF;
    }
    return targetsF;
  }

  // Interne Navigation abfangen: NUR Links + Buttons
  document.addEventListener('click', function(e){
    var el = e.target.closest('a,button');   // kein input hier!
    if (!el) return;

    // Ausnahmen: data-nocheck → keine Warnung
    if (el.hasAttribute('data-nocheck')) return;

    // Summary/Dialog-Schließen u. ä. sind keine Navigation
    if (el.tagName === 'BUTTON' && el.type === 'button') return;

    // Submit-Aktionen dürfen immer durch
    if (isSubmitRelated(el)) return;

    if (!isDirty) return;

    var ok = confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?');
    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
    } else {
      isDirty = false;
    }
  }, true);
})();
