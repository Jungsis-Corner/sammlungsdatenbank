# Übergabe: Stand der Überarbeitung

Stand: 17.10.2026, nach Abschluss von D11, Phase 3 (außer B5/B6), Phase 4 und Phase 5 Welle 5a, 5b, 5c und 5d (zweiter Rechner, WSL). Dieses Dokument ist für eine neue Claude-Code-Sitzung gedacht.
Es enthält **keine** Zugangsdaten, keine Namen von Personen und keine Serveradressen.

## 1. Einstieg in eine neue Sitzung

> Lies CLAUDE.md, docs/Überarbeitungsplan Sammlungsdatenbank.md und docs/uebergabe.md. Wir machen mit der nächsten offenen
> Aufgabe aus Abschnitt 2 weiter. Zeige mir vor jeder Datenbank- oder Mehrdatei-Änderung zuerst den Plan.

Vorher `git status` (muss sauber sein), `git fetch --all --tags`, `git checkout main && git pull`.
Die Plandatei ist **nicht im Repo** (siehe Abschnitt 3) und muss auf dem Rechner liegen.

## 1a. Wechsel auf den anderen Rechner (Checkliste)

1. `git status` (sauber?), dann `git fetch --all --tags` und `git checkout main && git pull` (holt D3 bis D11).
2. **Nicht im Repo, muss auf dem Rechner liegen** (vom WSL-Rechner kopieren, falls noch nicht vorhanden):
   - `docs/Überarbeitungsplan Sammlungsdatenbank.md` (bleibt lokal, nie committen)
   - `docs/bestand/` (Sammlungsdaten, nie committen): u. a. `dump.sql` (Live-Stand vom 13.10.2026 vor D3), `kategorie_mapping_vorschlag.csv` (freigegebene Zuordnung, nur zur Nachvollziehbarkeit), `05_dubletten.csv`, `06_kategorien.csv`, `werkzeuge/`
3. Die lokale **Testumgebung** (Abschnitt 6) existiert nur auf dem WSL-Rechner (Wegwerf-MySQL, App-Kopie) und wird bei Bedarf neu aufgebaut. Für reine Doku- oder Planarbeit ist sie nicht nötig.
4. Der Server ist auf dem Stand von `main` (alle Uploads und Migrationen bis D10 sind bestätigt, D11 ist reine Dokumentation). Ein Upload steht nicht aus.
5. Nächster Schritt: Phase 5 (UI/UX und Responsive Design) planen. **HSTS** frühestens am 20.10.2026 auf ein Jahr (eigener Schritt in der Root-`.htaccess`). Zurückgestellt: B5/B6 (Phase 3), D12. D12 (Altlasten) frühestens nach vier Wochen Stabilisierung.

## 2. Wo wir stehen

| Phase | Stand |
| --- | --- |
| 1 Sicherheit | S1 bis S8 sind umgesetzt, gemergt und auf dem Server. S9 (CSP ohne `unsafe-inline`) kommt laut Plan erst nach Phase 5 |
| 2 Datenbank | **D1 bis D11 sind abgeschlossen**, alles gemergt und auf dem Server (Code hochgeladen, Migrationen bis `2026-10-13_08_kategorie_hierarchie.sql` eingespielt und geprüft). Offen: **D12** (Altlasten, nur mit deiner Freigabe nach einem Export) |
| 3 bis 7 | nicht begonnen |

Eingespielte Migrationen (Live): `0000_login_log`, `2026-10-13_01_login_versuche`, `_02_schema_migrations`, `_03_bereinigung`, `_04_typen`,
`_05_lookup_unique`, `_06_geld`, `_07_fremdschluessel`, `_08_kategorie_hierarchie`, `2026-10-14_01_bild_pfade`. `schema.sql` bildet diesen Stand ab (geprüft: Struktur
identisch zu einer Datenbank aus Live-Dump plus allen Migrationen).

Sicherungstabellen auf dem Server (bleiben bis D12): `bak_d3_*`, `bak_d6_geld`, `bak_d9_kategorie`, `bak_b2_bild`.

