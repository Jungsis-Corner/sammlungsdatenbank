-- Migration D7: Fremdschlüssel und Indizes (Phase 2)
-- ============================================================================
-- 1. Boxnamen: reine Zahlen werden zu "Box N" ("Regal" bleibt). Die Tabelle Box wird jetzt benutzt
--    (Sammlung.Box ist ein Verweis auf Box.ID, die Pflege läuft über manage_box.php).
-- 2. Indizes auf den elf Verweisspalten von Sammlung sowie auf Bezeichnung(191), Zum Verkauf,
--    Barcode und Einkaufsdatum.
-- 3. Elf Fremdschlüssel von Sammlung:
--      Kategorie, Zustand, Verpackung, Material, Standort, Hersteller, Publisher, Verkäufer,
--      Datentraeger, Box -> jeweilige Lookup-Tabelle: ON UPDATE CASCADE, ON DELETE RESTRICT
--      VerbautIn -> Sammlung.ID:                      ON UPDATE CASCADE, ON DELETE SET NULL
--
-- VORHER: Dump per phpMyAdmin und den Code dieser Version hochladen (inc_manage_lookup.php,
-- inc_security.php, manage_box.php, einstellungen.php, edit.php, index.php, view.php, export.php).
-- Kein Wartungsmodus nötig. Wiederholbar: Vorhandene Indizes/Schlüssel werden übersprungen.
-- Vorab geprüft (13.10.2026): keine verwaisten Verweise, keine 0-Werte.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Prüfung: Verweise ohne Gegenstück (erwartet: 0 Zeilen). Sonst scheitert das Anlegen der Schlüssel.
-- ---------------------------------------------------------------------------
SELECT 'Kategorie' AS Spalte, s.`ID`, s.`Kategorie` AS Verweis FROM `Sammlung` s LEFT JOIN `Kategorie` l ON l.`ID` = s.`Kategorie` WHERE s.`Kategorie` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Zustand' AS Spalte, s.`ID`, s.`Zustand` AS Verweis FROM `Sammlung` s LEFT JOIN `Zustand` l ON l.`ID` = s.`Zustand` WHERE s.`Zustand` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Verpackung' AS Spalte, s.`ID`, s.`Verpackung` AS Verweis FROM `Sammlung` s LEFT JOIN `Verpackung` l ON l.`ID` = s.`Verpackung` WHERE s.`Verpackung` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Material' AS Spalte, s.`ID`, s.`Material` AS Verweis FROM `Sammlung` s LEFT JOIN `Material` l ON l.`ID` = s.`Material` WHERE s.`Material` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Standort' AS Spalte, s.`ID`, s.`Standort` AS Verweis FROM `Sammlung` s LEFT JOIN `Standort` l ON l.`ID` = s.`Standort` WHERE s.`Standort` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Hersteller' AS Spalte, s.`ID`, s.`Hersteller` AS Verweis FROM `Sammlung` s LEFT JOIN `Hersteller` l ON l.`ID` = s.`Hersteller` WHERE s.`Hersteller` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Publisher' AS Spalte, s.`ID`, s.`Publisher` AS Verweis FROM `Sammlung` s LEFT JOIN `Publisher` l ON l.`ID` = s.`Publisher` WHERE s.`Publisher` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Verkäufer' AS Spalte, s.`ID`, s.`Verkäufer` AS Verweis FROM `Sammlung` s LEFT JOIN `Verkäufer` l ON l.`ID` = s.`Verkäufer` WHERE s.`Verkäufer` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Datentraeger' AS Spalte, s.`ID`, s.`Datentraeger` AS Verweis FROM `Sammlung` s LEFT JOIN `Datentraeger` l ON l.`ID` = s.`Datentraeger` WHERE s.`Datentraeger` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'Box' AS Spalte, s.`ID`, s.`Box` AS Verweis FROM `Sammlung` s LEFT JOIN `Box` l ON l.`ID` = s.`Box` WHERE s.`Box` IS NOT NULL AND l.`ID` IS NULL
UNION ALL
SELECT 'VerbautIn' AS Spalte, s.`ID`, s.`VerbautIn` AS Verweis FROM `Sammlung` s LEFT JOIN `Sammlung` l ON l.`ID` = s.`VerbautIn` WHERE s.`VerbautIn` IS NOT NULL AND l.`ID` IS NULL;

-- ---------------------------------------------------------------------------
-- 2. Boxnamen: "7" -> "Box 7" (nur reine Zahlen, daher wiederholbar)
-- ---------------------------------------------------------------------------
UPDATE `Box` SET `Box` = CONCAT('Box ', `Box`) WHERE `Box` REGEXP '^[0-9]+$';

