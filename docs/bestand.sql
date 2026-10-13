-- ============================================================================
-- Bestandsaufnahme (Phase 2, Aufgabe D2): NUR LESENDE Abfragen
-- ============================================================================
-- Diese Datei enthält ausschließlich SELECT-Abfragen. Sie ändert nichts an der Datenbank.
-- Sie nutzt nur Funktionen, die auch MySQL 5.7 und MariaDB 10.3 kennen (kein REGEXP_REPLACE,
-- keine CTEs, keine Fensterfunktionen).
--
-- So gehst du vor (phpMyAdmin, Reiter "SQL", richtige Datenbank links gewählt):
--   1. Einen BLOCK (von "-- ===== BLOCK n" bis zum Semikolon vor dem nächsten Block) kopieren,
--      einfügen, auf "OK" klicken.
--   2. Unter dem Ergebnis "Exportieren" (Abfrageergebnis) wählen, Format CSV, Spaltennamen in
--      der ersten Zeile, Zeichensatz UTF-8.
--   3. Als docs/bestand/NN_name.csv speichern, mit den Namen, die bei jedem Block stehen.
--   4. Wenn ein Block eine Fehlermeldung liefert: Meldung und Blocknummer an Claude Code schicken.
--
-- Die Ergebnisse enthalten Namen und Beispielwerte aus der Sammlung. docs/bestand/ steht in der
-- .gitignore und wird nicht committet.
-- ============================================================================

-- ===== BLOCK 1: Beziehungsspalten ohne Treffer in der Lookup-Tabelle (docs/bestand/01_referenzen.csv) =====
-- Je Spalte: Anzahl NULL, 0, nicht ganzzahlig (nur bei float-Spalten) und ohne Treffer, mit bis zu 10 Beispiel-IDs.