Git-Stand: `main` = `origin/main` (Stand nach D11). Tags `vor-phase-1` und `vor-phase-2` sind auf GitHub. Auf dem zweiten Rechner (WSL) sind alle
erledigten lokalen Branches gelöscht; auf GitHub gibt es die Branches `d3-…` bis `d11-…` noch (alle in `main` enthalten, können mit
`git push origin --delete <name>` entfernt werden, wenn gewünscht). Auf dem anderen Rechner alte lokale Branches mit `git branch -d` aufräumen.

## 3. Offene Punkte

- **D12** (siehe Abschnitt 7): Altspalten und `bak_*`-Tabellen entfernen, erst nach Export durch Jungsi und mit Freigabe.
- **HSTS** frühestens am 20.10.2026 auf ein Jahr erhöhen (siehe Abschnitt 4).
- **Strikter SQL-Modus:** `edit.php` scheitert dort an `Einkaufsdatum = ''` (Altlast; das Duplizieren ohne Kosten speichert deshalb auch `0000-00-00`). Live nicht-strikt, daher kein Problem; bei einer Umstellung vorher beheben (`''` zu `NULL`).
- **Dubletten** aus `docs/bestand/05_dubletten.csv` (17 Kandidaten, vor allem Hersteller) entscheidet Jungsi selbst mit der Zusammenführen-Funktion in den Pflegeseiten (E5, nichts automatisch).
- **Neue Kategorie-Knoten** (Atari, Apple, Commodore, Commodore Amiga, Philips) sind in der Kategorie-Auswahl wählbar; sie sind nur als Eltern gedacht.
- Lokale Hilfsdateien im Projektordner (`phpinfo.php`, `wp-config.php`, `debug_session.php`, `token_test.php`, `logtest.php`, `mini-finder.php`, `php-error.log`, `readme.txt`, `var/`) sind ignoriert, dürfen aber nie auf den Server (Liste in `CLAUDE.md`, Abschnitt Deployment).

## 4. Entscheidungen von Jungsi (gelten weiter)

- Gastzugang bleibt (`gast`/`gast`, bewusst öffentlich). Export für Gäste aus. Felder für Gäste wie bisher verborgen.
- HSTS steht auf `max-age=300`. **Frühestens am 20.10.2026** auf ein Jahr (31536000) erhöhen, vorher alle Subdomains prüfen.
- Spalten werden nicht umbenannt (E7). `Sammlung.Sonstiges` ist **keine** tote Spalte (Checkbox „Sonstiges dabei“) und wird nicht gelöscht.
- Die sechs Altspalten in `Sammlung` bleiben bis D12 und werden erst entfernt, **nachdem Jungsi die Daten exportiert hat**.
- Box: eigene Lookup-Tabelle mit Pflegeseite, Namen „Box 1“ bis „Box 13“, „Box 99“, „Regal“.
- Kategorien: Struktur Marke → Plattform → Spiele/Anwendungen, kein Zusammenführen, Namenskorrekturen direkt, fünf neue Knoten, Spiele ohne Plattform direkt unter der Marke.
- Preis aus dem Einkauf geht bei der Übernahme in `Einkaufspreis` und `Wert`. Ungültige Geldeingabe wird `NULL`.
- `docs/bestand/` wird **nie** committet (enthält Sammlungsdaten). `docs/Überarbeitungsplan Sammlungsdatenbank.md` bleibt lokal (`.gitignore`), nie committen oder pushen.

## 5. Wichtige Erkenntnisse