-- ---------------------------------------------------------------------------
-- 3. Indizes (übersprungen, wenn schon vorhanden)
-- ---------------------------------------------------------------------------
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_kategorie'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_kategorie` (`Kategorie`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_zustand'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_zustand` (`Zustand`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_verpackung'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_verpackung` (`Verpackung`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_material'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_material` (`Material`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_standort'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_standort` (`Standort`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_hersteller'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_hersteller` (`Hersteller`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_publisher'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_publisher` (`Publisher`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_verkaeufer'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_verkaeufer` (`Verkäufer`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_datentraeger'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_datentraeger` (`Datentraeger`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_box'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_box` (`Box`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_verbautin'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_verbautin` (`VerbautIn`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_bezeichnung'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_bezeichnung` (Bezeichnung(191))');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_verkauf'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_verkauf` (`Zum Verkauf`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_barcode'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_barcode` (`Barcode`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND INDEX_NAME = 'idx_sammlung_einkaufsdatum'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD KEY `idx_sammlung_einkaufsdatum` (`Einkaufsdatum`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 4. Fremdschlüssel (übersprungen, wenn schon vorhanden)
-- ---------------------------------------------------------------------------
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_kategorie'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_kategorie` FOREIGN KEY (`Kategorie`) REFERENCES `Kategorie` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_zustand'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_zustand` FOREIGN KEY (`Zustand`) REFERENCES `Zustand` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_verpackung'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_verpackung` FOREIGN KEY (`Verpackung`) REFERENCES `Verpackung` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_material'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_material` FOREIGN KEY (`Material`) REFERENCES `Material` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_standort'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_standort` FOREIGN KEY (`Standort`) REFERENCES `Standort` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_hersteller'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_hersteller` FOREIGN KEY (`Hersteller`) REFERENCES `Hersteller` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_publisher'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_publisher` FOREIGN KEY (`Publisher`) REFERENCES `Publisher` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_verkaeufer'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_verkaeufer` FOREIGN KEY (`Verkäufer`) REFERENCES `Verkäufer` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_datentraeger'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_datentraeger` FOREIGN KEY (`Datentraeger`) REFERENCES `Datentraeger` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_box'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_box` FOREIGN KEY (`Box`) REFERENCES `Box` (`ID`) ON UPDATE CASCADE ON DELETE RESTRICT');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND CONSTRAINT_NAME = 'fk_sammlung_verbautin'),
  'SELECT 1', 'ALTER TABLE `Sammlung` ADD CONSTRAINT `fk_sammlung_verbautin` FOREIGN KEY (`VerbautIn`) REFERENCES `Sammlung` (`ID`) ON UPDATE CASCADE ON DELETE SET NULL');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 5. Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_07_fremdschluessel.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT 'Fremdschlüssel von Sammlung mit RESTRICT' AS Pruefung, 10 AS erwartet,
         (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung'
            AND CONSTRAINT_NAME LIKE 'fk\_sammlung\_%' AND DELETE_RULE = 'RESTRICT' AND UPDATE_RULE = 'CASCADE') AS ist, 1 AS o
  UNION ALL
  SELECT 'Fremdschlüssel VerbautIn (SET NULL)', 1,
         (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung'
            AND CONSTRAINT_NAME = 'fk_sammlung_verbautin' AND DELETE_RULE = 'SET NULL' AND UPDATE_RULE = 'CASCADE'), 2
  UNION ALL
  SELECT 'Indizes idx_sammlung_*', 15,
         (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung'
            AND INDEX_NAME LIKE 'idx\_sammlung\_%'), 3
  UNION ALL
  SELECT 'Boxnamen ohne "Box N" (außer Regal)', 0,
         (SELECT COUNT(*) FROM `Box` WHERE `Box` NOT REGEXP '^Box [0-9]+$' AND `Box` <> 'Regal'), 4
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Entfernt Schlüssel und Indizes, Boxnamen wieder als Zahl.
-- ---------------------------------------------------------------------------
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_kategorie`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_zustand`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_verpackung`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_material`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_standort`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_hersteller`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_publisher`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_verkaeufer`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_datentraeger`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_box`;
-- ALTER TABLE `Sammlung` DROP FOREIGN KEY `fk_sammlung_verbautin`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_kategorie`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_zustand`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_verpackung`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_material`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_standort`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_hersteller`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_publisher`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_verkaeufer`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_datentraeger`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_box`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_verbautin`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_bezeichnung`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_verkauf`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_barcode`;
-- ALTER TABLE `Sammlung` DROP INDEX `idx_sammlung_einkaufsdatum`;
-- UPDATE `Box` SET `Box` = SUBSTRING(`Box`, 5) WHERE `Box` REGEXP '^Box [0-9]+$';
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_07_fremdschluessel.sql';
