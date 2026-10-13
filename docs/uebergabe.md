# Übergabe: Stand der Überarbeitung

Stand: 13.10.2026, nach Abschluss von D3 (zweiter Rechner, WSL). Dieses Dokument ist für eine neue Claude-Code-Sitzung gedacht.
Es enthält **keine** Zugangsdaten, keine Namen von Personen und keine Serveradressen.

## 1. Einstieg in eine neue Sitzung

> Lies CLAUDE.md, docs/Überarbeitungsplan Sammlungsdatenbank.md und docs/uebergabe.md. Wir machen mit der nächsten offenen
> Aufgabe aus Abschnitt 2 weiter. Zeige mir vor jeder Datenbank- oder Mehrdatei-Änderung zuerst den Plan.

Vorher `git status` (muss sauber sein, bis auf die untracked Plandatei), `git fetch --all --tags`, `git checkout main && git pull`.

## 2. Wo wir stehen

| Phase | Stand |
| --- | --- |
| 1 Sicherheit | S1 bis S8 sind umgesetzt, gemergt und auf dem Server. S9 (CSP ohne `unsafe-inline`) kommt laut Plan erst nach Phase 5 |
| 2 Datenbank | **D1, D2, D3 sind abgeschlossen**: gemergt auf `main` (`1c28015`), Code hochgeladen, Migration `2026-10-13_03_bereinigung.sql` auf dem Server eingespielt und geprüft. **D4** (Typen anpassen) ist als Nächstes dran |
| 3 bis 7 | nicht begonnen |

Server-Stand (von Jungsi bestätigt, 13.10.2026):

- Alle Dateien bis einschließlich D3 sind hochgeladen. Die beiden letzten Korrekturen (`index.php` Zustand/Verpackung ohne NULL als Array-Schlüssel, `statistik.php` Materialname ohne NULL) liegen auf `main`; ob sie schon hochgeladen sind, bei Jungsi erfragen.
- In der Live-Datenbank eingespielt: `0000_login_log`, `2026-10-13_01_login_versuche`, `2026-10-13_02_schema_migrations`, `2026-10-13_03_bereinigung`.
  Kontrolle nach D3: Hersteller 1.187, Publisher 429, Verkäufer 277, Box 14, Sammlung 3.799, Einkauf 862, `bak_d3_sammlung` 3.799. Stichprobe in der App bestanden.
- Die Sicherungstabellen `bak_d3_*` bleiben bis zur Abnahme stehen (Aufräumen in D12).

Git-Stand:

- `main` = `origin/main` = `1c28015`. Die Tags `vor-phase-1` und `vor-phase-2` sind auf GitHub.
- Die Branches `d3-vorbereitung` und `d3-bereinigung` sind vollständig in `main` enthalten und können gelöscht werden. Ebenso die alten lokalen Branches `s1-…` bis `s8-…`, `d1-…`, `d2-…`, `museum-exit`.
- `docs/Überarbeitungsplan Sammlungsdatenbank.md` liegt **untracked**. Jungsi hat nicht entschieden, ob sie committet wird.

## 3. Offene Punkte

- **Box 10:** Die Zeile heißt „Regal“, in `Sammlung` steht Box 10 bei 6 Einträgen. Bedeutung klären, bevor D4/D7 den Fremdschlüssel setzen.
- **HSTS** frühestens am 20.10.2026 auf ein Jahr erhöhen (siehe Abschnitt 4).
- **D4 bis D12** laut Abschnitt 8.

## 4. Entscheidungen von Jungsi (gelten weiter)

- Gastzugang bleibt (`gast`/`gast`, bewusst öffentlich). Export für Gäste aus. Felder für Gäste wie bisher verborgen.
- HSTS steht auf `max-age=300`. **Frühestens am 20.10.2026** auf ein Jahr (31536000) erhöhen, vorher alle Subdomains prüfen.
- `Box` bleibt **Fremdschlüssel**. Die Zeilen 11, 12, 13 und 99 heißen genau „11“, „12“, „13“, „99“ (in der Migration enthalten). Offen: Die Zeile 10 heißt „Regal“, in `Sammlung` steht Box 10 bei 6 Einträgen. Bedeutung klären.
- Einzelfälle (Kategorie, Material, Standort) sind entschieden und in der Migration eingetragen.
- Neun exakte Dubletten in `Hersteller`, `Publisher`, `Verkäufer`: kleinere ID bleibt, Verweise werden umgestellt (in der Migration).
- 38 kaputte NAS-Bildpfade werden auf `''` gesetzt (Sicherung als CSV unter `docs/bestand/` und in `bak_d3_sammlung`).
- Die sechs Altspalten in `Sammlung` bleiben vorerst. Entfernen erst am Ende (D12), **nachdem Jungsi die Daten exportiert hat**.
- `docs/bestand/` wird **nie** committet (enthält Sammlungsdaten).
- `docs/Überarbeitungsplan Sammlungsdatenbank.md` liegt noch **untracked**. Jungsi hat nicht entschieden, ob sie committet wird.

