-- Migration D3: Datenbereinigung (Phase 2)
-- ============================================================================
-- Reine Datenänderung, keine Strukturänderung an den Hauptdaten. Läuft ohne Wartungsmodus.
-- VORHER: Dump per phpMyAdmin und die aktuelle edit.php hochladen (sie speichert leere Auswahlfelder
-- seit dieser Version als NULL statt 0). Wiederholbar: Ein zweiter Lauf ändert nichts mehr.
--
-- Was passiert:
--   1. Sicherungstabellen bak_d3_* (Zustand vor der Änderung; bleiben bis zur Abnahme stehen)
--   2. Tabelle Box um 11, 12, 13 und 99 ergänzen
--   3. 12 Einzelfälle (Kategorie, Material, Standort) nach Entscheidung von Jungsi
--   4. 9 exakte Dubletten in Hersteller, Publisher, Verkäufer zusammenführen (kleinere ID bleibt)
--   5. Nullen in Beziehungsspalten werden NULL
--   6. Jahr 0 wird NULL, Anzahl NULL wird 1, kaputte NAS-Bildpfade werden geleert
--   7. Kontrolle (letzte Abfrage), Eintrag in schema_migrations
-- Wert und Einkaufspreis bleiben unverändert (Umstellung in D6 zusammen mit dem Code).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Sicherungstabellen (CREATE TABLE schließt eine Transaktion ab, daher vor START TRANSACTION)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bak_d3_sammlung` AS
SELECT `ID`, `Kategorie`, `Material`, `Standort`, `Zustand`, `Hersteller`, `Publisher`, `Verkäufer`,
       `Verpackung`, `Datentraeger`, `VerbautIn`, `Box`, `Anzahl`, `Jahr`, `SammlungBild1`
FROM `Sammlung`;

CREATE TABLE IF NOT EXISTS `bak_d3_einkauf` AS
SELECT `ID`, `Verkaeufer` FROM `Einkauf`;

CREATE TABLE IF NOT EXISTS `bak_d3_lookup_del` (
  `Tabelle` varchar(30) NOT NULL,
  `ID` int NOT NULL,
  `Name` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`Tabelle`, `ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Ab hier eine Transaktion: bei einem Fehler bleibt die Datenbank unverändert.
-- ---------------------------------------------------------------------------
START TRANSACTION;

-- 2. Box: Kistennummern, die in Sammlung vorkommen, aber noch keine Zeile in Box haben
INSERT IGNORE INTO `Box` (`ID`, `Box`) VALUES (11, '11'), (12, '12'), (13, '13'), (99, '99');

-- 3. Einzelfälle (Entscheidungen aus docs/bestand/einzelfaelle.csv). Geändert wird nur, was noch 0 oder NULL ist.
UPDATE `Sammlung` SET `Kategorie` = 33, `Geaendert_am` = `Geaendert_am`  WHERE `ID` = 3870 AND (`Kategorie` = 0 OR `Kategorie` IS NULL);  -- Microsoft Xbox
UPDATE `Sammlung` SET `Kategorie` = 289, `Geaendert_am` = `Geaendert_am` WHERE `ID` = 3909 AND (`Kategorie` = 0 OR `Kategorie` IS NULL);  -- Schneider CPC
UPDATE `Sammlung` SET `Kategorie` = 106, `Geaendert_am` = `Geaendert_am` WHERE `ID` = 3912 AND (`Kategorie` = 0 OR `Kategorie` IS NULL);  -- Joystick
UPDATE `Sammlung` SET `Material`  = 2, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 3737 AND (`Material`  = 0 OR `Material`  IS NULL);  -- Software - Kassette
UPDATE `Sammlung` SET `Standort`  = 2, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 480  AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Konsolenzimmer
UPDATE `Sammlung` SET `Standort`  = 1, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 644  AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Hobbyraum
UPDATE `Sammlung` SET `Standort`  = 2, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 816  AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Konsolenzimmer
UPDATE `Sammlung` SET `Standort`  = 1, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 2931 AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Hobbyraum
UPDATE `Sammlung` SET `Standort`  = 1, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 3890 AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Hobbyraum
UPDATE `Sammlung` SET `Standort`  = 3, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 3897 AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Bibliothek
UPDATE `Sammlung` SET `Standort`  = 3, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 3898 AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Bibliothek
UPDATE `Sammlung` SET `Standort`  = 1, `Geaendert_am` = `Geaendert_am`   WHERE `ID` = 3913 AND (`Standort`  = 0 OR `Standort`  IS NULL);  -- Hobbyraum

