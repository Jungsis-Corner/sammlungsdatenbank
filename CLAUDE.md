# Sammlungsdatenbank (Jungsis Corner)

Eigene PHP/MySQL-Webanwendung zur Verwaltung einer Retro-Computing-Sammlung (1.300+ Objekte: Rechner, Konsolen, Spiele, Zubehör, Literatur). Es gibt eine private Admin-Ansicht und eine öffentliche Museums-/Gastansicht. Open Source unter MIT-Lizenz.

## Stack und Umgebung

- PHP + MySQL, kein Framework, Hosting bei Strato
- Benutzeroberfläche durchgehend **Deutsch** (Texte, Labels, Fehlermeldungen)
- Hauptumgebung: Windows mit Edge (Chromium). Auf dem iPad läuft Edge mit WebKit-Engine. Nicht pauschal WebKit-Bugs annehmen, erst am beobachteten Verhalten prüfen.
- Externe APIs:
  - DeepL (Übersetzung), Key kommt aus `config.php`
  - Twitch/IGDB (Spiele-Metadaten), Zugangsdaten über die Konstanten `TWITCH_CLIENT_ID_CONST` und `TWITCH_CLIENT_SECRET_CONST` aus `config.php`. IGDB wird in `edit.php` (Button "IGDB holen", POST mit `action=igdb` und CSRF-Token) genutzt, um anhand der Bezeichnung und der aus der Kategorie abgeleiteten Plattformen eine Spielbeschreibung (summary) abzurufen, die per DeepL übersetzt wird. Status und Diagnose erscheinen einmalig als Session-Meldung, nicht in der URL.
- Die App ist eine PWA mit Service Worker (`sw.js`)

## Wichtige Dateien und Einstiegspunkte

