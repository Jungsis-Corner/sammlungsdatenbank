# Übergabe: Stand der Überarbeitung

Stand: 13.10.2026, nach Abschluss von D11 (zweiter Rechner, WSL). Dieses Dokument ist für eine neue Claude-Code-Sitzung gedacht.
Es enthält **keine** Zugangsdaten, keine Namen von Personen und keine Serveradressen.

## 1. Einstieg in eine neue Sitzung

> Lies CLAUDE.md, docs/Überarbeitungsplan Sammlungsdatenbank.md und docs/uebergabe.md. Wir machen mit der nächsten offenen
> Aufgabe aus Abschnitt 2 weiter. Zeige mir vor jeder Datenbank- oder Mehrdatei-Änderung zuerst den Plan.

Vorher `git status` (muss sauber sein), `git fetch --all --tags`, `git checkout main && git pull`.
Die Plandatei ist **nicht im Repo** (siehe Abschnitt 3) und muss auf dem Rechner liegen.

## 2. Wo wir stehen

| Phase | Stand |
| --- | --- |
| 1 Sicherheit | S1 bis S8 sind umgesetzt, gemergt und auf dem Server. S9 (CSP ohne `unsafe-inline`) kommt laut Plan erst nach Phase 5 |
| 2 Datenbank | **D1 bis D11 sind abgeschlossen**, alles gemergt und auf dem Server (Code hochgeladen, Migrationen bis `2026-10-13_08_kategorie_hierarchie.sql` eingespielt und geprüft). Offen: **D12** (Altlasten, nur mit deiner Freigabe nach einem Export) |
| 3 bis 7 | nicht begonnen |

Eingespielte Migrationen (Live): `0000_login_log`, `2026-10-13_01_login_versuche`, `_02_schema_migrations`, `_03_bereinigung`, `_04_typen`,
`_05_lookup_unique`, `_06_geld`, `_07_fremdschluessel`, `_08_kategorie_hierarchie`. `schema.sql` bildet diesen Stand ab (geprüft: Struktur
identisch zu einer Datenbank aus Live-Dump plus allen Migrationen).

Sicherungstabellen auf dem Server (bleiben bis D12): `bak_d3_*`, `bak_d6_geld`, `bak_d9_kategorie`.

Git-Stand: `main` = `origin/main`. Tags `vor-phase-1` und `vor-phase-2` sind auf GitHub. Alte lokale Branches (`s1-…` bis `s8-…`, `d1-…` bis
`d11-…`, `museum-exit`) sind in `main` enthalten und können gelöscht werden (`git branch -d`).

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
- Bilder liegen unter `…/blog/wp-content/uploads/` (nicht `/wp-content/uploads/`), flach ohne Jahr/Monat, 619 Einträge mit Doppelschrägstrich `//blog`. Phase 3: `IMAGE_WP_BASE` entsprechend; 38 kaputte NAS-Pfade wurden in D3 auf `''` gesetzt.

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
- **Phase 3** Bilder (WordPress und lokal), **4** Performance, **5** UI (Kaskade Marke/Plattform/Typ, Statistik-Kacheln U16), **6** Barrierefreiheit, **7** Tests. Siehe Plan.

## 8. Arbeitsregeln (aus CLAUDE.md und aus dieser Sitzung)

- Deutsch in allem, was Jungsi sieht. Kleine Schritte, bei Schema- oder Mehrdatei-Änderungen **zuerst den Plan zeigen**.
- Pro Aufgabe ein Branch, kleine thematische Commits mit deutscher Message. Commit-Trailer: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Nach jeder PHP-Änderung `php -l`. Migrationen **nicht** selbst gegen die Live-Datenbank ausführen: Jungsi spielt sie in phpMyAdmin ein (vorher Dump).
- Am Ende jeder Aufgabe die Dateiliste für FileZilla nennen (inklusive Migration). `.htaccess` immer zuletzt hochladen, vorher sichern.
- Nie lesen, ändern oder committen: `config.php`. Keine Zugangsdaten, keine Namen von Personen oder Serveradressen in committeten Dateien (das Repo ist öffentlich).
- Erst nach Jungsis Freigabe mergen und pushen. Keine force-Pushes. Beim Merge ergänzt sich `main` per `--ff-only`.
- Beobachtungen von Jungsi haben Vorrang vor Annahmen: erst prüfen, was live tatsächlich passiert, dann den Ansatz anpassen.