- Live-Server: **MySQL 8.0.36, PHP 8.4**, Strato, **nicht-strikter** SQL-Modus (Checkboxen speichern `''` in Zahlspalten). PHP-Mindestversion für den Code: 8.1.
- Die Spalte `Geaendert_am` hat `ON UPDATE CURRENT_TIMESTAMP`: Jedes `UPDATE` ändert sie. Datenmigrationen setzen `Geaendert_am = Geaendert_am`.
- Als Admin sieht man den Wartungsmodus (503) nie. Zum Prüfen privates Fenster oder abgemeldet.
- Reihenfolge Code/Migration je nach Fall: siehe `migrations/README.md` (additiv: erst Migration; kompatibel: erst Code; unverträglich: Wartungsmodus).
- Fremdschlüssel: `Sammlung` → alle Lookups mit `RESTRICT`, `VerbautIn` → `Sammlung.ID` mit `SET NULL`; `Einkauf` → Kategorie/Verkäufer mit `SET NULL`. Die Verweise je Lookup stehen zentral in `lookup_refs()` (`inc_security.php`).
- Der phpMyAdmin-Export „Abfrageergebnis“ funktioniert bei den Abfragen aus `docs/bestand.sql` nicht; stattdessen ein SQL-Dump, den Claude lokal einspielt.
- Der Dump enthält `Geaendert_am` nicht in den `INSERT`-Anweisungen; Vergleiche nur innerhalb eines Imports.
- Bilder liegen unter `…/blog/wp-content/uploads/` (WordPress liegt im Ordner `blog` neben `sammlung` auf demselben Webspace, per Dateisystem lesbar). 136 Einträge haben Unterordner (`2014/11/…`), 50 Namen enthalten Umlaute, 619 Werte `//blog`. WordPress erzeugt Zwischengrößen (z. B. 225x300 mit ca. 32 KB). 38 kaputte NAS-Pfade wurden in D3 auf `''` gesetzt.

## 6. Zweiter Rechner (WSL) und lokale Testumgebung

Claude Code läuft in WSL (Ubuntu). Git-E-Mail ist die GitHub-noreply-Adresse (sonst GH007). Die echte `config.php` wird nie geladen oder kopiert.
Installiert (apt): PHP 8.5.4 (CLI, `mysqli`, `mbstring`), MySQL 8.4.11 (Dienst nicht gestartet). `sudo` braucht ein Passwort, Pakete installiert Jungsi selbst (`! sudo apt install …`).

- **MySQL-Wegwerf-Instanz** im Scratchpad, Port 3399, eigener Datenordner:
  `mysqld --no-defaults --initialize-insecure --datadir=<ordner>/data`, dann
  `mysqld --no-defaults --datadir=<ordner>/data --port=3399 --bind-address=127.0.0.1 --mysqlx=OFF --socket=<ordner>/sock --mysqlx-socket=<ordner>/xsock --pid-file=<ordner>/pid --log-error=<ordner>/err.log`.
  Client: `mysql -uroot -h127.0.0.1 -P3399 --default-character-set=utf8mb4`. Danach `SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'` (Live ist nicht-strikt).
- **App-Kopie ohne echte `config.php`:** `git archive HEAD | tar -x -C <ordner>/www/sammlung`, eigene Test-`config.php` (DB `127.0.0.1`, root ohne Passwort, `ADMIN_PASSWORD_HASH` für ein Testpasswort, `SESSION_COOKIE_SECURE` false, `LOGIN_HASH_SALT`, `ERROR_LOG_PATH` und **auch `APP_TITLE`, `APP_TITLE_MUSEUM`, `APP_PWA_NAME`, `APP_PWA_SHORT_NAME`**, sonst 500er).
  Server: `php -d mysqli.default_port=3399 -S 127.0.0.1:8124` im Ordner `www`. In der Kopie `SET time_zone = 'Europe/Berlin'` durch `'+02:00'` ersetzen (nur Kopie).
  Später geänderte Dateien **einzeln benannt** in die Kopie kopieren, nie pauschal `*.php` (sonst landet die echte `config.php` in der Kopie).