-- 4. Exakte Dubletten zusammenführen. Je Paar: (a) Verweise auf die größere ID umstellen, (b) Zeile sichern,
--    (c) Zeile löschen, aber nur, wenn die Namen noch gleich sind und nichts mehr auf sie verweist.

-- Hersteller 18 <- 714
UPDATE `Sammlung` SET `Hersteller` = 18, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 714 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 18 AND b.`ID` = 714);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 18 AND b.`ID` = 714;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 18 WHERE b.`ID` = 714 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 714);

-- Hersteller 282 <- 653
UPDATE `Sammlung` SET `Hersteller` = 282, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 653 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 282 AND b.`ID` = 653);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 282 AND b.`ID` = 653;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 282 WHERE b.`ID` = 653 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 653);

-- Hersteller 464 <- 465
UPDATE `Sammlung` SET `Hersteller` = 464, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 465 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 464 AND b.`ID` = 465);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 464 AND b.`ID` = 465;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 464 WHERE b.`ID` = 465 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 465);

-- Hersteller 550 <- 572
UPDATE `Sammlung` SET `Hersteller` = 550, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 572 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 550 AND b.`ID` = 572);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 550 AND b.`ID` = 572;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 550 WHERE b.`ID` = 572 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 572);

-- Hersteller 964 <- 965
UPDATE `Sammlung` SET `Hersteller` = 964, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 965 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 964 AND b.`ID` = 965);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 964 AND b.`ID` = 965;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 964 WHERE b.`ID` = 965 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 965);

-- Hersteller 1029 <- 1087
UPDATE `Sammlung` SET `Hersteller` = 1029, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller` = 1087 AND EXISTS (SELECT 1 FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 1029 AND b.`ID` = 1087);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Hersteller', b.`ID`, b.`Hersteller` FROM `Hersteller` a JOIN `Hersteller` b ON a.`Hersteller` = b.`Hersteller` WHERE a.`ID` = 1029 AND b.`ID` = 1087;
DELETE b FROM `Hersteller` b JOIN `Hersteller` a ON a.`Hersteller` = b.`Hersteller` AND a.`ID` = 1029 WHERE b.`ID` = 1087 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Hersteller` = 1087);

-- Publisher 29 <- 362
UPDATE `Sammlung` SET `Publisher` = 29, `Geaendert_am` = `Geaendert_am` WHERE `Publisher` = 362 AND EXISTS (SELECT 1 FROM `Publisher` a JOIN `Publisher` b ON a.`Publisher` = b.`Publisher` WHERE a.`ID` = 29 AND b.`ID` = 362);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Publisher', b.`ID`, b.`Publisher` FROM `Publisher` a JOIN `Publisher` b ON a.`Publisher` = b.`Publisher` WHERE a.`ID` = 29 AND b.`ID` = 362;
DELETE b FROM `Publisher` b JOIN `Publisher` a ON a.`Publisher` = b.`Publisher` AND a.`ID` = 29 WHERE b.`ID` = 362 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Publisher` = 362);

-- Verkäufer 192 <- 260 (auch Einkauf umstellen)
UPDATE `Sammlung` SET `Verkäufer` = 192, `Geaendert_am` = `Geaendert_am` WHERE `Verkäufer` = 260 AND EXISTS (SELECT 1 FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 192 AND b.`ID` = 260);
UPDATE `Einkauf` SET `Verkaeufer` = 192, `Geaendert_am` = `Geaendert_am` WHERE `Verkaeufer` = 260 AND EXISTS (SELECT 1 FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 192 AND b.`ID` = 260);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Verkäufer', b.`ID`, b.`Verkäufer` FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 192 AND b.`ID` = 260;
DELETE b FROM `Verkäufer` b JOIN `Verkäufer` a ON a.`Verkäufer` = b.`Verkäufer` AND a.`ID` = 192 WHERE b.`ID` = 260 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Verkäufer` = 260) AND NOT EXISTS (SELECT 1 FROM `Einkauf` WHERE `Verkaeufer` = 260);

