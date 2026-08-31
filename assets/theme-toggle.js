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
  });
})();
