# Übergabe: Stand der Überarbeitung (Rechnerwechsel)

Stand: 13.10.2026, Ende der Sitzung auf dem ersten Rechner. Dieses Dokument ist für eine neue Claude-Code-Sitzung
auf dem zweiten Rechner gedacht. Es enthält **keine** Zugangsdaten, keine Namen von Personen und keine Serveradressen.

## 1. Einstieg auf dem anderen Rechner

Zuerst die Vorbereitung (Abschnitt 6), dann in Claude Code:

> Lies CLAUDE.md, docs/Überarbeitungsplan Sammlungsdatenbank.md und docs/uebergabe.md. Wir machen mit Aufgabe D3
> weiter (Branch d3-bereinigung). Zeige mir vor jeder Datenbank- oder Mehrdatei-Änderung zuerst den Plan.

## 2. Wo wir stehen

| Phase | Stand |
| --- | --- |
| 1 Sicherheit | S1 bis S8 sind umgesetzt, **gemergt auf `main` und auf dem Server**. S9 (CSP ohne `unsafe-inline`) ist offen und kommt laut Plan erst nach Phase 5 |
| 2 Datenbank | **D1** (Migrationsordner, `schema_migrations`, Wartungsmodus) und **D2** (Bestandsaufnahme) sind gemergt, D1 ist auf dem Server eingespielt. **D3** (Bereinigung) ist **fertig und getestet, aber weder gemergt noch auf dem Server** |
| 3 bis 7 | nicht begonnen |

Server-Stand (von Jungsi bestätigt, 13.10.2026):

- Alle Dateien von S1 bis S8 sind hochgeladen, auch beide Uploads von S8 (21 Dateien: u. a. `inc_security.php`, `export.php`, `duplicate.php`, `view.php`, `museum/index.php`, `.htaccess`). `main` bis `dda1a08` entspricht dem Server, abgesehen von `docs/` und `migrations/`, die nie hochgeladen werden.
- In der Live-Datenbank eingespielt: `0000_login_log`, `2026-10-13_01_login_versuche`, `2026-10-13_02_schema_migrations`.
- **Noch nicht** auf dem Server: alles aus `d3-bereinigung` (Migration `2026-10-13_03_bereinigung.sql`, `edit.php` und `index.php` mit den D3-Änderungen). Die D3-Fassungen von `edit.php` und `index.php` enthalten alle S8-Änderungen und ersetzen die Server-Dateien sauber.

Git-Stand:

- `main` = `origin/main` = `dda1a08` (Stand nach D2). Tags `vor-phase-1` und `vor-phase-2` sind auf GitHub.
- **`d3-vorbereitung`** (`3d3877e`): bringt `schema.sql` auf den Live-Stand (sechs Altspalten ergänzt). Baut auf `main` auf.
- **`d3-bereinigung`** (aktueller Branch, baut auf `d3-vorbereitung` auf): Migration `migrations/2026-10-13_03_bereinigung.sql`,
  `edit.php` (schreibt NULL statt 0), `index.php` (NULL-sichere Ausgabe), `schema.sql`, `migrations/README.md` und dieses Dokument.
- Alle anderen lokalen Branches (`s1-…` bis `s8-…`, `d1-…`, `d2-…`, `museum-exit`) sind schon in `main` gemergt und nur lokal. Sie können gelöscht werden.

## 3. Nächste Schritte für D3

1. **Frischen Dump** der Live-Datenbank ziehen (phpMyAdmin: Datenbank wählen, Exportieren, Angepasst, alle Tabellen, SQL, Struktur und Daten)
   und als `docs/bestand/dump.sql` ablegen. Dann `docs/bestand/nas_pfade_backup.csv` aus diesem Dump neu erzeugen
   (`docs/bestand/werkzeuge/nas_backup.php`), und die Migration noch einmal lokal gegen genau diesen Stand laufen lassen.
2. `edit.php` und `index.php` per FileZilla hochladen.
3. Migration `2026-10-13_03_bereinigung.sql` in phpMyAdmin einspielen (kein Wartungsmodus nötig). Die letzte Abfrage zeigt eine
   Kontrolltabelle mit 18 Zeilen, **alle** müssen `OK` zeigen.
4. Stichprobe: Liste, einen Eintrag öffnen und speichern, Filter nach Hersteller. Danach `d3-vorbereitung` und `d3-bereinigung` mergen und pushen.
5. Die Sicherungstabellen `bak_d3_*` bleiben bis zur Abnahme stehen (Aufräumen in D12).

