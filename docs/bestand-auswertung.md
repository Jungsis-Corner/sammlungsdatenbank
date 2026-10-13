# Bestandsaufnahme: Auswertung (Phase 2, D2)

Stand: 13.10.2026. Grundlage: SQL-Dump der Live-Datenbank (MySQL **8.0.36**, 3.799 Einträge in `Sammlung`,
862 in `Einkauf`), lokal eingespielt und mit `docs/bestand.sql` ausgewertet. Die Rohdaten liegen in
`docs/bestand/` (nicht im Repo). Diese Datei enthält nur Zahlen und IDs, keine Namen.

## 1. Kurzfassung

Die Daten sind sauber. Es gibt **keine verwaisten Verweise** außer bei `Box`, **keine uneinheitlichen Geldformate**
und keine unerwarteten Statuswerte. Die Bereinigung (D3) besteht im Wesentlichen aus: Nullen zu NULL,
12 Einzelfällen, `Box`-Entscheidung und 9 exakten Dubletten in den Lookup-Tabellen.

## 2. Befunde

| Bereich | Befund | Folge |
| --- | --- | --- |
| Beziehungsspalten | Kein Eintrag verweist auf eine nicht vorhandene Lookup-Zeile (`Box` ausgenommen). Nicht ganzzahlige Werte: 0 | FK sind nach Bereinigung der Nullen möglich |
| Nullen als „keine Angabe“ | `Hersteller` 800, `Publisher` 1.342, `Verkäufer` 1.133, `Verpackung` 1.375, `Datentraeger` 1.671, `VerbautIn` 627, `Box` 3.453. NULL: Hersteller 13, Publisher 77, Verkäufer 30, Verpackung 7, Datentraeger 106, VerbautIn 3.128, Box 139 | D3: 0 wird NULL (FK ohne 0-Zeile) |
| Einzelfälle | `Kategorie` = 0: IDs 3870, 3909, 3912. `Material` = 0: ID 3737. `Standort` = 0 oder NULL: IDs 480, 644, 816, 2931, 3890, 3897, 3898, 3913. `Anzahl` NULL: ID 2830 | Zuordnung pro Fall durch Jungsi (D3) |
| `Box` | In `Sammlung.Box` stehen **Kistennummern**: 0 (3.453), 1–13 und 99. Die Tabelle `Box` kennt nur IDs 1–9 (Namen „1“–„9“) und ID 10 („Regal“). 97 Einträge haben Box 11, 12, 13 oder 99 ohne Lookup-Zeile | Entscheidung D7: Zahl oder Fremdschlüssel (siehe Abschnitt 3) |
| Geld | `Wert`: 11 leer, 1.293 ganze Zahl, 2.495 Komma-Dezimal. `Einkaufspreis`: 764 leer, 300 ganze Zahl, 2.735 Komma-Dezimal. Keine Punkt-, Tausender-, Text- oder negativen Werte | D6 ist unkritisch: Komma zu Punkt, leer zu NULL |
| `Einkauf.Preis` | 378 NULL, 5 gleich 0. `Menge` überall mindestens 1 | keine Bereinigung nötig |
| `Jahr` | 16 Einträge mit 0 (IDs u. a. 41, 52, 71, 280, 816, 1438, 2594, 2799, 3060, 3872), Bereich 1974–2026, nichts nicht ganzzahlig | 0 wird NULL |
| `Anzahl` | 1 NULL (ID 2830), 103 mit 0 (davon 64 in der Kategorie „Sonstiges“), 3.628 mit 1, 67 über 1 | 0 bleibt erlaubt (`SMALLINT UNSIGNED`), NULL wird 1 |
| Statuswerte | `Original/Homebrew`: Original 2.553, Homebrew 1.246. `Zum Verkauf`: leer 3.748, „1“ 51. `Verpackung Status`: leer 2.158, Original 1.640, Nachdruck 1. `Anleitung Status`: leer 2.855, Original 944. `Datenträger Status`: leer 114, Original 3.685. `Getestet Status`: NULL 3.431, leer 359, OK 9 | sauber, `Zum Verkauf` ist nur „“ oder „1“ |
| `Sonstiges` | **Aktive Checkbox** („Sonstiges dabei“, mit `Sonstiges Beschreibung`): 87 mal 1, 3.712 mal 0, in `edit.php` verwendet | **nicht löschen** (korrigiert D12 im Plan) |
| Bilder | Alle 3.756 URLs zeigen auf `https://www.jungsi.de/blog/wp-content/uploads/…` (nicht `/wp-content/uploads/`). 619 davon mit Doppelschrägstrich `//blog`. Flacher Ordner ohne Jahr/Monat. Endungen: jpg 3.454, webp 329, png 10, jpeg 1. 59 mit Umlauten, 25 mit Leerzeichen | Phase 3: `IMAGE_WP_BASE` = `/blog/wp-content/uploads/`, gespeichert wird nur der Dateiname |
| Bilder: Defekte | 38 Einträge mit NAS-Pfad `\jungsinas…` (Backslash, ohne Trennzeichen), IDs 41–2987, nicht darstellbar. 5 ohne Bild (IDs 1548, 3251, 3780, 3827, 3857) | Jungsi klärt die 38 NAS-Pfade (Phase 3, B2) |
| Lookup-Namen | maximale Länge 64 Zeichen, keine leeren Namen | `VARCHAR(190)` reicht |
| Dubletten blockieren `UNIQUE` | 9 Paare, die ein `UNIQUE` auf dem Namen ablehnen würde: `Hersteller` 18/714, 282/653, 464/465, 550/572, 964/965, 1029/1087 · `Publisher` 29/362 · `Verkäufer` 192/260 und 284/286 (nur Groß-/Kleinschreibung) | vor D5 zusammenführen, je Paar Entscheidung (E5) |
| Weitere Hinweise (nur Hinweisliste) | Hersteller: ID 60 und 174 (nur Leerzeichen), ID 6 und 273 (Rechtsform), ID 242 und 801, 606 und 797, 610 und 807, 391 und 808. Publisher: ID 10, 177 und 270. Verkäufer: ID 134 und 237 | kein automatisches Zusammenführen |
| Kategorien | 173 Kategorien (`06_kategorien.csv`) | Grundlage für das Mapping in D9 |
| Struktur | Alle Tabellen `utf8mb4_general_ci`, nur `Einkauf` und `Login_Log` `utf8mb4_0900_ai_ci`. Nur 2 Fremdschlüssel (`Einkauf`). Auf `Sammlung` nur der Primärschlüssel | D4 und D7 wie im Plan |