- **Werkzeuge** in `docs/bestand/werkzeuge/` haben feste Windows-Pfade des ersten Rechners; vor Gebrauch in einer Kopie anpassen: `smoke.sh` (76 Seiten, `view.php?id=3` ist ein erwarteter 404), `save_test.sh`, `checksum2.php`, `nas_backup.php` u. a.
- **Ablauf einer Migrationsprüfung:** frische Datenbank aus dem Dump plus die vorherigen Migrationen, Prüfsummen notieren, Migration zweimal laufen lassen (zweiter Lauf ändert nichts), `DOWN` ausführen und Prüfsummen vergleichen, `schema.sql` einspielen und die Struktur (`information_schema`) mit der migrierten Datenbank vergleichen, App-Kopie mit `smoke.sh` und eigenen Tests prüfen, PHP-Fehlerlog mitlesen.
- Den Dump `docs/bestand/dump.sql` (Live-Stand vom 13.10.2026, **vor** D3) nicht überschreiben. Für neue Tests einen **frischen** Dump ziehen oder D3 bis D9 darauf anwenden.

## 7. Weiterer Fahrplan

- **D12 (Altlasten, nur mit Freigabe):** Nach mindestens vier Wochen Stabilisierung die Altspalten in `Sammlung` und `Kategorie.Spiele` entfernen (Jungsi exportiert vorher die Daten), die `bak_*`-Tabellen löschen (`DROP TABLE`). Spaltenumbenennungen sind laut E7 nicht Teil dieser Runde.
- **Phase 3 Bilder** in drei Schritten: **Schritt 1 erledigt** (`inc_image.php`: `image_url()`, `image_exists()`, `image_img()`, Altwerte lesen, `alt`/`lazy`/Maße/Platzhalter in Liste, Detail, Bearbeiten; ohne Datenbankänderung). **Schritt 2 erledigt** (`thumb.php`: nur eingeloggt, Breiten aus Whitelist, zuerst WordPress-Zwischengrößen, sonst GD, Cache `cache/thumbs/`, bei jedem Problem das Original; `srcset`; `cache/.htaccess` und `medien/.htaccess` ohne `php_flag`; Service-Worker-Bildcache mit höchstens 200 Einträgen; auf dem Server geprüft: Cache-Ordner 403, `thumb.php?f=../config.php` 400). **Schritt 3 erledigt** (Migration `2026-10-14_01_bild_pfade.sql`: `SammlungBild1` enthält nur noch den relativen Pfad, Sicherung `bak_b2_bild` bis D12; `edit.php` kürzt eingefügte WordPress-URLs beim Speichern; `bildpruefung.php` zeigt GD-Status, fehlende Dateien, Originale über 2 MB, verwaiste Dateien in `medien/`). Stand der Bildprüfung auf dem Server: 3.756 Einträge mit Bild, 4 mit fehlender Datei (von Jungsi zu korrigieren), 3 Originale über 2 MB. **Offen:** Upload und Auswahl im lokalen Modus (B5, B6), von Jungsi auf später verschoben; außerdem B-Reste aus dem Plan nur bei Bedarf. Lokal ist GD installiert (auf dem WSL-Rechner). Die Testumgebung im Scratchpad geht bei einem WSL-Neustart verloren und wird dann neu aufgebaut: MySQL, Datenbank aus `dump.sql` plus Migrationen D3 bis D9, App-Kopie per `git ls-files`, Testbilder mit GD. Danach **4 Performance** (Schritte: **A erledigt** = Typeahead mit `api_lookup.php` und `assets/typeahead.js` für Hersteller, Publisher, Verkäufer, Kategorie, „Gehört zu“, Listenfilter und Zusammenführen; Bearbeiten von über 300 KB auf 35 KB, Liste auf 130 KB, live bestätigt. **B erledigt** = Seitengröße 25/50/100 (Cookie `pp`, `page_size()` in `inc_filter.php`, von `index.php` und `delete.php` benutzt; beim Wechsel bleibt der erste sichtbare Eintrag sichtbar), Sprungfeld, eine Aggregatabfrage statt dreien, nur angezeigte Spalten, Duplizieren/Löschen lesen den Kontext aus `data-ctx` an der Tabelle, Leerraum der Zeilen entfernt; Liste (25 Einträge) lokal 69 KB; gleiche Titel werden zusätzlich nach `ID` sortiert (feste Reihenfolge in Liste, Detail, Bearbeiten, Export). **C erledigt** = `EXPLAIN` der häufigsten Abfragen: alle Filter nutzen die Indizes aus D7, Gesamtscans nur ohne Filter oder bei Teilstring-Suche (2 bis 10 ms), keine Index-Migration nötig; Live-Header geprüft: HTML `no-store`, Sicherheitsheader je einmal, aber `text/javascript` war unkomprimiert und `sw.js` hatte 30 Tage HTTP-Cache, behoben mit `assets/.htaccess` (DEFLATE für `text/javascript`) und `updateViaCache: "none"` in `inc_pwa_footer.php`. Bewusst nicht: P6 Statistik-Cache, P7 CSS zusammenlegen). **Messergebnis Phase 4** (Liste 25 Einträge: 304 KB → ca. 69 KB lokal; Bearbeiten 356 KB → 33 KB lokal, live über 300 KB → 35 KB; Liste live 130 KB bei 50 Einträgen; Bilder je Seite statt Originalen nur Miniaturen im KB-Bereich); LCP-Messung mit Lighthouse steht aus (optional, Jungsi),  **5** UI in Wellen (a: Fundament, b: Liste/Filter, c: Detail, d: Formulare, e: Verwaltung/Statistik/Anmeldung/PWA; Kaskade Marke/Plattform/Typ, Statistik-Kacheln U16). **Welle 5a erledigt und ausgerollt** (`assets/base.css` v=3 mit Design-Tokens, einheitlichen Knöpfen: Hauptfarbe `--primary`, neutrale Zweitknöpfe, Rot nur Löschen, Filterknöpfe neutral/aktiv blau; Fokus, Touch 44 px, Linkfarbe; `inc_icons.php` mit `icon()` statt Emoji in Knöpfen/Menüs; Skip-Link, `<main id="inhalt">`, Seitentitel; `theme-toggle.js` v=5, `CACHE_NAME` v8). Lehre: In `:is(...)`-Listen zählt der stärkste Selektor für die ganze Liste, Attributselektoren zählen wie Klassen; nur einfache Klassen hineinschreiben. **Welle 5b Rollout 1 erledigt und ausgerollt** (Liste ohne Inline-CSS/JS: `assets/liste.css` v=3, `assets/liste.js` v=4; Filter als Seitenleiste ab 1280 px, darunter Bottom-Sheet, das nach einer Auswahl offen bleibt (sessionStorage); Chips für aktive Filter; Kategorie-Kaskade Marke/Plattform/Typ über `kategorie_kaskade()` in `inc_filter.php`, Parameter `kat`/`inkl`; Material als Liste; `data-nav`-Felder statt `onchange`). **Rollout 2 erledigt und ausgerollt** (`liste.css` v=7, `liste.js` v=5: ein HTML-Aufbau `<table class="list">` mit ARIA-Rollen, als Karten < 600 px, Raster 600–899 px, Tabelle ab 900 px; Umschalter Tabelle/Raster im Cookie `la`; Zusatzspalten Material/Original-Homebrew ab 1280 px, Verpackung/Standort/Box/Einkaufsdatum ab 1600 px; Zeilenaktionen nur Bearbeiten und Löschen; Sortierauswahl unter 900 px; leerer Zustand; Zum-Verkauf-Abzeichen oben rechts auf Karte/Raster). Lehre: Spezifität bei Überschreibungen von `table.list td{…}` beachten (`table.list td.c-xyz`), und langer Text darf ein Abzeichen nicht wegclampen. **Rollout 3 erledigt und ausgerollt, damit Welle 5b abgeschlossen** (`inc_nav.php`: `nav_links()`, `nav_appnav()`, `nav_bottom()`; obere Leiste `header.appbar` mit Titel als `h1`, Suche, Links, Theme-Knopf, unter 600 px sticky und beim Runterscrollen ausgeblendet; untere Leiste nur unter 600 px mit Sammlung/Suche/Hinzufügen/Mehr-Dialog; Seitenwechsel mit Zurück/Weiter und „Seite X von Y", unten fest über der unteren Leiste; `liste.css` v=9, `liste.js` v=6). Lehre: Textlinks ohne Klasse färbt `base.css` (`a:not([class])`), in eigenen Leisten deshalb Selektoren mit Elementnamen verstärken; Navigation gibt es bisher nur in `index.php`, die übrigen Seiten bekommen sie in 5c bis 5e. **Welle 5c erledigt und ausgerollt** (Detailseite `view.php`: `assets/detail.css` v=1, `assets/detail.js` v=1, gemeinsame obere Leiste `assets/nav.css` v=1 für Liste und Detail; Bild oben bzw. ab 900 px links haftend, Beschreibungsliste `dl` in Gruppen, leere Felder entfallen, Chips, Bild groß als `<dialog>`, Löschen mit Bestätigungsdialog per POST/CSRF an `delete.php`, feste Aktionsleiste unter 600 px, Wischen links/rechts zum nächsten/vorherigen Eintrag; es gibt nur ein Bild pro Eintrag, daher keine Wischgalerie). Lehre: Beim Auslagern von CSS in eine neue Datei alle Seiten prüfen, die die Regeln brauchen (`nav.css` fehlte zuerst in `view.php`). **Welle 5d erledigt und ausgerollt** (Formulare `edit.php` und `einkauf_edit.php`: `assets/edit.css` v=2, `assets/edit.js` v=1, ohne `app.css`/Inline-Code; Gruppen als `<details>` [iPhone nur die erste offen], `label for` an allen Feldern, `inputmode`, Speichern/Abbrechen fest unten auf dem iPhone, Löschen-Dialog, Bild oben, Fokus-Scroll; `einkauf_edit.php` mit Typeahead statt Datalisten und neuem Knopf „Als NEU speichern“; Feldfehler am Feld entfallen, weil `edit.php` serverseitig nichts prüft). Lehre: iOS-Datumsfelder haben eine eigene Mindestbreite (`appearance:none; min-width:0`). **Nächste Welle: 5e (Verwaltungsseiten, `einkauf.php`, Statistik, Anmeldung, Fehlerseiten, Export, PWA, Drucken, Navigation auf den übrigen Seiten).** Lehre: iPad quer hat nur 1024–1180 px, Seitenleiste deshalb erst ab 1280 px. Noch offen aus 5a: Emoji in Filter-Chips der Liste (5b) und Statistik-Überschriften (5e), feste Farben in den Inline-`<style>`-Blöcken (wandern nach 5b/5e in Dateien). Lokal: Zeitzonentabellen müssen in der Test-MySQL geladen sein (`mysql_tzinfo_to_sql`), sonst scheitert `SET time_zone` in `view.php`/`edit.php`, **6** Barrierefreiheit, **7** Tests. Siehe Plan.

## 8. Arbeitsregeln (aus CLAUDE.md und aus dieser Sitzung)

- Deutsch in allem, was Jungsi sieht. Kleine Schritte, bei Schema- oder Mehrdatei-Änderungen **zuerst den Plan zeigen**.
- Pro Aufgabe ein Branch, kleine thematische Commits mit deutscher Message. Commit-Trailer: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Nach jeder PHP-Änderung `php -l`. Migrationen **nicht** selbst gegen die Live-Datenbank ausführen: Jungsi spielt sie in phpMyAdmin ein (vorher Dump).
- Am Ende jeder Aufgabe die Dateiliste für FileZilla nennen (inklusive Migration). `.htaccess` immer zuletzt hochladen, vorher sichern.
- Nie lesen, ändern oder committen: `config.php`. Keine Zugangsdaten, keine Namen von Personen oder Serveradressen in committeten Dateien (das Repo ist öffentlich).
- Erst nach Jungsis Freigabe mergen und pushen. Keine force-Pushes. Beim Merge ergänzt sich `main` per `--ff-only`.
- Beobachtungen von Jungsi haben Vorrang vor Annahmen: erst prüfen, was live tatsächlich passiert, dann den Ansatz anpassen.