Erwartete Zählung durch die Migration: `Hersteller` 1.193 auf 1.187 Zeilen, `Publisher` 430 auf 429, `Verkäufer` 279 auf 277,
`Box` 10 auf 14. `Sammlung` bleibt bei 3.799, `Einkauf` bei 862. „mit Jahr“ in der Liste sinkt von 3.799 auf 3.783 (Jahr 0 wird NULL).

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

## 6. Vorbereitung auf dem zweiten Rechner

**Vorher auf dem ersten Rechner (diese Dinge sind nicht im Git):**

1. Ordner **`docs/bestand/`** komplett kopieren (USB-Stick oder Netzlaufwerk). Er enthält `dump.sql`, alle CSV-Dateien, `einzelfaelle.csv`, `nas_pfade_backup.csv` und `werkzeuge/` (meine Test- und Hilfsskripte). Nicht in Git, nicht in einen Cloud-Ordner mit Freigabe legen (Sammlungsdaten).
2. Datei **`docs/Überarbeitungsplan Sammlungsdatenbank.md`** kopieren (untracked), Sie ist nicht in Git.
3. **`config.php`** nicht kopieren, nicht committen. Auf dem zweiten Rechner existiert sie bereits (oder aus `config.example.php` anlegen). Sie enthält die Zugangsdaten und muss die Konstanten `LOGIN_HASH_SALT`, `SESSION_COOKIE_DOMAIN` (bei der Live-Installation `.jungsi.de`) und die Werte aus `config.example.php` haben.

**Auf dem zweiten Rechner:**

```
git status                      # muss sauber sein, sonst erst klären
git fetch --all --tags
git checkout main && git pull
git checkout d3-bereinigung     # Branch kommt über origin, er wurde gepusht
git config user.email           # muss die GitHub-noreply-Adresse sein (sonst lehnt GitHub den Push ab, GH007)
```

Die E-Mail ist `225476313+Jungsis-Corner@users.noreply.github.com`. Dann die Ordner `docs/bestand/` und die Plandatei an ihren Platz kopieren.

## 7. Lokale Testumgebung (nur nötig, wenn Claude Migrationen testen soll)

Claude hat auf dem ersten Rechner PHP 8.4 (Winget) und MySQL 8.4 benutzt. Auf dem zweiten Rechner braucht es dasselbe:

- **PHP:** `php -l` für jede geänderte Datei (Pflicht laut CLAUDE.md). Für Tests mit MySQL die Erweiterungen laden: `php -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d mysqli.default_port=3399 …`
- **MySQL 8.4:** Eigene Wegwerf-Instanz mit eigenem Datenordner und eigenem Port, **ohne** die Installation des Rechners zu berühren:
  `mysqld --no-defaults --initialize-insecure --datadir=<Ordner>`, dann
  `mysqld --no-defaults --datadir=<Ordner> --port=3399 --bind-address=127.0.0.1 --mysqlx=OFF`. Client immer mit `--default-character-set=utf8mb4`.
- **Die echte `config.php` nie in Tests laden.** Tests laufen mit einer App-Kopie ohne `config.php` und einer Fake-Konfiguration (Datenbank = lokale Kopie, siehe `werkzeuge/smoke.sh`).
- Lokal fehlen die MySQL-Zeitzonentabellen: In der Test-Kopie `SET time_zone = 'Europe/Berlin'` in `view.php` und `edit.php` durch `'+02:00'` ersetzen (nur in der Kopie).
- Die Skripte in `docs/bestand/werkzeuge/` haben **feste Pfade** des ersten Rechners (Scratchpad-Ordner, `C:\temp\sammlungsdatenbank`). Vor dem Einsatz Pfade anpassen:
  `run_blocks.php` (Blöcke von `bestand.sql` gegen die lokale Kopie, schreibt CSV), `checksum.php` (Prüfsumme je Tabelle, Vorher/Nachher-Vergleich), `smoke.sh` (76 Seiten gegen die App-Kopie), `save_test.sh` (Speichertest für NULL), `einzelfaelle.php`, `nas_backup.php`, `gen_bestand.php` (erzeugt `docs/bestand.sql`), `testdaten.sql`.

Test-Ablauf einer Migration (so wurde D3 geprüft): frische Kopie aus `dump.sql` importieren, Prüfsummen notieren, Migration zweimal
laufen lassen (zweiter Lauf darf nichts ändern), `DOWN` ausführen (Prüfsummen wieder identisch zum Dump), App-Kopie mit `smoke.sh` und `save_test.sh` prüfen.

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
