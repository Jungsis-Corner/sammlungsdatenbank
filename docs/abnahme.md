# Abnahme (Phase 7, Teil T5/T6 und Phase 6 A9)

Manuelle Prüfung durch Jungsi. Was ein Skript prüfen kann, steht in `tests/README.md` und ist bereits grün (Smoke-Test, Filter-Gleichheit, Datenbankprüfung, Header live). Hier steht nur, was ein Mensch mit dem Gerät in der Hand prüfen muss. Kästchen abhaken, Auffälligkeiten mit Gerät und Seite notieren.

## 0. Vorab (einmal, am Server)

- [ ] `tests/db_checks.sql` in phpMyAdmin (Registerkarte SQL) ausführen: keine Zeile mit `FEHLER`. Die `Info`-Zahlen notieren (Einträge, Summe Wert, Summe Einkaufspreis).
- [ ] Browser: App mit Strg+F5 neu laden, damit keine alten Dateien aus dem Cache stören (PWA: einmal schließen und neu öffnen).

## 1. Windows-Desktop, Edge (aktuell)

- [ ] Anmelden, Liste: Suche, ein Filter aus jeder Gruppe, Sortierung, Seitenwechsel, Wechsel Tabelle/Raster.
- [ ] Detailseite: Bild groß öffnen und schließen, vorheriger/nächster Eintrag, Drucken (Vorschau).
- [ ] Bearbeiten: speichern, speichern und nächster, Als NEU speichern (`einkauf_edit.php`), Löschen-Dialog (abbrechen).
- [ ] Einkaufsliste: Filter, ein Stand-Schalter, Zeile antippen.
- [ ] Verwaltung: Pflegeseite, Kategorien, Zusammenführen-Dialog (abbrechen), Bildprüfung.
- [ ] Statistik, Export (Karte, Download), Abmelden, Gastzugang (keine Adminseiten, kein Gesamtwert).
- [ ] Hell/Dunkel umschalten auf Liste, Detail, Formular.
- [ ] Konsole (F12): keine Meldungen „Refused to …“ oder Skriptfehler.
- [ ] **Tastatur:** Tab-Reihenfolge, sichtbarer Fokus, Sprunglink „Zum Inhalt springen“, Dialoge mit Esc schließen, Typeahead mit Pfeiltasten und Enter.

## 2. Desktop, Chrome oder Firefox (Stichprobe)

- [ ] Liste, Detail und Bearbeiten öffnen und einen Eintrag speichern.

## 3. iPad (hochkant und quer), Safari und Edge

- [ ] Liste: Filter öffnen und schließen (Bottom-Sheet bis 1280 px), Raster, Tipp auf Eintrag.
- [ ] Formular: Datumsfelder, Tastatur, Speichern.
- [ ] Einkaufsliste: Filter, Schalter, Tipp auf Zeile.
- [ ] Barcode-Scanner in `edit.php` (lädt ZXing, Kamera erlauben).

## 4. iPhone, Safari

- [ ] Kartenliste, untere Navigation, Mehr-Dialog, Filter-Sheet, Wischen auf der Detailseite.
- [ ] Formular: erste Gruppe offen, feste Leiste unten, Bildschirmtastatur verdeckt nichts.
- [ ] **VoiceOver** (Einstellungen, Bedienungshilfen): Suchen, Eintrag öffnen, Bearbeiten, Speichern. Werden Knöpfe, Felder und Meldungen verständlich vorgelesen?
- [ ] **Text größer** (Einstellungen, Anzeige, Textgröße): Seiten bleiben bedienbar.

## 5. iPhone, PWA vom Home-Bildschirm

- [ ] Start, Statusleiste und Ränder (Safe-Area) sehen richtig aus.
- [ ] Flugmodus an, App öffnen: Offlineseite erscheint; Flugmodus aus, Anmeldung ist noch gültig.

## 6. Zoom und Lighthouse (optional)

- [ ] **200 % Zoom** im Desktop-Browser: Liste, Detail, Formular ohne abgeschnittene Inhalte und ohne waagerechtes Scrollen der Seite.
- [ ] **Lighthouse** (Edge, F12, Lighthouse, Mobil) für Liste und Detail: Barrierefreiheit und Best Practices notieren (Ziel: Barrierefreiheit über 95), LCP der Liste notieren (Phase 4 hat keine Messung).

## Ergebnis

Datum, Geräte, Auffälligkeiten:

Wenn nichts Gravierendes offen ist, setzt Claude den Abnahme-Tag (`abnahme-2026-10`).