## 5. Wichtige Erkenntnisse aus der Auswertung

Details: `docs/bestand-auswertung.md`. Das Wichtigste:

- Live-Server: **MySQL 8.0.36, PHP 8.4**, Strato. Die App läuft dort im **nicht-strikten** SQL-Modus (die Checkboxen speichern `''` in Zahlspalten). Lokal in strikter MySQL schlägt das fehl. Beim Test `SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'` setzen. Das ist bei D4 zu beachten.
- `Sammlung.Sonstiges` ist **keine** tote Spalte (Checkbox „Sonstiges dabei“). Der Plan (D12) liegt dort falsch, nicht löschen.
- Die Bilder liegen unter `…/blog/wp-content/uploads/` (nicht `/wp-content/uploads/`), flach ohne Jahr/Monat, 619 Einträge mit Doppelschrägstrich `//blog`. Phase 3: `IMAGE_WP_BASE` entsprechend, gespeichert wird nur der Dateiname.
- `Wert` und `Einkaufspreis` sind sauber (nur leer, ganze Zahl oder Komma-Dezimal). `export.php` rechnet aber mit `REPLACE(Wert, ".", "")`, deshalb darf die Umstellung auf Punkt-Schreibweise erst in D6 zusammen mit dem Code im Wartungsmodus passieren.
- `duplicate.php` bindet `Wert` als Kommazahl (`(float)"20,50"` ergibt 20): Beim Duplizieren gehen die Cent verloren. In D6 beheben.
- `Einkauf` hat die Collation `utf8mb4_0900_ai_ci`, alles andere `utf8mb4_general_ci`. Abfragen, die Textspalten beider Welten mischen, scheitern mit Fehler 1271 (D8).
- Die Spalte `Geaendert_am` hat `ON UPDATE CURRENT_TIMESTAMP`: Jedes `UPDATE` ändert sie. Datenmigrationen müssen `Geaendert_am = Geaendert_am` setzen (so geschehen in D3).
- `schema.sql` entspricht seit `d3-vorbereitung` dem Live-Stand (90 Spalten, 23 Indizes, 2 Fremdschlüssel, geprüft).
- Der phpMyAdmin-Export „Abfrageergebnis“ funktioniert bei den Abfragen aus `docs/bestand.sql` nicht. Statt dessen: ein SQL-Dump der Datenbank, den Claude lokal einspielt (siehe Abschnitt 7).
- Der Dump enthält die Spalte `Geaendert_am` nicht in den `INSERT`-Anweisungen. Jeder Import stempelt sie neu. Vergleiche nur innerhalb eines Imports.

## 6. Zweiter Rechner (WSL): Stand der Einrichtung

Auf dem zweiten Rechner läuft Claude Code in WSL (Ubuntu). Die Ordner `docs/bestand/` (nicht in Git, enthält Sammlungsdaten) und die Plandatei sind dort angelegt.

- Git-E-Mail ist die GitHub-noreply-Adresse (`git config user.email`, sonst lehnt GitHub den Push ab, GH007).
- Die echte `config.php` wird nie geladen oder kopiert.
- Der aktuelle Live-Dump liegt als `docs/bestand/dump.sql` (Stand 13.10.2026, 17:43 Uhr, vor dem Einspielen der D3-Migration). Für D4 einen **neuen** Dump ziehen, er enthält dann die D3-Änderungen.

## 7. Lokale Testumgebung (WSL)

Installiert (apt): PHP 8.5.4 (CLI, mit `mysqli`, `mbstring`), MySQL 8.4.11 (`/usr/sbin/mysqld`, Dienst nicht gestartet). Auf dem Server läuft PHP 8.4, MySQL 8.0.36.
`sudo` braucht ein Passwort, Pakete installiert Jungsi selbst (`! sudo apt install …`).

- **MySQL-Wegwerf-Instanz** im Scratchpad, eigener Datenordner, Port 3399, Socket und PID im Datenordner:
  `mysqld --no-defaults --initialize-insecure --datadir=<ordner>/data`, dann
  `mysqld --no-defaults --datadir=<ordner>/data --port=3399 --bind-address=127.0.0.1 --mysqlx=OFF --socket=<ordner>/sock --mysqlx-socket=<ordner>/xsock --pid-file=<ordner>/pid --log-error=<ordner>/err.log`.
  Client: `mysql -uroot -h127.0.0.1 -P3399 --default-character-set=utf8mb4`. Danach `SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'` (die Live-DB läuft nicht-strikt).
- **App-Kopie ohne echte `config.php`:** `git archive HEAD | tar -x -C <ordner>/www/sammlung`, eigene Test-`config.php` (DB `127.0.0.1`, root ohne Passwort, `ADMIN_PASSWORD_HASH` für ein Testpasswort, `SESSION_COOKIE_SECURE` false, `LOGIN_HASH_SALT`, `ERROR_LOG_PATH` und **auch `APP_TITLE`, `APP_TITLE_MUSEUM`, `APP_PWA_NAME`, `APP_PWA_SHORT_NAME`**, sonst gibt es 500er).
  Server: `php -d mysqli.default_port=3399 -S 127.0.0.1:8124` im Ordner `www`. In der Kopie `SET time_zone = 'Europe/Berlin'` durch `'+02:00'` ersetzen (nur Kopie; lokal fehlen die Zeitzonentabellen).