- Einstieg Museum: `museum/start.php` (der ältere Kurzlink `museum/index.php` leitet nur dorthin weiter)
- `boot.php`: zentrale Session-Konfiguration und Wartungsmodus (Datei `maintenance.flag` im App-Ordner: alle Besucher und Gäste bekommen 503, angemeldete Admins kommen durch; `login.php` und `logout.php` bleiben erreichbar). Zum Prüfen als Besucher ein privates Fenster nehmen, Admins sehen den 503 nie.
- `inc_format.php`: Geldbeträge. `parse_decimal()` liest Komma oder Punkt (auch „1.234,50“, „12 €“) und liefert `"1234.50"` oder `null`; `fmt_dec()` gibt „1.234,50“ aus (Formularfelder, CSV), `fmt_eur()` gibt „1.234,50 €“ aus. Für alle Geldfelder (`Sammlung.Wert`, `Sammlung.Einkaufspreis`, `Einkauf.Preis`, alle `DECIMAL(10,2)`) verwenden, nie selbst mit `str_replace` oder `(float)` umrechnen.
- `inc_image.php`: Bildschicht, die einzige Stelle, die aus `Sammlung.SammlungBild1` eine URL und ein `<img>` baut: `image_url()`, `image_exists()`, `image_img()` (mit `alt`, `loading`, `decoding`, Breite/Höhe, Platzhalter), `image_parse()`. Liest alte Werte (volle WordPress-URL) und neue (relativer Pfad, so liegt es seit B2 in der Datenbank); `image_normalize_for_storage()` kürzt beim Speichern in `edit.php` eingefügte WordPress-URLs. `bildpruefung.php` (Admin, nur lesend) zeigt GD-Status, fehlende Dateien, Originale über 2 MB und verwaiste Dateien in `medien/`. Neue Bildausgaben immer darüber, nie `<img src="<?= $row['SammlungBild1'] ?>">`. Konstanten `IMAGE_*` mit Standardwerten, Vorlage in `config.example.php`.
- `thumb.php?f=<Bildname>&w=<Breite>`: liefert verkleinerte Bilder (nur angemeldet, Gäste eingeschlossen; Breite nur aus `IMAGE_THUMB_WIDTHS`; nutzt zuerst WordPress-Zwischengrößen, sonst GD; Cache in `cache/thumbs/`, darf jederzeit gelöscht werden; bei jedem Problem kommt das Original). `medien/` (lokale Bilder, später) und `cache/` haben eigene `.htaccess` ohne `php_flag`/`php_value` (können bei CGI/FPM einen 500er auslösen). Der Service Worker cached Miniaturen in einem eigenen Bild-Cache (höchstens 200 Einträge).
- `api_lookup.php?t=<Liste>&q=<Text>` und `assets/typeahead.js`: Suchfelder mit Vorschlagsliste statt Auswahllisten mit tausend Einträgen (Hersteller, Publisher, Verkäufer, Kategorie, „Gehört zu“, Filter in der Liste, Zielauswahl beim Zusammenführen). Die API liefert höchstens 20 Treffer als JSON, nur lesend (GET), nur angemeldet; Tabellen nur aus fester Zuordnung, Gäste bekommen nichts, was ihnen die Seiten ausblenden. Neues Feld mit vielen Werten = `<input data-ta="<Schlüssel>" data-ta-hidden="<ID-Feld>">` und ein Eintrag in der Zuordnung von `api_lookup.php`, kein `<select>`/`<datalist>` mit allen Zeilen.
- `config.php`: Zugangsdaten, API-Keys, Konstanten wie `APP_TITLE` und `APP_DEBUG`. **Nie lesen, ändern oder committen.** Vorlage ist `config.example.php`.
- `inc_errors.php`: zentrale Fehlerbehandlung (siehe Sicherheit). Als **erstes** in jeder Seite einbinden.
- `inc_security.php`: `csrf_token()`, `csrf_field()`, `require_post_csrf()`, `safe_back_url()`, `lookup_ident()` (Whitelist für Lookup-Tabellen und -Spalten), `lookup_refs()` (Verweise je Lookup, für Verwendungsanzahl und Zusammenführen)
- `inc_filter.php`: `sammlung_filter_where()` baut den WHERE-Teil der Sammlungsliste aus den 11 Filtern (Kategorie [`filter=spiel*` über `Kategorie.Ebene = 'spiele'`, `filter=<ID>` exakt, `kat=<ID>` mit `inkl=1` samt Unterkategorien über `Kategorie.Pfad`], Original/Homebrew, Material, Suche, Hersteller, Publisher, Verkäufer, Standort, Box, Zum Verkauf, Einkaufsdatum) mit `?`-Platzhaltern. `db_select()` führt die Abfrage als Prepared Statement aus. Wird von `index.php`, `view.php`, `edit.php`, `export.php` und `delete.php` genutzt. Die Suche `q` durchsucht Bezeichnung, Barcode und Beschreibung. Weitere Helfer dort: `page_size()` (Seitengröße der Liste 25/50/100 aus `?pp=` oder dem Cookie `pp`, Standard 25; `index.php` und `delete.php` müssen dieselbe benutzen), `kategorie_baum()` (Kategorie-Baum in Anzeigereihenfolge, für Dropdowns) und `box_sort_sql()` (natürliche Sortierung der Boxen: Box 2 vor Box 10).
- `inc_manage_lookup.php`: gemeinsame Logik und HTML für alle `manage_*.php` (Stammdaten wie Material, Standort, Hersteller). Die `manage_*.php` sind nur noch dünne Dateien, die ein Array `$cfg` setzen (Tabelle, Spalte, Beschriftungen, optional `post` für abweichende Formularfeld-Namen, `keep_page`, `merge`, `order`). Besonderheiten: `Verkäufer` (Umlaut in Tabelle und Spalte, Formularfeld `verkaeufer`) und `Datentraeger` (Tabelle mit `ae`, Spalte `Datentrager` ohne `ä`). Neue Stammdaten-Seite = neue `manage_*.php` mit `$cfg`, keine Kopie der Logik. Ausnahme: `manage_kategorie.php` ist eine eigene Seite (Baum mit `ParentID`, `Ebene`, `Pfad`; Pfad des Teilbaums wird bei jeder Änderung neu berechnet; kein Zusammenführen, weil Kategorie-IDs in Links stehen). `kategorie_baum()` in `inc_filter.php` liefert den Baum für Dropdowns.
- `.gitattributes`: Zeilenenden auf LF vereinheitlicht (`* text=auto eol=lf`)
- `schema.sql`: Datenbankstruktur (ohne Daten und ohne `bak_*`-Sicherungstabellen), bildet den Stand nach allen Migrationen ab und trägt sie am Ende als eingespielt in `schema_migrations` ein
- `migrations/`: nummerierte Migrationen und `migrations/README.md` (Ablauf, Sicherungstabellen, Wartungsmodus)
- `assets/base.css`: Design-Fundament (Phase 5a), **auf jeder Seite als Letztes** eingebunden (nach `app.css`/`admin.css` und nach dem Inline-`<style>`). Enthält die Design-Tokens (`--bg`, `--text`, `--primary`, `--danger`, `--neutral`, `--s1`…`--s6`, `--r-sm`, `--tap` …, hell und unter `html[data-theme="dark"]`), vereinheitlicht alle Knopfklassen (eine Hauptfarbe `--primary` für die Hauptaktion, neutrale Zweitknöpfe, Rot nur für Löschen; die alten Klassen wie `btn-blue`, `save`, `nav` bleiben gültig und werden hier umgefärbt), `:focus-visible`, Touch-Flächen (44 px bei `pointer:coarse`), `.skip-link`, `.sr-only`, `.ico`. Neue Farben immer als Token, keine festen Hex-Werte in neuen Regeln.
- `inc_icons.php`: `icon('name')` gibt ein Inline-SVG aus dem Sprite aus (`icon('trash','Löschen')` für Icon ohne Text, mit unsichtbarem Text für Screenreader); das Sprite wird beim ersten Aufruf einmal geschrieben. Neue Icons in `ICON_PATHS` ergänzen. Kein Emoji in Knöpfen und Menüs, kein Icon-Font.
- Seitengerüst: jede Seite hat `<a class="skip-link" href="#inhalt">` und `<main id="inhalt">` und einen sprechenden `<title>` („… – Sammlung“).
- `manifest.php`: dynamisches PWA-Manifest
- `assets/`: CSS und JS (`app.css`, `theme-toggle.js`)