## 3. Abweichungen vom Plan und von `schema.sql`

1. **`schema.sql` entspricht nicht dem Live-Stand.** Live gibt es sechs Spalten, die `schema.sql` nicht kennt:
   `Originalverpackung` (1.549 Zeilen mit Wert), `Anleitung` (896), `Datenträger vorhanden` (3.590),
   `Datentraegertest` (5), `Testdatum` (0), `Testergebnis` (0). Sie stammen aus dem alten Modell mit Häkchen und werden
   vom Code **nicht gelesen** (die Detailansicht nutzt nur die `… Status`-Spalten). Entscheidung nötig: `schema.sql` auf
   den Live-Stand bringen und die Spalten später in D12 entfernen, oder die Spalten jetzt in einer Migration löschen.
2. **D12 im Plan** nennt `Sammlung.Sonstiges` „nirgends zugeordnet“. Das stimmt nicht, siehe oben.
3. **Phase 3:** Die WordPress-Basis ist `/blog/wp-content/uploads/`, nicht `/wp-content/uploads/`.
4. **Server:** MySQL 8.0.36. Funktionen wie `CHECK`-Constraints und CTEs sind verfügbar. E8 bleibt trotzdem bei
   „nur Funktionen, die auch MySQL 5.7/MariaDB 10.3 kennen“, damit Forks laufen.

## 4. Offene Entscheidungen für D3

1. `Box`: als Zahl führen (Lookup-Tabelle entfällt oder bleibt ungenutzt) oder als Fremdschlüssel (Tabelle um 11, 12, 13, 99 ergänzen und „Regal“ klären).
2. Zuordnung der 12 Einzelfälle (Abschnitt 2).
3. Die 9 exakten Dubletten zusammenführen (je Paar behalte ich den Eintrag mit der kleineren ID und stelle die Verweise um).
4. Die 38 Bild-Einträge mit NAS-Pfad: leeren oder neu zuordnen.
5. Die sechs Altspalten: in `schema.sql` aufnehmen und später entfernen, oder jetzt in D3/D4 entfernen.
