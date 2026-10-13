-- Migration D5: Lookup-Tabellen eindeutig (Phase 2)
-- ============================================================================
-- Namensspalte jeder Lookup-Tabelle: text -> VARCHAR(190) NOT NULL mit UNIQUE-Index.
-- Gilt für Kategorie, Zustand, Verpackung, Material, Standort, Hersteller, Publisher, Verkäufer,
-- Datentraeger und Box. Vergleich ohne Groß-/Kleinschreibung (Collation utf8mb4_general_ci).
-- VORHER: Dump per phpMyAdmin und den Code dieser Version hochladen (inc_manage_lookup.php, inc_security.php).
-- Wiederholbar: Ein zweiter Lauf ändert nichts mehr.
--
-- Schritt 1 ist nur eine Prüfung: Sie muss LEER sein (keine Zeilen). Sonst bricht das ALTER mit
-- Fehler 1062 ab, dann die genannten Namen zuerst in den Pflegeseiten zusammenführen oder umbenennen.
-- Geprüft vorab (13.10.2026, nach D3): keine Kollisionen, keine leeren Namen, längster Name 64 Zeichen.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Prüfung: doppelte oder leere Namen (erwartet: 0 Zeilen)
-- ---------------------------------------------------------------------------
SELECT 'Kategorie' AS Tabelle, `Kategorie` AS Name, COUNT(*) AS Anzahl FROM `Kategorie` GROUP BY `Kategorie` HAVING COUNT(*) > 1 OR `Kategorie` IS NULL OR TRIM(`Kategorie`) = ''
UNION ALL
SELECT 'Zustand' AS Tabelle, `Zustand` AS Name, COUNT(*) AS Anzahl FROM `Zustand` GROUP BY `Zustand` HAVING COUNT(*) > 1 OR `Zustand` IS NULL OR TRIM(`Zustand`) = ''
UNION ALL
SELECT 'Verpackung' AS Tabelle, `Verpackung` AS Name, COUNT(*) AS Anzahl FROM `Verpackung` GROUP BY `Verpackung` HAVING COUNT(*) > 1 OR `Verpackung` IS NULL OR TRIM(`Verpackung`) = ''
UNION ALL
SELECT 'Material' AS Tabelle, `Material` AS Name, COUNT(*) AS Anzahl FROM `Material` GROUP BY `Material` HAVING COUNT(*) > 1 OR `Material` IS NULL OR TRIM(`Material`) = ''
UNION ALL
SELECT 'Standort' AS Tabelle, `Standort` AS Name, COUNT(*) AS Anzahl FROM `Standort` GROUP BY `Standort` HAVING COUNT(*) > 1 OR `Standort` IS NULL OR TRIM(`Standort`) = ''
UNION ALL
SELECT 'Hersteller' AS Tabelle, `Hersteller` AS Name, COUNT(*) AS Anzahl FROM `Hersteller` GROUP BY `Hersteller` HAVING COUNT(*) > 1 OR `Hersteller` IS NULL OR TRIM(`Hersteller`) = ''
UNION ALL
SELECT 'Publisher' AS Tabelle, `Publisher` AS Name, COUNT(*) AS Anzahl FROM `Publisher` GROUP BY `Publisher` HAVING COUNT(*) > 1 OR `Publisher` IS NULL OR TRIM(`Publisher`) = ''
UNION ALL
SELECT 'Verkäufer' AS Tabelle, `Verkäufer` AS Name, COUNT(*) AS Anzahl FROM `Verkäufer` GROUP BY `Verkäufer` HAVING COUNT(*) > 1 OR `Verkäufer` IS NULL OR TRIM(`Verkäufer`) = ''
UNION ALL
SELECT 'Datentraeger' AS Tabelle, `Datentrager` AS Name, COUNT(*) AS Anzahl FROM `Datentraeger` GROUP BY `Datentrager` HAVING COUNT(*) > 1 OR `Datentrager` IS NULL OR TRIM(`Datentrager`) = ''
UNION ALL
SELECT 'Box' AS Tabelle, `Box` AS Name, COUNT(*) AS Anzahl FROM `Box` GROUP BY `Box` HAVING COUNT(*) > 1 OR `Box` IS NULL OR TRIM(`Box`) = '';