## Regeln: Cache-Busting (sonst greifen Änderungen nicht)

- Bei Änderungen an `app.css`: `?v=N` in **allen** Dateien hochzählen, die sie einbinden.
- Bei Änderungen an `theme-toggle.js`: `?v=N` an **allen** Seiten hochzählen, die das Skript einbinden (u. a. `edit.php`, `view.php`, `index.php`, `einkauf.php`, `einkauf_edit.php`, `statistik.php`, `einstellungen.php`, `inc_head_admin.php`). **Zusätzlich** `CACHE_NAME` in `sw.js` erhöhen. Beides immer zusammen.
- Der Service Worker cached Assets cache-first. Eine unversionierte URL wird nie neu geladen.
- `sw.js` wird mit `updateViaCache: "none"` registriert (`inc_pwa_footer.php`), weil Strato es sonst mit 30 Tagen HTTP-Cache ausliefert. `assets/.htaccess` ergänzt die Kompression für `text/javascript` (die Liste in der `.htaccess` im App-Ordner kennt nur `application/javascript`). Nach Header-Änderungen immer per `curl -sI -H "Accept-Encoding: gzip" <URL>` prüfen (`content-encoding`, `cache-control`).
- Bei Änderungen an `base.css`: `?v=N` in **allen** Seiten hochzählen, die sie einbinden (alle Seiten außer reinen Skripten, `inc_head_admin.php` eingeschlossen). `theme-toggle.js` steht auf `?v=5`.
- **Massenersetzungen** (`sed -i`, Skripte) nie auf `*.php` oder andere Platzhalter anwenden, sondern nur auf Dateien, die das Suchmuster wirklich enthalten (`grep -l <Muster> | xargs sed -i ...`) oder die einzeln benannt sind. `sed -i` schreibt jede Datei neu und setzt den Zeitstempel, auch ohne Treffer; das berührt `config.php`, `wp-config.php` und andere lokale Dateien und lässt den FileZilla-Vergleich fast alles als geändert markieren. Vorher mit `grep -l` prüfen, welche Dateien betroffen sind.
- Vor Abschluss einer solchen Änderung per Suche prüfen, dass keine Einbindung ohne `?v=` übrig ist.

## Regeln: Frontend

- Dark Mode: explizite Farb-Overrides pro Element. **Kein** `filter: invert(...)`, das ist bei Tabellen unzuverlässig.
- Filter-Zustand (Schnellfilter, Suche) muss nach dem Speichern in `edit.php` erhalten bleiben.
- Auf dem iPad im Standalone-PWA-Modus gab es das Problem des verschluckten ersten Taps (Suchleiste `einkauf.php`). Die Korrektur steckt als `touchstart`-Listener in `theme-toggle.js`. Nicht entfernen.

## Regeln: Fachlogik