-- Verkäufer 284 <- 286 (Namen unterscheiden sich nur in Groß-/Kleinschreibung; auch Einkauf umstellen)
UPDATE `Sammlung` SET `Verkäufer` = 284, `Geaendert_am` = `Geaendert_am` WHERE `Verkäufer` = 286 AND EXISTS (SELECT 1 FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 284 AND b.`ID` = 286);
UPDATE `Einkauf` SET `Verkaeufer` = 284, `Geaendert_am` = `Geaendert_am` WHERE `Verkaeufer` = 286 AND EXISTS (SELECT 1 FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 284 AND b.`ID` = 286);
INSERT IGNORE INTO `bak_d3_lookup_del` (`Tabelle`, `ID`, `Name`) SELECT 'Verkäufer', b.`ID`, b.`Verkäufer` FROM `Verkäufer` a JOIN `Verkäufer` b ON a.`Verkäufer` = b.`Verkäufer` WHERE a.`ID` = 284 AND b.`ID` = 286;
DELETE b FROM `Verkäufer` b JOIN `Verkäufer` a ON a.`Verkäufer` = b.`Verkäufer` AND a.`ID` = 284 WHERE b.`ID` = 286 AND NOT EXISTS (SELECT 1 FROM `Sammlung` WHERE `Verkäufer` = 286) AND NOT EXISTS (SELECT 1 FROM `Einkauf` WHERE `Verkaeufer` = 286);

-- 5. Nullen in Beziehungsspalten werden NULL ("keine Angabe")
UPDATE `Sammlung` SET `Kategorie`    = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Kategorie`    = 0;
UPDATE `Sammlung` SET `Material`     = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Material`     = 0;
UPDATE `Sammlung` SET `Standort`     = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Standort`     = 0;
UPDATE `Sammlung` SET `Hersteller`   = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Hersteller`   = 0;
UPDATE `Sammlung` SET `Publisher`    = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Publisher`    = 0;
UPDATE `Sammlung` SET `Verkäufer`    = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Verkäufer`    = 0;
UPDATE `Sammlung` SET `Verpackung`   = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Verpackung`   = 0;
UPDATE `Sammlung` SET `Datentraeger` = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Datentraeger` = 0;
UPDATE `Sammlung` SET `Zustand`      = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Zustand`      = 0;
UPDATE `Sammlung` SET `VerbautIn`    = NULL, `Geaendert_am` = `Geaendert_am` WHERE `VerbautIn`    = 0;
UPDATE `Sammlung` SET `Box`          = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Box`          = 0;

-- 6. Jahr, Anzahl, kaputte NAS-Bildpfade (Backslash; Sicherung als CSV unter docs/bestand/ und in bak_d3_sammlung)
UPDATE `Sammlung` SET `Jahr` = NULL, `Geaendert_am` = `Geaendert_am` WHERE `Jahr` = 0;
UPDATE `Sammlung` SET `Anzahl` = 1, `Geaendert_am` = `Geaendert_am` WHERE `Anzahl` IS NULL;
UPDATE `Sammlung` SET `SammlungBild1` = '', `Geaendert_am` = `Geaendert_am` WHERE LOCATE(CHAR(92), `SammlungBild1`) > 0;

COMMIT;

