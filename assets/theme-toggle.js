// theme-toggle.js - gemeinsam genutzt von ALLEN Seiten.
// Setzt data-theme so frueh wie moeglich (verhindert "weisses Aufblitzen"),
// und erstellt automatisch einen schwebenden Umschalt-Button, falls noch
// keiner mit id="themeToggle" auf der Seite vorhanden ist.
(function(){
  function getCookie(name){
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  function setTheme(theme){
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = 'theme=' + theme + ';path=/;max-age=' + (60*60*24*365) + ';SameSite=Lax';
    var btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = theme === 'dark' ? '☀️' : '🌙';
  }

  // Sofort ausfuehren (Script blockiert das Parsen, laeuft also vor dem CSS-Link,
  // sofern das Script-Tag im <head> vor <link rel="stylesheet"> steht).
  var stored = getCookie('theme');
  document.documentElement.setAttribute('data-theme', stored === 'dark' ? 'dark' : 'light');

  // ---- iOS/WKWebView-Fix: Touch-Events zuverlaessig "aufwecken" ----
  // Bekannter WebKit-Bug, v.a. bei installierten Home-Screen-Apps (PWA,
  // "standalone"-Modus): Ohne mindestens einen registrierten Touch-Event-
  // Listener im Dokument wertet iOS den allerersten Tap nach jedem Neustart
  // der App teils nur als "Hover-Erkennung" statt als echten Klick - Buttons,
  // Inputs und Selects reagieren dann erst beim zweiten Antippen. Ein leerer,
  // passiver touchstart-Listener so frueh wie moeglich behebt das zuverlaessig,
  // ohne das eigentliche Touch-Verhalten zu beeinflussen.
  document.addEventListener('touchstart', function(){}, { passive: true });

  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('themeToggle');
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'themeToggle';
      btn.className = 'btn theme-toggle theme-toggle-floating';
      btn.title = 'Hell/Dunkel umschalten';
      // Bewusst an <html> gehaengt (nicht an <body>), damit der Button
      // vom Invertierungs-Filter auf <body> unberuehrt bleibt und beim
      // Scrollen korrekt fixiert bleibt.
      document.documentElement.appendChild(btn);
    }
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    btn.textContent = current === 'dark' ? '☀️' : '🌙';
    btn.addEventListener('click', function(){
      var now = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      setTheme(now === 'dark' ? 'light' : 'dark');
    });

    // ---- Datumsfelder: kleinen "✖"-Button zum Leeren ergänzen ----
    // (iOS zeigt im nativen Datums-Picker keine zuverlässige Möglichkeit,
    // ein bereits gesetztes Datum wieder auf leer zu setzen.)
    document.querySelectorAll('input[type="date"]').forEach(function(inp){
      if (inp.closest('.date-with-clear')) return; // schon verpackt
      var wrapper = document.createElement('span');
      wrapper.className = 'date-with-clear';
      inp.parentNode.insertBefore(wrapper, inp);
      wrapper.appendChild(inp);

      var clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'date-clear-btn';
      clearBtn.title = 'Datum leeren';
      clearBtn.textContent = '✖';
      clearBtn.addEventListener('click', function(){
        inp.value = '';
        inp.dispatchEvent(new Event('input',  { bubbles: true }));
        inp.dispatchEvent(new Event('change', { bubbles: true }));
      });
      wrapper.appendChild(clearBtn);
    });

    // ---- Text+Datalist-Suchfelder: verstecktes ID-Feld synchronisieren ----
    // Format der Datalist-Einträge: "Anzeigetext (#ID)" - wird per Regex
    // ausgelesen und in das zugehörige Hidden-Feld geschrieben. Nach einer
    // erfolgreichen Auswahl wird die "(#ID)"-Kennung aus der Anzeige wieder
    // entfernt, damit nur noch der saubere Name sichtbar bleibt - die ID
    // steckt weiterhin im versteckten Feld.
    document.querySelectorAll('.lookup-search[data-hidden-target]').forEach(function(searchInput){
      var hidden = document.getElementById(searchInput.getAttribute('data-hidden-target'));
      if (!hidden) return;
      function sync(){
        var m = searchInput.value.match(/\(#(\d+)\)\s*$/);
        if (m) {
          hidden.value = m[1];
          searchInput.value = searchInput.value.replace(/\s*\(#\d+\)\s*$/, '');
        } else {
          hidden.value = '';
        }
      }
      sync(); // beim Laden: falls vorbelegt, Anzeige direkt bereinigen
      searchInput.addEventListener('input', sync);
      searchInput.addEventListener('change', sync);
    });
  });
})();