SELECT 'Sammlung' AS Tabelle, 'Kategorie' AS Spalte, 'Kategorie' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Kategorie` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Kategorie` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> FLOOR(S.`Kategorie`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> 0 AND NOT EXISTS (SELECT 1 FROM `Kategorie` X WHERE X.`ID` = S.`Kategorie`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> 0 AND NOT EXISTS (SELECT 1 FROM `Kategorie` X WHERE X.`ID` = S.`Kategorie`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Material' AS Spalte, 'Material' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Material` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Material` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Material` IS NOT NULL AND S.`Material` <> FLOOR(S.`Material`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Material` IS NOT NULL AND S.`Material` <> 0 AND NOT EXISTS (SELECT 1 FROM `Material` X WHERE X.`ID` = S.`Material`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Material` IS NOT NULL AND S.`Material` <> 0 AND NOT EXISTS (SELECT 1 FROM `Material` X WHERE X.`ID` = S.`Material`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Hersteller' AS Spalte, 'Hersteller' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Hersteller` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Hersteller` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Hersteller` IS NOT NULL AND S.`Hersteller` <> FLOOR(S.`Hersteller`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Hersteller` IS NOT NULL AND S.`Hersteller` <> 0 AND NOT EXISTS (SELECT 1 FROM `Hersteller` X WHERE X.`ID` = S.`Hersteller`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Hersteller` IS NOT NULL AND S.`Hersteller` <> 0 AND NOT EXISTS (SELECT 1 FROM `Hersteller` X WHERE X.`ID` = S.`Hersteller`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Publisher' AS Spalte, 'Publisher' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Publisher` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Publisher` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Publisher` IS NOT NULL AND S.`Publisher` <> FLOOR(S.`Publisher`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Publisher` IS NOT NULL AND S.`Publisher` <> 0 AND NOT EXISTS (SELECT 1 FROM `Publisher` X WHERE X.`ID` = S.`Publisher`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Publisher` IS NOT NULL AND S.`Publisher` <> 0 AND NOT EXISTS (SELECT 1 FROM `Publisher` X WHERE X.`ID` = S.`Publisher`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Verkäufer' AS Spalte, 'Verkäufer' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Verkäufer` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Verkäufer` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Verkäufer` IS NOT NULL AND S.`Verkäufer` <> FLOOR(S.`Verkäufer`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Verkäufer` IS NOT NULL AND S.`Verkäufer` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verkäufer` X WHERE X.`ID` = S.`Verkäufer`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Verkäufer` IS NOT NULL AND S.`Verkäufer` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verkäufer` X WHERE X.`ID` = S.`Verkäufer`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Standort' AS Spalte, 'Standort' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Standort` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Standort` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Standort` IS NOT NULL AND S.`Standort` <> FLOOR(S.`Standort`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Standort` IS NOT NULL AND S.`Standort` <> 0 AND NOT EXISTS (SELECT 1 FROM `Standort` X WHERE X.`ID` = S.`Standort`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Standort` IS NOT NULL AND S.`Standort` <> 0 AND NOT EXISTS (SELECT 1 FROM `Standort` X WHERE X.`ID` = S.`Standort`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Verpackung' AS Spalte, 'Verpackung' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Verpackung` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Verpackung` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Verpackung` IS NOT NULL AND S.`Verpackung` <> FLOOR(S.`Verpackung`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Verpackung` IS NOT NULL AND S.`Verpackung` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verpackung` X WHERE X.`ID` = S.`Verpackung`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Verpackung` IS NOT NULL AND S.`Verpackung` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verpackung` X WHERE X.`ID` = S.`Verpackung`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Datentraeger' AS Spalte, 'Datentraeger' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Datentraeger` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Datentraeger` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Datentraeger` IS NOT NULL AND S.`Datentraeger` <> FLOOR(S.`Datentraeger`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Datentraeger` IS NOT NULL AND S.`Datentraeger` <> 0 AND NOT EXISTS (SELECT 1 FROM `Datentraeger` X WHERE X.`ID` = S.`Datentraeger`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Datentraeger` IS NOT NULL AND S.`Datentraeger` <> 0 AND NOT EXISTS (SELECT 1 FROM `Datentraeger` X WHERE X.`ID` = S.`Datentraeger`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Zustand' AS Spalte, 'Zustand' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Zustand` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Zustand` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Zustand` IS NOT NULL AND S.`Zustand` <> FLOOR(S.`Zustand`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Zustand` IS NOT NULL AND S.`Zustand` <> 0 AND NOT EXISTS (SELECT 1 FROM `Zustand` X WHERE X.`ID` = S.`Zustand`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Zustand` IS NOT NULL AND S.`Zustand` <> 0 AND NOT EXISTS (SELECT 1 FROM `Zustand` X WHERE X.`ID` = S.`Zustand`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'Box' AS Spalte, 'Box' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Box` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Box` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Box` IS NOT NULL AND S.`Box` <> FLOOR(S.`Box`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Box` IS NOT NULL AND S.`Box` <> 0 AND NOT EXISTS (SELECT 1 FROM `Box` X WHERE X.`ID` = S.`Box`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Box` IS NOT NULL AND S.`Box` <> 0 AND NOT EXISTS (SELECT 1 FROM `Box` X WHERE X.`ID` = S.`Box`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung' AS Tabelle, 'VerbautIn' AS Spalte, 'Sammlung' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`VerbautIn` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`VerbautIn` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`VerbautIn` IS NOT NULL AND S.`VerbautIn` <> FLOOR(S.`VerbautIn`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`VerbautIn` IS NOT NULL AND S.`VerbautIn` <> 0 AND NOT EXISTS (SELECT 1 FROM `Sammlung` X WHERE X.`ID` = S.`VerbautIn`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`VerbautIn` IS NOT NULL AND S.`VerbautIn` <> 0 AND NOT EXISTS (SELECT 1 FROM `Sammlung` X WHERE X.`ID` = S.`VerbautIn`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Sammlung` S
UNION ALL
SELECT 'Einkauf' AS Tabelle, 'Kategorie' AS Spalte, 'Kategorie' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Kategorie` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Kategorie` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> FLOOR(S.`Kategorie`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> 0 AND NOT EXISTS (SELECT 1 FROM `Kategorie` X WHERE X.`ID` = S.`Kategorie`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Kategorie` IS NOT NULL AND S.`Kategorie` <> 0 AND NOT EXISTS (SELECT 1 FROM `Kategorie` X WHERE X.`ID` = S.`Kategorie`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Einkauf` S
UNION ALL
SELECT 'Einkauf' AS Tabelle, 'Verkaeufer' AS Spalte, 'Verkäufer' AS Lookup,
  COUNT(*) AS Gesamt,
  COALESCE(SUM(S.`Verkaeufer` IS NULL), 0) AS Anzahl_NULL,
  COALESCE(SUM(S.`Verkaeufer` = 0), 0) AS Anzahl_0,
  COALESCE(SUM(S.`Verkaeufer` IS NOT NULL AND S.`Verkaeufer` <> FLOOR(S.`Verkaeufer`)), 0) AS Nicht_ganzzahlig,
  COALESCE(SUM(S.`Verkaeufer` IS NOT NULL AND S.`Verkaeufer` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verkäufer` X WHERE X.`ID` = S.`Verkaeufer`)), 0) AS Ohne_Treffer,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Verkaeufer` IS NOT NULL AND S.`Verkaeufer` <> 0 AND NOT EXISTS (SELECT 1 FROM `Verkäufer` X WHERE X.`ID` = S.`Verkaeufer`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiel_IDs
FROM `Einkauf` S;

-- ===== BLOCK 2: Formate von Wert, Einkaufspreis, Jahr, Anzahl (docs/bestand/02_formate.csv) =====
-- Wert/Einkaufspreis sind Text: Zählung nach Format mit bis zu 20 verschiedenen Beispielwerten.

SELECT 'Sammlung.Wert' AS Spalte, f.Format AS Befund, COUNT(*) AS Anzahl,
  SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT f.Wert ORDER BY f.Wert SEPARATOR ' | '), ' | ', 20) AS Beispiele
FROM (SELECT S.`Wert` AS Wert,
    CASE
      WHEN S.`Wert` IS NULL OR TRIM(S.`Wert`) = '' THEN '01 leer/NULL'
      WHEN S.`Wert` REGEXP '^[0-9]+$' THEN '02 ganze Zahl (20)'
      WHEN S.`Wert` REGEXP '^[0-9]+,[0-9]{1,2}$' THEN '03 Komma-Dezimal (20,50)'
      WHEN S.`Wert` REGEXP '^[0-9]+[.][0-9]{1,2}$' THEN '04 Punkt-Dezimal (20.50)'
      WHEN S.`Wert` REGEXP '^[0-9]{1,3}([.][0-9]{3})+(,[0-9]{1,2})?$' THEN '05 Tausenderpunkt (1.250,00)'
      WHEN S.`Wert` REGEXP '^[0-9]{1,3}(,[0-9]{3})+([.][0-9]{1,2})?$' THEN '06 Tausenderkomma (1,250.00)'
      WHEN S.`Wert` <> TRIM(S.`Wert`) AND TRIM(S.`Wert`) REGEXP '^[0-9]+([.,][0-9]{1,2})?$' THEN '07 Zahl mit Leerzeichen am Rand'
      WHEN S.`Wert` REGEXP '^-' THEN '08 negativ'
      ELSE '09 sonstiger Text (Euro-Zeichen, Buchstaben, mehrere Zahlen ...)'
    END AS Format
  FROM `Sammlung` S) f
GROUP BY f.Format
UNION ALL
SELECT 'Sammlung.Einkaufspreis' AS Spalte, f.Format AS Befund, COUNT(*) AS Anzahl,
  SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT f.Wert ORDER BY f.Wert SEPARATOR ' | '), ' | ', 20) AS Beispiele
FROM (SELECT S.`Einkaufspreis` AS Wert,
    CASE
      WHEN S.`Einkaufspreis` IS NULL OR TRIM(S.`Einkaufspreis`) = '' THEN '01 leer/NULL'
      WHEN S.`Einkaufspreis` REGEXP '^[0-9]+$' THEN '02 ganze Zahl (20)'
      WHEN S.`Einkaufspreis` REGEXP '^[0-9]+,[0-9]{1,2}$' THEN '03 Komma-Dezimal (20,50)'
      WHEN S.`Einkaufspreis` REGEXP '^[0-9]+[.][0-9]{1,2}$' THEN '04 Punkt-Dezimal (20.50)'
      WHEN S.`Einkaufspreis` REGEXP '^[0-9]{1,3}([.][0-9]{3})+(,[0-9]{1,2})?$' THEN '05 Tausenderpunkt (1.250,00)'
      WHEN S.`Einkaufspreis` REGEXP '^[0-9]{1,3}(,[0-9]{3})+([.][0-9]{1,2})?$' THEN '06 Tausenderkomma (1,250.00)'
      WHEN S.`Einkaufspreis` <> TRIM(S.`Einkaufspreis`) AND TRIM(S.`Einkaufspreis`) REGEXP '^[0-9]+([.,][0-9]{1,2})?$' THEN '07 Zahl mit Leerzeichen am Rand'
      WHEN S.`Einkaufspreis` REGEXP '^-' THEN '08 negativ'
      ELSE '09 sonstiger Text (Euro-Zeichen, Buchstaben, mehrere Zahlen ...)'
    END AS Format
  FROM `Sammlung` S) f
GROUP BY f.Format
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'NULL' AS Befund, COALESCE(SUM(S.`Jahr` IS NULL), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Jahr` IS NULL THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'gleich 0' AS Befund, COALESCE(SUM(S.`Jahr` = 0), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Jahr` = 0 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'nicht ganzzahlig' AS Befund, COALESCE(SUM(S.`Jahr` IS NOT NULL AND S.`Jahr` <> FLOOR(S.`Jahr`)), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Jahr` IS NOT NULL AND S.`Jahr` <> FLOOR(S.`Jahr`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'vor 1900 (ohne 0)' AS Befund, COALESCE(SUM(S.`Jahr` > 0 AND S.`Jahr` < 1900), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Jahr` > 0 AND S.`Jahr` < 1900 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'nach nächstem Jahr' AS Befund, COALESCE(SUM(S.`Jahr` > YEAR(CURDATE()) + 1), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Jahr` > YEAR(CURDATE()) + 1 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Jahr' AS Spalte, 'Minimum / Maximum (ohne 0)' AS Befund, 0 AS Anzahl,
  CONCAT(COALESCE(MIN(NULLIF(S.`Jahr`, 0)), ''), ' / ', COALESCE(MAX(NULLIF(S.`Jahr`, 0)), '')) AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Anzahl' AS Spalte, 'NULL' AS Befund, COALESCE(SUM(S.`Anzahl` IS NULL), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Anzahl` IS NULL THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Anzahl' AS Spalte, 'gleich 0' AS Befund, COALESCE(SUM(S.`Anzahl` = 0), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Anzahl` = 0 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Anzahl' AS Spalte, 'negativ' AS Befund, COALESCE(SUM(S.`Anzahl` < 0), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Anzahl` < 0 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Anzahl' AS Spalte, 'nicht ganzzahlig' AS Befund, COALESCE(SUM(S.`Anzahl` IS NOT NULL AND S.`Anzahl` <> FLOOR(S.`Anzahl`)), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Anzahl` IS NOT NULL AND S.`Anzahl` <> FLOOR(S.`Anzahl`) THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Sammlung.Anzahl' AS Spalte, 'größer als 1' AS Befund, COALESCE(SUM(S.`Anzahl` > 1), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Anzahl` > 1 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Einkauf.Preis' AS Spalte, 'NULL' AS Befund, COALESCE(SUM(S.`Preis` IS NULL), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Preis` IS NULL THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Einkauf` S
UNION ALL
SELECT 'Einkauf.Preis' AS Spalte, 'gleich 0' AS Befund, COALESCE(SUM(S.`Preis` = 0), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Preis` = 0 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Einkauf` S
UNION ALL
SELECT 'Einkauf.Preis' AS Spalte, 'negativ' AS Befund, COALESCE(SUM(S.`Preis` < 0), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Preis` < 0 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Einkauf` S
UNION ALL
SELECT 'Einkauf.Menge' AS Spalte, 'kleiner als 1' AS Befund, COALESCE(SUM(S.`Menge` < 1), 0) AS Anzahl, COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN S.`Menge` < 1 THEN S.`ID` END ORDER BY S.`ID`), ',', 10), '') AS Beispiele
FROM `Einkauf` S
ORDER BY Spalte, Befund;

-- ===== BLOCK 3: Verwendete Werte der Status-Spalten, Zum Verkauf, Box, Sonstiges (docs/bestand/03_statuswerte.csv) =====
-- Schreibweisen = wie viele verschiedene Schreibweisen (Groß/Klein) sich hinter dem Wert verbergen.

SELECT 'Original/Homebrew' AS Spalte,
  CASE WHEN S.`Original/Homebrew` IS NULL THEN '(NULL)' WHEN S.`Original/Homebrew` = '' THEN '(leer)' ELSE S.`Original/Homebrew` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Original/Homebrew`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Original/Homebrew`
UNION ALL
SELECT 'Verpackung Status' AS Spalte,
  CASE WHEN S.`Verpackung Status` IS NULL THEN '(NULL)' WHEN S.`Verpackung Status` = '' THEN '(leer)' ELSE S.`Verpackung Status` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Verpackung Status`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Verpackung Status`
UNION ALL
SELECT 'Anleitung Status' AS Spalte,
  CASE WHEN S.`Anleitung Status` IS NULL THEN '(NULL)' WHEN S.`Anleitung Status` = '' THEN '(leer)' ELSE S.`Anleitung Status` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Anleitung Status`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Anleitung Status`
UNION ALL
SELECT 'Datenträger Status' AS Spalte,
  CASE WHEN S.`Datenträger Status` IS NULL THEN '(NULL)' WHEN S.`Datenträger Status` = '' THEN '(leer)' ELSE S.`Datenträger Status` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Datenträger Status`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Datenträger Status`
UNION ALL
SELECT 'Zum Verkauf' AS Spalte,
  CASE WHEN S.`Zum Verkauf` IS NULL THEN '(NULL)' WHEN S.`Zum Verkauf` = '' THEN '(leer)' ELSE S.`Zum Verkauf` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Zum Verkauf`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Zum Verkauf`
UNION ALL
SELECT 'Getestet Status' AS Spalte,
  CASE WHEN S.`Getestet Status` IS NULL THEN '(NULL)' WHEN S.`Getestet Status` = '' THEN '(leer)' ELSE S.`Getestet Status` END AS Wert,
  COUNT(*) AS Anzahl, COUNT(DISTINCT BINARY S.`Getestet Status`) AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Getestet Status`
UNION ALL
SELECT 'Box' AS Spalte, '(Sammlung) Zeilen mit einem Box-Wert' AS Wert, COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Sammlung` S WHERE S.`Box` IS NOT NULL
UNION ALL
SELECT 'Box' AS Spalte, '(Sammlung) davon: ID existiert in Tabelle Box' AS Wert, COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Sammlung` S WHERE S.`Box` IS NOT NULL AND EXISTS (SELECT 1 FROM `Box` X WHERE X.`ID` = S.`Box`)
UNION ALL
SELECT 'Box' AS Spalte, '(Sammlung) verschiedene Box-Werte' AS Wert, COUNT(DISTINCT S.`Box`) AS Anzahl, '' AS Schreibweisen
FROM `Sammlung` S
UNION ALL
SELECT 'Box' AS Spalte, '(Lookup Box) Zeilen' AS Wert, COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Box` B
UNION ALL
SELECT 'Box' AS Spalte, '(Lookup Box) Name rein numerisch' AS Wert, COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Box` B WHERE B.`Box` REGEXP '^[0-9]+$'
UNION ALL
SELECT 'Box' AS Spalte, '(Lookup Box) Name nicht numerisch' AS Wert, COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Box` B WHERE B.`Box` IS NOT NULL AND B.`Box` NOT REGEXP '^[0-9]+$'
UNION ALL
SELECT 'Sonstiges (keiner Tabelle zugeordnet)' AS Spalte, CASE WHEN S.`Sonstiges` IS NULL THEN '(NULL)' ELSE CONCAT('', S.`Sonstiges`) END AS Wert,
  COUNT(*) AS Anzahl, '' AS Schreibweisen
FROM `Sammlung` S
GROUP BY S.`Sonstiges`
ORDER BY Spalte, Wert;

-- ===== BLOCK 4: Muster von SammlungBild1 (docs/bestand/04_bilder.csv) =====
-- Grundlage für Phase 3 (Bildschicht). Beispiele: bis zu 5 verschiedene Werte.

SELECT 'Muster' AS Spalte, f.Muster AS Befund, COUNT(*) AS Anzahl,
  SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT f.Wert ORDER BY f.Wert SEPARATOR ' | '), ' | ', 5) AS Beispiele
FROM (SELECT S.`SammlungBild1` AS Wert,
    CASE
      WHEN S.`SammlungBild1` IS NULL OR TRIM(S.`SammlungBild1`) = '' THEN '01 leer/NULL'
      WHEN S.`SammlungBild1` LIKE 'http%' AND S.`SammlungBild1` LIKE '%jungsi.de/wp-content/uploads/%' THEN '02 absolute WordPress-URL (jungsi.de/wp-content/uploads)'
      WHEN S.`SammlungBild1` LIKE 'http%' AND S.`SammlungBild1` LIKE '%/wp-content/uploads/%' THEN '03 absolute WordPress-URL, anderer Host'
      WHEN S.`SammlungBild1` LIKE 'http://%' OR S.`SammlungBild1` LIKE 'https://%' THEN '04 andere absolute URL'
      WHEN S.`SammlungBild1` LIKE '%/%' THEN '05 relativer Pfad mit Ordner'
      ELSE '06 nur Dateiname'
    END AS Muster
  FROM `Sammlung` S) f
GROUP BY f.Muster
UNION ALL
SELECT 'Basis-URL vor /wp-content/uploads/' AS Spalte, SUBSTRING_INDEX(S.`SammlungBild1`, '/wp-content/uploads/', 1) AS Befund, COUNT(*) AS Anzahl, '' AS Beispiele
FROM `Sammlung` S
WHERE S.`SammlungBild1` LIKE '%/wp-content/uploads/%'
GROUP BY SUBSTRING_INDEX(S.`SammlungBild1`, '/wp-content/uploads/', 1)
UNION ALL
SELECT 'Dateiendung' AS Spalte, LOWER(SUBSTRING_INDEX(S.`SammlungBild1`, '.', -1)) AS Befund, COUNT(*) AS Anzahl, '' AS Beispiele
FROM `Sammlung` S
WHERE S.`SammlungBild1` IS NOT NULL AND S.`SammlungBild1` LIKE '%.%' AND CHAR_LENGTH(SUBSTRING_INDEX(S.`SammlungBild1`, '.', -1)) <= 8
GROUP BY LOWER(SUBSTRING_INDEX(S.`SammlungBild1`, '.', -1))
UNION ALL
SELECT 'Auffälligkeit' AS Spalte, 'Leerzeichen am Rand' AS Befund, COALESCE(SUM(S.`SammlungBild1` <> TRIM(S.`SammlungBild1`)), 0) AS Anzahl,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN S.`SammlungBild1` <> TRIM(S.`SammlungBild1`) THEN S.`SammlungBild1` END ORDER BY S.`SammlungBild1` SEPARATOR ' | '), ' | ', 5), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Auffälligkeit' AS Spalte, 'Leerzeichen im Wert' AS Befund, COALESCE(SUM(TRIM(S.`SammlungBild1`) LIKE '% %'), 0) AS Anzahl,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN TRIM(S.`SammlungBild1`) LIKE '% %' THEN S.`SammlungBild1` END ORDER BY S.`SammlungBild1` SEPARATOR ' | '), ' | ', 5), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Auffälligkeit' AS Spalte, 'Nicht-ASCII-Zeichen (Umlaute u. a.)' AS Befund, COALESCE(SUM(S.`SammlungBild1` <> CONVERT(S.`SammlungBild1` USING ascii)), 0) AS Anzahl,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN S.`SammlungBild1` <> CONVERT(S.`SammlungBild1` USING ascii) THEN S.`SammlungBild1` END ORDER BY S.`SammlungBild1` SEPARATOR ' | '), ' | ', 5), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Auffälligkeit' AS Spalte, 'Query-String (?)' AS Befund, COALESCE(SUM(S.`SammlungBild1` LIKE '%?%'), 0) AS Anzahl,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN S.`SammlungBild1` LIKE '%?%' THEN S.`SammlungBild1` END ORDER BY S.`SammlungBild1` SEPARATOR ' | '), ' | ', 5), '') AS Beispiele
FROM `Sammlung` S
UNION ALL
SELECT 'Auffälligkeit' AS Spalte, 'Zeilenumbruch im Wert' AS Befund, COALESCE(SUM(S.`SammlungBild1` LIKE CONCAT('%', CHAR(10), '%')), 0) AS Anzahl,
  COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN S.`SammlungBild1` LIKE CONCAT('%', CHAR(10), '%') THEN S.`SammlungBild1` END ORDER BY S.`SammlungBild1` SEPARATOR ' | '), ' | ', 5), '') AS Beispiele
FROM `Sammlung` S
ORDER BY Spalte, Befund;

-- ===== BLOCK 5: Dubletten-Kandidaten in Lookup-Tabellen (docs/bestand/05_dubletten.csv) =====
-- Nur eine HINWEISLISTE, kein Urteil: 'Atari' und 'Atari Corporation' können getrennte Einträge sein.
-- Stufe 1: gleich nach Kleinschreibung ohne Leerzeichen/Satzzeichen ('&' zählt als 'and').
-- Stufe 2: zusätzlich ohne Rechtsformzusatz am Ende (GmbH, Ltd, Inc, Corp ...), nur Gruppen, die in Stufe 1 noch verschieden sind.
-- Kandidaten: ID:Name (Anzahl Einträge in Sammlung, die ihn verwenden).

SELECT 'Hersteller' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Hersteller` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Hersteller`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Hersteller` L
      LEFT JOIN `Sammlung` S ON S.`Hersteller` = L.`ID`
      GROUP BY L.`ID`, L.`Hersteller`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Hersteller' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Hersteller` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Hersteller`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Hersteller` L
      LEFT JOIN `Sammlung` S ON S.`Hersteller` = L.`ID`
      GROUP BY L.`ID`, L.`Hersteller`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Publisher' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Publisher` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Publisher`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Publisher` L
      LEFT JOIN `Sammlung` S ON S.`Publisher` = L.`ID`
      GROUP BY L.`ID`, L.`Publisher`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Publisher' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Publisher` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Publisher`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Publisher` L
      LEFT JOIN `Sammlung` S ON S.`Publisher` = L.`ID`
      GROUP BY L.`ID`, L.`Publisher`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Verkäufer' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Verkäufer` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Verkäufer`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Verkäufer` L
      LEFT JOIN `Sammlung` S ON S.`Verkäufer` = L.`ID`
      GROUP BY L.`ID`, L.`Verkäufer`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Verkäufer' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Verkäufer` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Verkäufer`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Verkäufer` L
      LEFT JOIN `Sammlung` S ON S.`Verkäufer` = L.`ID`
      GROUP BY L.`ID`, L.`Verkäufer`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Standort' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Standort` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Standort`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Standort` L
      LEFT JOIN `Sammlung` S ON S.`Standort` = L.`ID`
      GROUP BY L.`ID`, L.`Standort`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Standort' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Standort` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Standort`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Standort` L
      LEFT JOIN `Sammlung` S ON S.`Standort` = L.`ID`
      GROUP BY L.`ID`, L.`Standort`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Material' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Material` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Material`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Material` L
      LEFT JOIN `Sammlung` S ON S.`Material` = L.`ID`
      GROUP BY L.`ID`, L.`Material`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Material' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Material` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Material`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Material` L
      LEFT JOIN `Sammlung` S ON S.`Material` = L.`ID`
      GROUP BY L.`ID`, L.`Material`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Zustand' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Zustand` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Zustand`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Zustand` L
      LEFT JOIN `Sammlung` S ON S.`Zustand` = L.`ID`
      GROUP BY L.`ID`, L.`Zustand`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Zustand' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Zustand` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Zustand`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Zustand` L
      LEFT JOIN `Sammlung` S ON S.`Zustand` = L.`ID`
      GROUP BY L.`ID`, L.`Zustand`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Verpackung' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Verpackung` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Verpackung`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Verpackung` L
      LEFT JOIN `Sammlung` S ON S.`Verpackung` = L.`ID`
      GROUP BY L.`ID`, L.`Verpackung`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Verpackung' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Verpackung` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Verpackung`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Verpackung` L
      LEFT JOIN `Sammlung` S ON S.`Verpackung` = L.`ID`
      GROUP BY L.`ID`, L.`Verpackung`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Datentraeger' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Datentrager` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Datentrager`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Datentraeger` L
      LEFT JOIN `Sammlung` S ON S.`Datentraeger` = L.`ID`
      GROUP BY L.`ID`, L.`Datentrager`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Datentraeger' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Datentrager` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Datentrager`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Datentraeger` L
      LEFT JOIN `Sammlung` S ON S.`Datentraeger` = L.`ID`
      GROUP BY L.`ID`, L.`Datentrager`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Box' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Box` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Box`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Box` L
      LEFT JOIN `Sammlung` S ON S.`Box` = L.`ID`
      GROUP BY L.`ID`, L.`Box`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Box' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Box` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Box`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Box` L
      LEFT JOIN `Sammlung` S ON S.`Box` = L.`ID`
      GROUP BY L.`ID`, L.`Box`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
UNION ALL
SELECT 'Kategorie' AS Tabelle, '1 gleich nach Normalisierung' AS Stufe, n.n1 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Kategorie` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Kategorie`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Kategorie` L
      LEFT JOIN `Sammlung` S ON S.`Kategorie` = L.`ID`
      GROUP BY L.`ID`, L.`Kategorie`) m
) n
GROUP BY n.n1
HAVING COUNT(*) > 1 AND n.n1 <> ''
UNION ALL
SELECT 'Kategorie' AS Tabelle, '2 gleich ohne Rechtsform' AS Stufe, n.n2 AS Schluessel, COUNT(*) AS Anzahl_Namen,
  GROUP_CONCAT(CONCAT(n.ID, ':', COALESCE(n.Name, ''), ' (', n.Verwendet, 'x)') ORDER BY n.ID SEPARATOR ' | ') AS Kandidaten
FROM (
    SELECT m.ID, m.Name, m.n1, m.Verwendet,
      CASE
        WHEN m.n1 LIKE '%incorporated' AND CHAR_LENGTH(m.n1) > 14 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 12)
        WHEN m.n1 LIKE '%corporation' AND CHAR_LENGTH(m.n1) > 13 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 11)
        WHEN m.n1 LIKE '%limited' AND CHAR_LENGTH(m.n1) > 9 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 7)
        WHEN m.n1 LIKE '%gmbh' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%corp' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%sarl' AND CHAR_LENGTH(m.n1) > 6 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 4)
        WHEN m.n1 LIKE '%ltd' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%inc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%llc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%plc' AND CHAR_LENGTH(m.n1) > 5 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 3)
        WHEN m.n1 LIKE '%bv' AND CHAR_LENGTH(m.n1) > 4 THEN LEFT(m.n1, CHAR_LENGTH(m.n1) - 2)
        ELSE m.n1 END AS n2
    FROM (SELECT L.`ID` AS ID, L.`Kategorie` AS Name,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(L.`Kategorie`, '')), ' ', ''), '.', ''), ',', ''), '-', ''), '_', ''), '''', ''), '"', ''), '/', ''), '(', ''), ')', ''), '&', 'and') AS n1,
        COUNT(S.`ID`) AS Verwendet
      FROM `Kategorie` L
      LEFT JOIN `Sammlung` S ON S.`Kategorie` = L.`ID`
      GROUP BY L.`ID`, L.`Kategorie`) m
) n
GROUP BY n.n2
HAVING COUNT(*) > 1 AND n.n2 <> '' AND COUNT(DISTINCT n.n1) > 1
ORDER BY Tabelle, Stufe, Schluessel;

-- ===== BLOCK 6: Alle Kategorien mit Anzahl Einträge (docs/bestand/06_kategorien.csv) =====
-- Grundlage für das Kategorie-Mapping in D9.
SELECT K.`ID` AS ID, K.`Kategorie` AS Name, K.`Spiele` AS Spiele_Flag, COUNT(S.`ID`) AS Anzahl
FROM `Kategorie` K
LEFT JOIN `Sammlung` S ON S.`Kategorie` = K.`ID`
GROUP BY K.`ID`, K.`Kategorie`, K.`Spiele`
ORDER BY K.`Kategorie`;

-- ===== BLOCK 7: Server, Tabellen, Spalten, Fremdschlüssel, Indizes (docs/bestand/07_server.csv) =====
-- Enthält VERSION() und die Collation je Tabelle und Spalte (Grundlage für D4 und D7).
SELECT 'Server' AS Bereich, 'Version' AS Objekt, CONVERT(CAST(VERSION() AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Server' AS Bereich, 'sql_mode' AS Objekt, CONVERT(CAST(@@sql_mode AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Server' AS Bereich, 'Zeichensatz (Datenbank)' AS Objekt, CONVERT(CAST(@@character_set_database AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Server' AS Bereich, 'Collation (Datenbank)' AS Objekt, CONVERT(CAST(@@collation_database AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Server' AS Bereich, 'lower_case_table_names' AS Objekt, CONVERT(CAST(@@lower_case_table_names AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Server' AS Bereich, 'group_concat_max_len' AS Objekt, CONVERT(CAST(@@group_concat_max_len AS CHAR) USING utf8mb4) AS Detail, '' AS Detail2
UNION ALL
SELECT 'Tabelle', CONVERT(t.table_name USING utf8mb4), CONVERT(COALESCE(t.engine, '') USING utf8mb4), CONVERT(CONCAT(COALESCE(t.table_collation, ''), ' / ca. ', COALESCE(t.table_rows, 0), ' Zeilen') USING utf8mb4)
FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'
UNION ALL
SELECT 'Spalte', CONVERT(CONCAT(c.table_name, '.', c.column_name) USING utf8mb4), CONVERT(c.column_type USING utf8mb4), CONVERT(CONCAT(IF(c.is_nullable = 'YES', 'NULL', 'NOT NULL'), COALESCE(CONCAT(' / ', c.collation_name), '')) USING utf8mb4)
FROM information_schema.columns c WHERE c.table_schema = DATABASE()
UNION ALL
SELECT 'Fremdschlüssel', CONVERT(CONCAT(k.table_name, '.', k.column_name) USING utf8mb4), CONVERT(CONCAT(k.referenced_table_name, '.', k.referenced_column_name) USING utf8mb4), CONVERT(k.constraint_name USING utf8mb4)
FROM information_schema.key_column_usage k WHERE k.table_schema = DATABASE() AND k.referenced_table_name IS NOT NULL
UNION ALL
SELECT 'Fremdschlüssel-Regel', CONVERT(r.constraint_name USING utf8mb4), CONVERT(CONCAT('DELETE ', r.delete_rule) USING utf8mb4), CONVERT(CONCAT('UPDATE ', r.update_rule) USING utf8mb4)
FROM information_schema.referential_constraints r WHERE r.constraint_schema = DATABASE()
UNION ALL
SELECT 'Index', CONVERT(CONCAT(s.table_name, '.', s.index_name) USING utf8mb4), CONVERT(GROUP_CONCAT(s.column_name ORDER BY s.seq_in_index) USING utf8mb4), CONVERT(IF(s.non_unique = 0, 'UNIQUE', '') USING utf8mb4)
FROM information_schema.statistics s WHERE s.table_schema = DATABASE()
GROUP BY s.table_name, s.index_name, s.non_unique
ORDER BY 1, 2;