-- ---------------------------------------------------------------------------
-- 2. Typ und UNIQUE-Index (ein ALTER je Tabelle; wird übersprungen, wenn der Index
--    uq_<Tabelle>_name schon besteht)
-- ---------------------------------------------------------------------------
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND INDEX_NAME = 'uq_Kategorie_name'),
  'SELECT 1',
  'ALTER TABLE `Kategorie` MODIFY `Kategorie` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Kategorie_name` (`Kategorie`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Zustand' AND INDEX_NAME = 'uq_Zustand_name'),
  'SELECT 1',
  'ALTER TABLE `Zustand` MODIFY `Zustand` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Zustand_name` (`Zustand`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Verpackung' AND INDEX_NAME = 'uq_Verpackung_name'),
  'SELECT 1',
  'ALTER TABLE `Verpackung` MODIFY `Verpackung` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Verpackung_name` (`Verpackung`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Material' AND INDEX_NAME = 'uq_Material_name'),
  'SELECT 1',
  'ALTER TABLE `Material` MODIFY `Material` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Material_name` (`Material`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Standort' AND INDEX_NAME = 'uq_Standort_name'),
  'SELECT 1',
  'ALTER TABLE `Standort` MODIFY `Standort` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Standort_name` (`Standort`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Hersteller' AND INDEX_NAME = 'uq_Hersteller_name'),
  'SELECT 1',
  'ALTER TABLE `Hersteller` MODIFY `Hersteller` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Hersteller_name` (`Hersteller`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Publisher' AND INDEX_NAME = 'uq_Publisher_name'),
  'SELECT 1',
  'ALTER TABLE `Publisher` MODIFY `Publisher` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Publisher_name` (`Publisher`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Verkäufer' AND INDEX_NAME = 'uq_Verkäufer_name'),
  'SELECT 1',
  'ALTER TABLE `Verkäufer` MODIFY `Verkäufer` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Verkäufer_name` (`Verkäufer`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Datentraeger' AND INDEX_NAME = 'uq_Datentraeger_name'),
  'SELECT 1',
  'ALTER TABLE `Datentraeger` MODIFY `Datentrager` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Datentraeger_name` (`Datentrager`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Box' AND INDEX_NAME = 'uq_Box_name'),
  'SELECT 1',
  'ALTER TABLE `Box` MODIFY `Box` VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL, ADD UNIQUE KEY `uq_Box_name` (`Box`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 3. Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_05_lookup_unique.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT 'Kategorie.Kategorie' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Kategorie' AND s.COLUMN_NAME = 'Kategorie' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND COLUMN_NAME = 'Kategorie') AS ist, 0 AS o
  UNION ALL
  SELECT 'Zustand.Zustand' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Zustand' AND s.COLUMN_NAME = 'Zustand' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Zustand' AND COLUMN_NAME = 'Zustand') AS ist, 1 AS o
  UNION ALL
  SELECT 'Verpackung.Verpackung' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Verpackung' AND s.COLUMN_NAME = 'Verpackung' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Verpackung' AND COLUMN_NAME = 'Verpackung') AS ist, 2 AS o
  UNION ALL
  SELECT 'Material.Material' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Material' AND s.COLUMN_NAME = 'Material' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Material' AND COLUMN_NAME = 'Material') AS ist, 3 AS o
  UNION ALL
  SELECT 'Standort.Standort' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Standort' AND s.COLUMN_NAME = 'Standort' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Standort' AND COLUMN_NAME = 'Standort') AS ist, 4 AS o
  UNION ALL
  SELECT 'Hersteller.Hersteller' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Hersteller' AND s.COLUMN_NAME = 'Hersteller' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Hersteller' AND COLUMN_NAME = 'Hersteller') AS ist, 5 AS o
  UNION ALL
  SELECT 'Publisher.Publisher' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Publisher' AND s.COLUMN_NAME = 'Publisher' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Publisher' AND COLUMN_NAME = 'Publisher') AS ist, 6 AS o
  UNION ALL
  SELECT 'Verkäufer.Verkäufer' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Verkäufer' AND s.COLUMN_NAME = 'Verkäufer' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Verkäufer' AND COLUMN_NAME = 'Verkäufer') AS ist, 7 AS o
  UNION ALL
  SELECT 'Datentraeger.Datentrager' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Datentraeger' AND s.COLUMN_NAME = 'Datentrager' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Datentraeger' AND COLUMN_NAME = 'Datentrager') AS ist, 8 AS o
  UNION ALL
  SELECT 'Box.Box' AS Pruefung, 'varchar(190) NOT NULL, unique' AS erwartet,
         (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL'), ', ',
                 IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = DATABASE()
                            AND s.TABLE_NAME = 'Box' AND s.COLUMN_NAME = 'Box' AND s.NON_UNIQUE = 0), 'unique', 'NICHT unique'))
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Box' AND COLUMN_NAME = 'Box') AS ist, 9 AS o
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Entfernt die UNIQUE-Indizes und stellt text wieder her.
-- ---------------------------------------------------------------------------
-- ALTER TABLE `Kategorie` DROP INDEX `uq_Kategorie_name`, MODIFY `Kategorie` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Zustand` DROP INDEX `uq_Zustand_name`, MODIFY `Zustand` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Verpackung` DROP INDEX `uq_Verpackung_name`, MODIFY `Verpackung` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Material` DROP INDEX `uq_Material_name`, MODIFY `Material` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Standort` DROP INDEX `uq_Standort_name`, MODIFY `Standort` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Hersteller` DROP INDEX `uq_Hersteller_name`, MODIFY `Hersteller` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Publisher` DROP INDEX `uq_Publisher_name`, MODIFY `Publisher` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Verkäufer` DROP INDEX `uq_Verkäufer_name`, MODIFY `Verkäufer` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Datentraeger` DROP INDEX `uq_Datentraeger_name`, MODIFY `Datentrager` TEXT COLLATE utf8mb4_general_ci;
-- ALTER TABLE `Box` DROP INDEX `uq_Box_name`, MODIFY `Box` TEXT COLLATE utf8mb4_general_ci;
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_05_lookup_unique.sql';