-- 7. Eintrag in schema_migrations
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_03_bereinigung.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- "vorher" kommt aus der Sicherung bak_d3_sammlung, "nachher" aus der Tabelle Sammlung.
-- Spalten mit Einzelfällen (Kategorie, Material, Standort) haben zusätzlich k aufgelöste Fälle.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.vorher, c.nachher, c.Status FROM (
  SELECT 'Kategorie: 0 / NULL' AS Pruefung, CONCAT(v.n0, ' / ', v.nn) AS vorher, CONCAT(n.n0, ' / ', n.nn) AS nachher,
         IF(n.n0 = 0 AND n.nn = v.nn + v.n0 - 3, 'OK', 'PRÜFEN') AS Status, 1 AS o
  FROM (SELECT COALESCE(SUM(`Kategorie` = 0), 0) AS n0, COALESCE(SUM(`Kategorie` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Kategorie` = 0), 0) AS n0, COALESCE(SUM(`Kategorie` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Material: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn),
         IF(n.n0 = 0 AND n.nn = v.nn + v.n0 - 1, 'OK', 'PRÜFEN'), 2
  FROM (SELECT COALESCE(SUM(`Material` = 0), 0) AS n0, COALESCE(SUM(`Material` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Material` = 0), 0) AS n0, COALESCE(SUM(`Material` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Standort: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn),
         IF(n.n0 = 0 AND n.nn = v.nn + v.n0 - 8, 'OK', 'PRÜFEN'), 3
  FROM (SELECT COALESCE(SUM(`Standort` = 0), 0) AS n0, COALESCE(SUM(`Standort` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Standort` = 0), 0) AS n0, COALESCE(SUM(`Standort` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Hersteller: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 4
  FROM (SELECT COALESCE(SUM(`Hersteller` = 0), 0) AS n0, COALESCE(SUM(`Hersteller` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Hersteller` = 0), 0) AS n0, COALESCE(SUM(`Hersteller` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Publisher: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 5
  FROM (SELECT COALESCE(SUM(`Publisher` = 0), 0) AS n0, COALESCE(SUM(`Publisher` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Publisher` = 0), 0) AS n0, COALESCE(SUM(`Publisher` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Verkäufer: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 6
  FROM (SELECT COALESCE(SUM(`Verkäufer` = 0), 0) AS n0, COALESCE(SUM(`Verkäufer` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Verkäufer` = 0), 0) AS n0, COALESCE(SUM(`Verkäufer` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Verpackung: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 7
  FROM (SELECT COALESCE(SUM(`Verpackung` = 0), 0) AS n0, COALESCE(SUM(`Verpackung` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Verpackung` = 0), 0) AS n0, COALESCE(SUM(`Verpackung` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Datentraeger: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 8
  FROM (SELECT COALESCE(SUM(`Datentraeger` = 0), 0) AS n0, COALESCE(SUM(`Datentraeger` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Datentraeger` = 0), 0) AS n0, COALESCE(SUM(`Datentraeger` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Zustand: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 9
  FROM (SELECT COALESCE(SUM(`Zustand` = 0), 0) AS n0, COALESCE(SUM(`Zustand` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Zustand` = 0), 0) AS n0, COALESCE(SUM(`Zustand` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'VerbautIn: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 10
  FROM (SELECT COALESCE(SUM(`VerbautIn` = 0), 0) AS n0, COALESCE(SUM(`VerbautIn` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`VerbautIn` = 0), 0) AS n0, COALESCE(SUM(`VerbautIn` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Box: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 11
  FROM (SELECT COALESCE(SUM(`Box` = 0), 0) AS n0, COALESCE(SUM(`Box` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Box` = 0), 0) AS n0, COALESCE(SUM(`Box` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Jahr: 0 / NULL', CONCAT(v.n0, ' / ', v.nn), CONCAT(n.n0, ' / ', n.nn), IF(n.n0 = 0 AND n.nn = v.nn + v.n0, 'OK', 'PRÜFEN'), 12
  FROM (SELECT COALESCE(SUM(`Jahr` = 0), 0) AS n0, COALESCE(SUM(`Jahr` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Jahr` = 0), 0) AS n0, COALESCE(SUM(`Jahr` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Anzahl: NULL', v.nn, n.nn, IF(n.nn = 0, 'OK', 'PRÜFEN'), 13
  FROM (SELECT COALESCE(SUM(`Anzahl` IS NULL), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(`Anzahl` IS NULL), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Bildpfade mit Backslash', v.nn, n.nn, IF(n.nn = 0, 'OK', 'PRÜFEN'), 14
  FROM (SELECT COALESCE(SUM(LOCATE(CHAR(92), `SammlungBild1`) > 0), 0) AS nn FROM `bak_d3_sammlung`) v,
       (SELECT COALESCE(SUM(LOCATE(CHAR(92), `SammlungBild1`) > 0), 0) AS nn FROM `Sammlung`) n
  UNION ALL
  SELECT 'Verweise ohne Treffer in der Lookup-Tabelle (alle 11 Spalten)', '-', w.n, IF(w.n = 0, 'OK', 'PRÜFEN'), 15
  FROM (SELECT SUM(x.n) AS n FROM (
          SELECT COUNT(*) AS n FROM `Sammlung` S WHERE S.`Kategorie`    IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Kategorie`    L WHERE L.`ID` = S.`Kategorie`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Material`     IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Material`     L WHERE L.`ID` = S.`Material`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Standort`     IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Standort`     L WHERE L.`ID` = S.`Standort`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Hersteller`   IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Hersteller`   L WHERE L.`ID` = S.`Hersteller`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Publisher`    IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Publisher`    L WHERE L.`ID` = S.`Publisher`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Verkäufer`    IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Verkäufer`    L WHERE L.`ID` = S.`Verkäufer`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Verpackung`   IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Verpackung`   L WHERE L.`ID` = S.`Verpackung`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Datentraeger` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Datentraeger` L WHERE L.`ID` = S.`Datentraeger`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Zustand`      IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Zustand`      L WHERE L.`ID` = S.`Zustand`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`VerbautIn`    IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Sammlung`     L WHERE L.`ID` = S.`VerbautIn`)
    UNION ALL SELECT COUNT(*) FROM `Sammlung` S WHERE S.`Box`          IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `Box`           L WHERE L.`ID` = S.`Box`)
        ) x) w
  UNION ALL
  SELECT 'Lookup-Zeilen gelöscht (Dubletten)', '-', COUNT(*), IF(COUNT(*) = 9, 'OK', 'PRÜFEN'), 16 FROM `bak_d3_lookup_del`
  UNION ALL
  SELECT 'Box-Zeilen 11, 12, 13, 99 vorhanden', '-', COUNT(*), IF(COUNT(*) = 4, 'OK', 'PRÜFEN'), 17 FROM `Box` WHERE `ID` IN (11, 12, 13, 99)
  UNION ALL
  SELECT 'Sammlung: Anzahl Einträge', v.k, n.k, IF(v.k = n.k, 'OK', 'PRÜFEN'), 18
  FROM (SELECT COUNT(*) AS k FROM `bak_d3_sammlung`) v,
       (SELECT COUNT(*) AS k FROM `Sammlung`) n
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen zwischen den Markierungen ohne "-- " ausführen.
-- Stellt aus den Sicherungstabellen den Stand vor der Migration wieder her.
-- Danach können die Tabellen bak_d3_* gelöscht werden (DROP TABLE), wenn sie nicht mehr gebraucht werden.
-- ---------------------------------------------------------------------------
-- START TRANSACTION;
-- INSERT IGNORE INTO `Hersteller` (`ID`, `Hersteller`) SELECT `ID`, `Name` FROM `bak_d3_lookup_del` WHERE `Tabelle` = 'Hersteller';
-- INSERT IGNORE INTO `Publisher` (`ID`, `Publisher`) SELECT `ID`, `Name` FROM `bak_d3_lookup_del` WHERE `Tabelle` = 'Publisher';
-- INSERT IGNORE INTO `Verkäufer` (`ID`, `Verkäufer`) SELECT `ID`, `Name` FROM `bak_d3_lookup_del` WHERE `Tabelle` = 'Verkäufer';
-- UPDATE `Sammlung` S JOIN `bak_d3_sammlung` B ON B.`ID` = S.`ID` SET
--   S.`Kategorie` = B.`Kategorie`, S.`Material` = B.`Material`, S.`Standort` = B.`Standort`, S.`Zustand` = B.`Zustand`,
--   S.`Hersteller` = B.`Hersteller`, S.`Publisher` = B.`Publisher`, S.`Verkäufer` = B.`Verkäufer`, S.`Verpackung` = B.`Verpackung`,
--   S.`Datentraeger` = B.`Datentraeger`, S.`VerbautIn` = B.`VerbautIn`, S.`Box` = B.`Box`, S.`Anzahl` = B.`Anzahl`,
--   S.`Jahr` = B.`Jahr`, S.`SammlungBild1` = B.`SammlungBild1`, S.`Geaendert_am` = S.`Geaendert_am`;
-- UPDATE `Einkauf` E JOIN `bak_d3_einkauf` B ON B.`ID` = E.`ID` SET E.`Verkaeufer` = B.`Verkaeufer`, E.`Geaendert_am` = E.`Geaendert_am`;
-- DELETE FROM `Box` WHERE `ID` IN (11, 12, 13, 99);
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_03_bereinigung.sql';
-- COMMIT;