- **Werkzeuge** in `docs/bestand/werkzeuge/` haben feste Windows-Pfade des ersten Rechners (`C:/temp/sammlungsdatenbank`, `/c/Users/…`). Vor Gebrauch in einer Kopie anpassen: `smoke.sh` (76 Seiten, Admin `admin`, Fehlerlog-Pfad), `save_test.sh` (Speichertest NULL, nutzt den `mysql`-Client), `nas_backup.php` (liest Datenbank `live`), `run_blocks.php`, `einzelfaelle.php`, `gen_bestand.php`, `sync_schema.php`, `keep_geaendert.php`. `checksum.php` läuft unverändert (Prüfsumme je Tabelle, nutzt `127.0.0.1:3399`).
  In `smoke.sh` ist `view.php?id=3` ein erwarteter 404 (ID gibt es nicht).
- **Ablauf einer Migrationsprüfung:** frische Datenbank aus `dump.sql`, Prüfsummen notieren (`checksum.php`), Migration zweimal laufen lassen (zweiter Lauf ändert nichts), `DOWN` ausführen (Prüfsummen wieder gleich), App-Kopie mit `smoke.sh` und `save_test.sh` prüfen. Das Log des PHP-Servers (Deprecations) mitlesen, PHP 8.5 meldet strenger als 8.4.
- Nach jedem Test den Dump nicht überschreiben, sondern in einer eigenen Datenbank (`d3test`, `d3t`, `live`) arbeiten.

## 8. Weiterer Fahrplan (Phase 2, laut Plan, mit Anpassungen)

- **D4** Typen anpassen (Fremdschlüsselspalten auf `INT`, `Jahr` `SMALLINT`, `Anzahl` `SMALLINT UNSIGNED NOT NULL DEFAULT 1`, `Zum Verkauf` `TINYINT(1)`, Collation vereinheitlichen). Berücksichtigen: nicht-strikter Modus der Live-Datenbank, Checkbox-Felder, `Box` ist Fremdschlüssel.
- **D5** Lookups: `UNIQUE` auf dem Namen (nach den Dubletten aus D3 möglich) und die Funktion „Zusammenführen“ in `inc_manage_lookup.php`.
- **D6** Geld als `DECIMAL(10,2)` mit `inc_format.php` (`parse_decimal()`, `fmt_eur()`), im **Wartungsmodus** (`maintenance.flag`), inklusive `export.php`, `statistik.php`, `einkauf_edit.php`, `duplicate.php`.
- **D7** Fremdschlüssel und Indizes, Fehler 1451 in `inc_manage_lookup.php` abfangen („noch in Verwendung“).
- **D8** `Einkauf` bleibt unverknüpft, nur Collation angleichen und die Übernahme-Funktion in `edit.php` testen.
- **D9** Kategorie-Hierarchie über CSV-Freigabe (`docs/kategorie_mapping_vorschlag.csv`), Grundlage `docs/bestand/06_kategorien.csv` (173 Kategorien).
- **D10 bis D12** Statistik, Dokumentation (`CLAUDE.md`, `README.md`, `schema.sql`), Altlasten (Altspalten erst nach Export, `bak_d3_*` aufräumen).
- Danach Phase 3 (Bilder), 4 (Performance), 5 (UI), 6 (Barrierefreiheit), 7 (Tests), siehe Plan.

## 9. Arbeitsregeln (aus CLAUDE.md und aus dieser Sitzung)

- Deutsch in allem, was Jungsi sieht. Kleine Schritte, bei Schema- oder Mehrdatei-Änderungen **zuerst den Plan zeigen**.
- Pro Aufgabe ein Branch, kleine thematische Commits mit deutscher Message. Commit-Trailer: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Nach jeder PHP-Änderung `php -l`. Migrationen **nicht** selbst gegen die Live-Datenbank ausführen: Jungsi spielt sie in phpMyAdmin ein (vorher Dump).
- Am Ende jeder Aufgabe die Dateiliste für FileZilla nennen (inklusive Migration). `.htaccess` immer zuletzt hochladen, vorher sichern.
- Nie lesen, ändern oder committen: `config.php`. Keine Zugangsdaten, keine Namen von Personen oder Serveradressen in committeten Dateien (das Repo ist öffentlich).
- Erst nach Jungsis Freigabe mergen und pushen. Keine force-Pushes.
- Beim Merge ergänzt sich `main` per `--ff-only` (bei zwei parallelen Branches ein normaler Merge).
- Beobachtungen von Jungsi haben Vorrang vor Annahmen: erst prüfen, was live tatsächlich passiert (z. B. mit `curl -sI`), dann den Ansatz anpassen.