- Verkaufte Dubletten werden **gelöscht**, nicht mit Status geführt.
- "Zum Verkauf" ist eine einfache Checkbox, kein mehrstufiger Prozess.
- Zustand ist aufgeteilt in **Erhaltungszustand** und **Vollständigkeit**.
- Verpackung, Anleitung und Datenträger nutzen die Dropdowns Original / Nachdruck / nicht vorhanden.
- "Gehört zu" ist eine selbstreferenzierende Verknüpfung (Autocomplete, Seriennummer zur Unterscheidung).
- **Kategorien** sind ein Baum (Marke → Plattform → Spiele / Anwendungen / Sonstiges) mit `ParentID`, `Ebene` und `Pfad` (z. B. `/288/53/`). „Spiel“ heißt `Ebene = 'spiele'`, nie `LIKE '%Spiel%'`. Kategorie-IDs stehen in Links (`go.php`, Blog) und dürfen sich nie ändern, deshalb gibt es für Kategorien kein Zusammenführen. Änderungen am Baum laufen über `manage_kategorie.php`, das den Pfad des Teilbaums neu berechnet. Die alte Spalte `Kategorie.Spiele` wird dort synchron gehalten und erst in D12 entfernt.
- **Box** ist eine Lookup-Tabelle (Namen „Box 1“, „Box 2“, „Regal“); `Sammlung.Box` speichert die ID, Liste/Detail/Export zeigen den Namen per JOIN, der Filter vergleicht die ID.
- **Leere Auswahlfelder** speichern `NULL`, nie `0` (Fremdschlüssel). **Geld** siehe `inc_format.php`.
- Bei neuen oder geänderten Feldern immer mitziehen: `edit.php`, `view.php`, `duplicate.php` (Feldliste), Listen/Filter, `export.php`, `schema.sql`.

## Regeln: Datenbank

- Schemaänderungen als nummerierte SQL-Datei in `migrations/` ablegen (`JJJJ-MM-TT_NN_beschreibung.sql`) und `schema.sql` entsprechend aktualisieren. Jede Migration ist **wiederholbar**, trägt sich in `schema_migrations` ein und hat eine auskommentierte `DOWN`-Sektion und eine Kontrolle (siehe `migrations/README.md`). Datenändernde Migrationen sichern vorher in `bak_*`-Tabellen und setzen bei `UPDATE` auf `Sammlung` immer `Geaendert_am = Geaendert_am`.
- **Reihenfolge Code/Migration:** Rein additive Migration (neue Spalten) zuerst, dann der Code (D9). Kompatibler Code zuerst, dann die Migration (D5, D7). Nur wenn alter und neuer Code mit der Datenbank unverträglich sind (D6, Geld), mit **Wartungsmodus**.
- Integrität: Lookup-Namen sind `UNIQUE` (ohne Unterscheidung von Groß- und Kleinschreibung); `Sammlung` verweist per Fremdschlüssel auf alle Lookups (`ON DELETE RESTRICT`), `VerbautIn` auf `Sammlung.ID` (`ON DELETE SET NULL`). Fehler 1451 (Löschen: noch verwendet) und 1452 (ungültige Auswahl) werden abgefangen und als Meldung gezeigt, nicht als Datenbankfehler. Alle Tabellen haben die Collation `utf8mb4_general_ci`. Mindestens PHP 8.1 (mysqli meldet Fehler als Ausnahmen).
- Migrationen **nicht selbst gegen die Live-Datenbank ausführen.** Jungsi spielt sie manuell auf Strato ein.
- Immer Prepared Statements verwenden. Keine SQL-Strings aus Benutzereingaben zusammenbauen, auch nicht mit `real_escape_string`.
- Filter der Sammlungsliste **nur** über `sammlung_filter_where()` aus `inc_filter.php` bauen, nie eine eigene Kopie in einer Seite. Neuer oder geänderter Filter = Änderung an dieser einen Stelle, danach alle fünf Seiten (`index`, `view`, `edit`, `export`, `delete`) prüfen und den Parameter in deren Link-/Rücksprung-Listen ergänzen.
- Tabellen- und Spaltennamen in SQL nie aus Variablen übernehmen, die von außen kommen. Lookup-Tabellen laufen über `lookup_ident()`; neue Lookup-Tabelle = Eintrag in dessen Whitelist.

## Sicherheit

