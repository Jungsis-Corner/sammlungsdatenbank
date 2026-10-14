/*
 * typeahead.js: Suchfeld mit Vorschlagsliste statt langer Auswahllisten (ohne Bibliothek).
 * Aktiviert jedes <input data-ta="<Schlüssel>"> (Schlüssel siehe api_lookup.php, z. B. hersteller, kategorie, sammlung).
 *
 * Attribute am Eingabefeld:
 *   data-ta          Liste, aus der api_lookup.php die Vorschläge holt
 *   data-ta-hidden   ID eines versteckten Felds, in das die gewählte ID geschrieben wird (wird geleert, sobald der Text von
 *                    der Auswahl abweicht; der Server löst dann einen exakt getippten Namen selbst auf)
 *   data-ta-nav      statt ein Feld zu füllen, die Seite mit diesem URL-Parameter neu laden (Filter in der Liste), Seite 1
 *   data-ta-exclude  ID, die nicht vorgeschlagen wird (nur sammlung)
 *   data-ta-url      Adresse der API (Standard: api_lookup.php neben der Seite)
 * Ohne JavaScript bleibt das Feld ein normales Textfeld.
 * Bedienung: Tippen, ↑ ↓ wählen, Enter übernimmt, Esc schließt, Antippen/Klicken übernimmt.
 */
(function () {
  'use strict';

  // Gestaltung einmal einfügen (helles und dunkles Design über html[data-theme="dark"])
  var css = '' +
    '.ta-wrap{position:relative;display:block;}' +
    '.ta-list{position:absolute;left:0;right:0;top:100%;z-index:1000;margin:2px 0 0;padding:0;list-style:none;' +
      'max-height:min(50vh,320px);overflow:auto;background:#fff;color:#111;border:1px solid #888;border-radius:6px;' +
      'box-shadow:0 4px 14px rgba(0,0,0,.25);}' +
    '.ta-list li{padding:9px 12px;cursor:pointer;line-height:1.3;border-bottom:1px solid #eee;word-break:break-word;}' +
    '.ta-list li:last-child{border-bottom:0;}' +
    '.ta-list li[aria-selected="true"],.ta-list li:hover{background:#dbeaff;}' +
    '.ta-list li.ta-leer{color:#666;cursor:default;font-style:italic;}' +
    'html[data-theme="dark"] .ta-list{background:#1e1e1e;color:#e8e8e8;border-color:#555;}' +
    'html[data-theme="dark"] .ta-list li{border-bottom-color:#333;}' +
    'html[data-theme="dark"] .ta-list li[aria-selected="true"],html[data-theme="dark"] .ta-list li:hover{background:#2f4a73;}' +
    'html[data-theme="dark"] .ta-list li.ta-leer{color:#aaa;}';
  var st = document.createElement('style');
  st.textContent = css;
  document.head.appendChild(st);

  var zaehler = 0;

  function init(input) {
    if (input.dataset.taInit) return;
    input.dataset.taInit = '1';
    var key = input.getAttribute('data-ta');
    var hidden = input.getAttribute('data-ta-hidden') ? document.getElementById(input.getAttribute('data-ta-hidden')) : null;
    var navParam = input.getAttribute('data-ta-nav');
    var exclude = input.getAttribute('data-ta-exclude') || '';
    var apiUrl = input.getAttribute('data-ta-url') || 'api_lookup.php';
    var id = 'ta-list-' + (++zaehler);

    // Feld in einen Wrapper setzen, damit die Liste direkt darunter erscheint
    var wrap = document.createElement('span');
    wrap.className = 'ta-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var list = document.createElement('ul');
    list.className = 'ta-list';
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrap.appendChild(list);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id);
    input.setAttribute('autocomplete', 'off');
    input.removeAttribute('list');

    var items = [];        // [{id, label}]
    var active = -1;
    var timer = null;
    var seq = 0;           // verwirft veraltete Antworten
    var ctrl = null;       // AbortController der laufenden Anfrage
    var gewaehlt = input.value;   // Text der letzten Auswahl (oder des Startwerts)

    function schliessen() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function markieren(i) {
      var lis = list.children;
      for (var k = 0; k < lis.length; k++) lis[k].setAttribute('aria-selected', k === i ? 'true' : 'false');
      active = i;
      if (i >= 0 && lis[i]) {
        input.setAttribute('aria-activedescendant', lis[i].id);
        if (lis[i].scrollIntoView) lis[i].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function zeichnen() {
      list.textContent = '';
      if (!items.length) {
        var leer = document.createElement('li');
        leer.className = 'ta-leer';
        leer.textContent = 'Keine Treffer';
        list.appendChild(leer);
      }
      items.forEach(function (it, i) {
        var li = document.createElement('li');
        li.id = id + '-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', 'false');
        li.textContent = it.label;
        // pointerdown + preventDefault: das Feld behält den Fokus, die Auswahl geht nicht an blur verloren
        li.addEventListener('pointerdown', function (e) { e.preventDefault(); waehlen(i); });
        list.appendChild(li);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      active = -1;
    }

    function waehlen(i) {
      var it = items[i];
      if (!it) return;
      if (navParam) {                       // Filter in der Liste: Seite mit dem Parameter neu laden
        var u = new URL(window.location.href);
        u.searchParams.set(navParam, String(it.id));
        u.searchParams.set('page', '1');
        window.location.href = u.toString();
        return;
      }
      input.value = it.label;
      gewaehlt = it.label;
      if (hidden) hidden.value = String(it.id);
      schliessen();
      input.dispatchEvent(new Event('change', { bubbles: true }));   // z. B. für die Warnung bei ungespeicherten Änderungen
    }

    function laden(q) {
      var mein = ++seq;
      if (ctrl && ctrl.abort) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      var url = apiUrl + '?t=' + encodeURIComponent(key) + '&q=' + encodeURIComponent(q) +
                (exclude ? '&x=' + encodeURIComponent(exclude) : '');
      fetch(url, { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { items: [] }; })
        .then(function (data) {
          if (mein !== seq || document.activeElement !== input) return;
          items = (data && data.items) ? data.items : [];
          zeichnen();
        })
        .catch(function () { /* abgebrochen oder offline: Liste bleibt, wie sie ist */ });
    }

    function planen() {
      clearTimeout(timer);
      timer = setTimeout(function () { laden(input.value.trim()); }, 150);
    }

    input.addEventListener('input', function () {
      // Text weicht von der Auswahl ab: gewählte ID verwerfen (ein exakter Name wird beim Speichern vom Server aufgelöst)
      if (hidden && input.value !== gewaehlt) hidden.value = '';
      planen();
    });
    input.addEventListener('focus', planen);
    input.addEventListener('blur', function () { clearTimeout(timer); setTimeout(schliessen, 120); });
    input.addEventListener('keydown', function (e) {
      var offen = !list.hidden && items.length;
      if (e.key === 'ArrowDown') {
        if (!offen) { planen(); return; }
        e.preventDefault(); markieren((active + 1) % items.length);
      } else if (e.key === 'ArrowUp') {
        if (!offen) return;
        e.preventDefault(); markieren(active <= 0 ? items.length - 1 : active - 1);
      } else if (e.key === 'Enter') {
        if (offen && active >= 0) { e.preventDefault(); waehlen(active); }
      } else if (e.key === 'Escape') {
        if (!list.hidden) { e.preventDefault(); schliessen(); }
      } else if (e.key === 'Tab') {
        schliessen();
      }
    });
  }

  function start() {
    var felder = document.querySelectorAll('input[data-ta]');
    for (var i = 0; i < felder.length; i++) init(felder[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
