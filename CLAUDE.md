# Sammlungsdatenbank (Jungsis Corner)

Eigene PHP/MySQL-Webanwendung zur Verwaltung einer Retro-Computing-Sammlung (1.300+ Objekte: Rechner, Konsolen, Spiele, Zubehör, Literatur). Es gibt eine private Admin-Ansicht und eine öffentliche Museums-/Gastansicht. Open Source unter MIT-Lizenz.

## Stack und Umgebung

- PHP + MySQL, kein Framework, Hosting bei Strato
- Benutzeroberfläche durchgehend **Deutsch** (Texte, Labels, Fehlermeldungen)
- Hauptumgebung: Windows mit Edge (Chromium). Auf dem iPad läuft Edge mit WebKit-Engine. Nicht pauschal WebKit-Bugs annehmen, erst am beobachteten Verhalten prüfen.
- Externe APIs:
  - DeepL (Übersetzung), Key kommt aus `config.php`
  - Twitch/IGDB (Spiele-Metadaten), Zugangsdaten über die Konstanten `TWITCH_CLIENT_ID_CONST` und `TWITCH_CLIENT_SECRET_CONST` aus `config.php`. IGDB wird in `edit.php` (Aufruf `edit.php?...&igdb=1`) genutzt, um anhand der Bezeichnung und der aus der Kategorie abgeleiteten Plattformen eine Spielbeschreibung (summary) abzurufen, die per DeepL übersetzt wird.
- Die App ist eine PWA mit Service Worker (`sw.js`)

## Wichtige Dateien und Einstiegspunkte

- Einstieg Museum: `start.php` (nicht `index.php`)
- `boot.php`: zentrale Session-Konfiguration
- `config.php`: Zugangsdaten, API-Keys, Konstanten wie `APP_TITLE`. **Nie lesen, ändern oder committen.** Vorlage ist `config.example.php`.
- `schema.sql`: Datenbankstruktur (ohne Daten)
- `manifest.php`: dynamisches PWA-Manifest
- `assets/`: CSS und JS (`app.css`, `theme-toggle.js`)

## Regeln: Cache-Busting (sonst greifen Änderungen nicht)

- Bei Änderungen an `app.css`: `?v=N` in **allen** Dateien hochzählen, die sie einbinden.
- Bei Änderungen an `theme-toggle.js`: `?v=N` an **allen** Seiten hochzählen, die das Skript einbinden (u. a. `edit.php`, `view.php`, `index.php`, `einkauf.php`, `einkauf_edit.php`, `statistik.php`, `einstellungen.php`, `inc_head_admin.php`). **Zusätzlich** `CACHE_NAME` in `sw.js` erhöhen. Beides immer zusammen.
- Der Service Worker cached Assets cache-first. Eine unversionierte URL wird nie neu geladen.
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
- Bei neuen oder geänderten Feldern immer mitziehen: `edit.php`, `view.php`, `duplicate.php` (Feldliste), Listen/Filter, `schema.sql`.

## Regeln: Datenbank

- Schemaänderungen als nummerierte SQL-Datei in `migrations/` ablegen, z. B. `migrations/2026-10-12_beschreibung.sql`, und `schema.sql` entsprechend aktualisieren.
- Migrationen **nicht selbst gegen die Live-Datenbank ausführen.** Jungsi spielt sie manuell auf Strato ein.
- Immer Prepared Statements verwenden. Keine SQL-Strings aus Benutzereingaben zusammenbauen.

## Sicherheit

- Keine Zugangsdaten, API-Keys oder Passwörter im Code oder in Commits. Alles läuft über `config.php`.
- Keine Debug- oder Testskripte (`phpinfo.php` o. ä.) anlegen, die auf dem Server landen könnten. Falls für lokale Tests nötig: in `.gitignore` eintragen.
- Keine ungeschützten Endpunkte. Neue Seiten laufen über `boot.php` und die bestehende Login-/Session-Prüfung.
- Neue Ausgaben von Benutzerdaten mit `htmlspecialchars` escapen.

## Deployment

- Jungsi lädt per **FileZilla** (Verzeichnisvergleich) auf Strato hoch. Claude Code lädt nichts hoch und hat keinen Serverzugang.
- Nach der Arbeit am Ende kurz auflisten, welche Dateien geändert wurden und hochgeladen werden müssen (inklusive Migration, falls vorhanden).
- Repo-Stand und Serverstand müssen übereinstimmen. Nach jedem Upload committen.

## Git-Ablauf

- Es wird auf zwei Rechnern gearbeitet. Vor Arbeitsbeginn `git pull`, am Ende `commit` und `push`.
- Vor dem Löschen oder Neu-Klonen eines Ordners immer `git status` prüfen.
- Pro Aufgabe ein Branch, kleine thematische Commits mit verständlicher deutscher Commit-Message.
- Keine force-Pushes, kein `git reset --hard` ohne ausdrückliche Rückfrage.

## Arbeitsweise

- In kleinen Schritten vorgehen und bei größeren Änderungen (Schema, mehrere Seiten) zuerst einen Plan zeigen.
- Nach jeder PHP-Änderung `php -l <datei>` ausführen.
- Jungsi testet im Browser und liefert Screenshots oder DevTools-Ausgaben. Bei Widerspruch zwischen Annahme und Beobachtung gilt die Beobachtung. Ansatz dann ändern, nicht verteidigen.
- Annahmen kurz benennen. Bei unklarem Befund erst nachfragen, statt zu raten.