- Keine Zugangsdaten, API-Keys oder Passwörter im Code oder in Commits. Alles läuft über `config.php`.
- Keine Debug- oder Testskripte (`phpinfo.php` o. ä.) anlegen, die auf dem Server landen könnten. Falls für lokale Tests nötig: in `.gitignore` eintragen.
- Keine ungeschützten Endpunkte. Neue Seiten laufen über `boot.php` und die bestehende Login-/Session-Prüfung.
- Neue Ausgaben von Benutzerdaten mit `htmlspecialchars` escapen.
- Aktionen, die Daten ändern (Löschen, Duplizieren, Importieren), laufen **nur per POST mit CSRF-Token** (`require_post_csrf()`, `csrf_field()` aus `inc_security.php`), nie per GET-Link. Nach der Aktion Redirect mit den Filterparametern (Filter-Zustand bleibt erhalten).
- Rücksprung-Ziele (`back`) immer mit `safe_back_url()` prüfen, nie ungeprüft in `href` oder `Location:` ausgeben.
- Fehler: `inc_errors.php` einbinden, **kein** `ini_set('display_errors', 1)` und keine Klartext-Ausgabe von `$conn->error`, `getMessage()` oder API-Antworten. Datenbankfehler über `fail_db($public, $detail)`, Details ins Log (`error_log`). Das Log ist über `logview.php` (nur Admin) lesbar. Lokal zum Debuggen `define('APP_DEBUG', true);` in `config.php`, auf dem Server aus.
- Keine Meldungstexte oder Diagnosedaten in der URL (`?msg=`). Einmalige Meldungen über die Session (Flash).

## Deployment

- Jungsi lädt per **FileZilla** (Verzeichnisvergleich) auf Strato hoch. Claude Code lädt nichts hoch und hat keinen Serverzugang.
- Nach der Arbeit am Ende kurz auflisten, welche Dateien geändert wurden und hochgeladen werden müssen (inklusive Migration, falls vorhanden).
- Repo-Stand und Serverstand müssen übereinstimmen. Nach jedem Upload committen.
- **Beim Upload ausschließen** (liegen lokal im Projektordner, sind in `.gitignore`, gehören nie auf den Server): `config.php` (nur bewusst, sie ist serverspezifisch), `phpinfo.php`, `wp-config.php`, `debug_session.php`, `token_test.php`, `logtest.php`, `mini-finder.php`, `php-error.log`, `readme.txt`, `maintenance.flag` (nur zum Wartungsmodus bewusst anlegen), `var/` und `docs/bestand/`.
- Ablauf bei Migrationen: Dump per phpMyAdmin, Migration einspielen, Kontrolle prüfen, Code hochladen, Stichprobe. Reihenfolge je nach Migration siehe Regeln Datenbank. Wartungsmodus nur, wenn dort verlangt.

## Git-Ablauf

- Es wird auf zwei Rechnern gearbeitet. Vor Arbeitsbeginn `git pull`, am Ende `commit` und `push`.
- Vor dem Löschen oder Neu-Klonen eines Ordners immer `git status` prüfen.
- `docs/Überarbeitungsplan*.md` ist nicht öffentlich (lokal, per `.gitignore` ausgeschlossen), ebenso `docs/bestand/` (Sammlungsdaten). Nie committen oder pushen.
- Pro Aufgabe ein Branch, kleine thematische Commits mit verständlicher deutscher Commit-Message.
- Keine force-Pushes, kein `git reset --hard` ohne ausdrückliche Rückfrage.
- Commits laufen mit der GitHub-noreply-Adresse (`git config user.email`), sonst lehnt GitHub den Push ab (GH007, E-Mail-Privatsphäre). Auf beiden Rechnern setzen.

## Arbeitsweise

- In kleinen Schritten vorgehen und bei größeren Änderungen (Schema, mehrere Seiten) zuerst einen Plan zeigen.
- Nach jeder PHP-Änderung `php -l <datei>` ausführen.
- Jungsi testet im Browser und liefert Screenshots oder DevTools-Ausgaben. Bei Widerspruch zwischen Annahme und Beobachtung gilt die Beobachtung. Ansatz dann ändern, nicht verteidigen.
- Annahmen kurz benennen. Bei unklarem Befund erst nachfragen, statt zu raten.
- Tests lokal gegen eine **Kopie** der App mit eigener Test-`config.php` (Datenbank `127.0.0.1`, kein echter Zugang) und eine Wegwerf-Datenbank aus einem Dump. Dateien nur einzeln benannt in die Testkopie kopieren, nie pauschal `*.php` (sonst landet die echte `config.php` in der Kopie).
